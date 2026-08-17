<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use App\Repositories\BackupRepository;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class AutomatedBackupSystemTest extends TestCase
{
    use RefreshDatabase;

    private string $testBackupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testBackupDir = storage_path('app/backups_test');
        config(['backup.path' => $this->testBackupDir]);
        config(['backup.retention.keep_days' => 7]);
        config(['backup.retention.keep_minimum_count' => 2]);

        if (File::exists($this->testBackupDir)) {
            File::deleteDirectory($this->testBackupDir);
        }
        File::makeDirectory($this->testBackupDir, 0755, true, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testBackupDir)) {
            File::deleteDirectory($this->testBackupDir);
        }
        parent::tearDown();
    }

    public function test_admin_can_view_backup_management_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.backups.index'));
        $response->assertOk();
        $response->assertSee('สำรองและกู้คืนข้อมูลระบบ');
        $response->assertSee('สำรองข้อมูลทันที');
    }

    public function test_non_admin_cannot_access_backup_management(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('admin.backups.index'));
        $response->assertRedirect(route('activities.index'));

        $jsonResponse = $this->actingAs($student)->getJson(route('admin.backups.index'));
        $jsonResponse->assertForbidden();
    }

    public function test_artisan_backup_run_command_creates_valid_database_backup(): void
    {
        // Create sample data
        User::factory()->count(3)->create();
        Activity::factory()->count(2)->create();

        $exitCode = Artisan::call('backup:run', ['--type' => 'db', '--no-notify' => true]);
        $this->assertEquals(0, $exitCode);

        $repo = app(BackupRepository::class);
        $backups = $repo->getAllBackups();

        $this->assertNotEmpty($backups);
        $latest = $backups[0];
        $this->assertEquals('db', $latest['type']);
        $this->assertFileExists((string)$latest['path']);

        // Verify ZIP contents
        $zip = new ZipArchive();
        $this->assertTrue($zip->open((string)$latest['path']));
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('manifest.json'));

        $sqlContent = $zip->getFromName('database.sql');
        $this->assertStringContainsString('BEGIN;', $sqlContent);
        $this->assertStringContainsString('COMMIT;', $sqlContent);

        $manifestContent = json_decode((string)$zip->getFromName('manifest.json'), true);
        $this->assertEquals('db', $manifestContent['type']);
        $this->assertContains('database', $manifestContent['included']);

        $zip->close();
    }

    public function test_artisan_backup_run_command_creates_biometric_snapshot(): void
    {
        $descriptor512 = array_fill(0, 512, 0.05);
        $descriptor128 = array_fill(0, 128, 0.09);

        $user = User::factory()->create([
            'student_id'         => 'BIO_BACKUP_01',
            'face_descriptor'    => $descriptor512,
            'face_descriptor_js' => $descriptor128,
        ]);

        $activity = Activity::factory()->create();

        Attendance::factory()->create([
            'user_id'          => $user->id,
            'activity_id'      => $activity->id,
            'method'           => 'qr_scan',
            'face_match_score' => 0.98,
        ]);

        $exitCode = Artisan::call('backup:run', ['--type' => 'biometrics', '--no-notify' => true]);
        $this->assertEquals(0, $exitCode);

        $repo = app(BackupRepository::class);
        $backups = $repo->getAllBackups();
        $latest = $backups[0];

        $this->assertEquals('biometrics', $latest['type']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open((string)$latest['path']));
        $this->assertNotFalse($zip->locateName('biometrics_attendance.json'));

        $bioJson = json_decode((string)$zip->getFromName('biometrics_attendance.json'), true);
        $this->assertIsArray($bioJson);
        $this->assertGreaterThanOrEqual(1, $bioJson['total_users']);
        $this->assertGreaterThanOrEqual(1, $bioJson['total_attendances']);

        $zip->close();
    }

    public function test_artisan_backup_run_command_creates_full_backup_with_manifest(): void
    {
        User::factory()->create();
        Activity::factory()->create();

        $exitCode = Artisan::call('backup:run', ['--type' => 'full', '--no-notify' => true]);
        $this->assertEquals(0, $exitCode);

        $repo = app(BackupRepository::class);
        $backups = $repo->getAllBackups();
        $latest = $backups[0];

        $this->assertEquals('full', $latest['type']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open((string)$latest['path']));
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('biometrics_attendance.json'));
        $this->assertNotFalse($zip->locateName('manifest.json'));

        $manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
        $this->assertEquals('full', $manifest['type']);
        $this->assertContains('database', $manifest['included']);
        $this->assertContains('biometrics', $manifest['included']);
        $this->assertContains('files', $manifest['included']);

        $zip->close();
    }

    public function test_artisan_backup_clean_prunes_old_backups_while_retaining_minimum_count(): void
    {
        $repo = app(BackupRepository::class);

        // Create 4 simulated backups with different timestamps
        $oldTimestamp1 = Carbon::now()->subDays(20)->timestamp;
        $oldTimestamp2 = Carbon::now()->subDays(15)->timestamp;
        $recentTimestamp1 = Carbon::now()->subDays(2)->timestamp;
        $recentTimestamp2 = Carbon::now()->subDays(1)->timestamp;

        $file1 = $this->testBackupDir . DIRECTORY_SEPARATOR . 'backup-2026-08-01_00-00-00-db.zip';
        $file2 = $this->testBackupDir . DIRECTORY_SEPARATOR . 'backup-2026-08-05_00-00-00-db.zip';
        $file3 = $this->testBackupDir . DIRECTORY_SEPARATOR . 'backup-2026-08-16_00-00-00-db.zip';
        $file4 = $this->testBackupDir . DIRECTORY_SEPARATOR . 'backup-2026-08-17_00-00-00-db.zip';

        File::put($file1, 'dummy content 1');
        touch($file1, $oldTimestamp1);

        File::put($file2, 'dummy content 2');
        touch($file2, $oldTimestamp2);

        File::put($file3, 'dummy content 3');
        touch($file3, $recentTimestamp1);

        File::put($file4, 'dummy content 4');
        touch($file4, $recentTimestamp2);

        $this->assertCount(4, $repo->getAllBackups());

        // Run clean with keep-days=7, keep-count=2
        $exitCode = Artisan::call('backup:clean', ['--keep-days' => '7', '--keep-count' => '2']);
        $this->assertEquals(0, $exitCode);

        $remaining = $repo->getAllBackups();
        $this->assertCount(2, $remaining);

        // Old files should be deleted
        $this->assertFileDoesNotExist($file1);
        $this->assertFileDoesNotExist($file2);

        // Recent files should still exist
        $this->assertFileExists($file3);
        $this->assertFileExists($file4);
    }

    public function test_admin_can_download_and_delete_backup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create a backup
        app(BackupService::class)->runBackup('db', false);

        $repo = app(BackupRepository::class);
        $backups = $repo->getAllBackups();
        $this->assertNotEmpty($backups);
        $filename = $backups[0]['filename'];

        // Download
        $downloadRes = $this->actingAs($admin)->get(route('admin.backups.download', $filename));
        $downloadRes->assertOk();
        $this->assertTrue($downloadRes->headers->contains('content-type', 'application/zip'));

        // Delete
        $deleteRes = $this->actingAs($admin)->delete(route('admin.backups.destroy', $filename));
        $deleteRes->assertRedirect(route('admin.backups.index'));
        $this->assertFileDoesNotExist($this->testBackupDir . DIRECTORY_SEPARATOR . $filename);
    }

    public function test_backup_actions_are_logged_to_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Manual backup trigger via HTTP post
        $response = $this->actingAs($admin)->post(route('admin.backups.store'), [
            'type' => 'db',
        ]);
        $response->assertRedirect(route('admin.backups.index'));

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action'  => 'create',
        ]);
    }
}
