<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\BackupCompleted;
use App\Models\Attendance;
use App\Models\SecurityLog;
use App\Models\User;
use App\Repositories\BackupRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class BackupService
{
    public function __construct(
        private readonly BackupRepository $backupRepo
    ) {}

    /**
     * Run a system backup.
     *
     * @param string $type 'full', 'db', 'files', 'biometrics'
     * @param bool $notify Send notifications upon completion
     * @return array<string, mixed> Backup summary
     * @throws Exception
     */
    public function runBackup(string $type = 'full', bool $notify = true): array
    {
        $startTime = microtime(true);
        $this->backupRepo->ensureDirectoryExists();

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "backup-{$timestamp}-{$type}.zip";
        $zipPath = $this->backupRepo->getBackupDirectory() . DIRECTORY_SEPARATOR . $filename;
        $tempDir = storage_path('app/temp/backup_' . uniqid());

        File::makeDirectory($tempDir, 0755, true, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($tempDir);
            throw new Exception("Cannot create zip backup file at: {$zipPath}");
        }

        $manifest = [
            'backup_name'     => $filename,
            'type'            => $type,
            'created_at'      => Carbon::now()->toISOString(),
            'app_name'        => config('app.name', 'Uni-Activity'),
            'app_version'     => '1.0.0',
            'laravel_version' => app()->version(),
            'php_version'     => PHP_VERSION,
            'db_connection'   => config('database.default'),
            'included'        => [],
            'stats'           => [],
        ];

        try {
            // 1. Database Backup
            if (in_array($type, ['full', 'db'], true)) {
                $sqlDumpPath = $tempDir . DIRECTORY_SEPARATOR . 'database.sql';
                $dbStats = $this->dumpDatabase($sqlDumpPath);
                $zip->addFile($sqlDumpPath, 'database.sql');

                $manifest['included'][] = 'database';
                $manifest['stats']['database'] = $dbStats;
            }

            // 2. Biometric & Attendance Specialized Snapshot
            if (in_array($type, ['full', 'biometrics'], true)) {
                $bioJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'biometrics_attendance.json';
                $bioStats = $this->dumpBiometricsAndAttendance($bioJsonPath);
                $zip->addFile($bioJsonPath, 'biometrics_attendance.json');

                $manifest['included'][] = 'biometrics';
                $manifest['stats']['biometrics'] = $bioStats;
            }

            // 3. Storage Files Backup
            if (in_array($type, ['full', 'files'], true)) {
                $storagePublicPath = storage_path('app/public');
                $fileStats = $this->archiveStorageFiles($zip, $storagePublicPath);

                $manifest['included'][] = 'files';
                $manifest['stats']['files'] = $fileStats;
            }

            // Save and add manifest.json
            $manifestJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'manifest.json';
            File::put($manifestJsonPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->addFile($manifestJsonPath, 'manifest.json');

            $zip->close();

            // Compute SHA256
            $sha256 = hash_file('sha256', $zipPath);
            File::put($zipPath . '.sha256', $sha256);

            $duration = round(microtime(true) - $startTime, 2);
            $sizeBytes = File::size($zipPath);
            $formattedSize = $this->backupRepo->formatBytes($sizeBytes);

            $result = [
                'filename'       => $filename,
                'path'           => $zipPath,
                'type'           => $type,
                'size_bytes'     => $sizeBytes,
                'formatted_size' => $formattedSize,
                'sha256'         => $sha256,
                'duration_sec'   => $duration,
                'manifest'       => $manifest,
                'is_success'     => true,
            ];

            // Cleanup temp directory
            File::deleteDirectory($tempDir);

            // Dispatch Event
            event(new BackupCompleted(
                filename: $filename,
                type: $type,
                sizeBytes: $sizeBytes,
                formattedSize: $formattedSize,
                isSuccess: true
            ));

            if ($notify) {
                $this->sendBackupNotification($result);
            }

            Log::info("Backup completed successfully: {$filename} ({$formattedSize}) in {$duration}s");

            return $result;
        } catch (Exception $e) {
            $zip->close();
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
            File::deleteDirectory($tempDir);

            Log::error("Backup failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            event(new BackupCompleted(
                filename: $filename,
                type: $type,
                sizeBytes: 0,
                formattedSize: '0 B',
                isSuccess: false,
                errorMessage: $e->getMessage()
            ));

            if ($notify) {
                $this->sendFailureNotification($filename, $type, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Clean old backups based on retention rules.
     *
     * @return array<int, string> Deleted filenames
     */
    public function cleanOldBackups(?int $keepDays = null, ?int $keepCount = null): array
    {
        $days = $keepDays ?? (int) config('backup.retention.keep_days', 14);
        $count = $keepCount ?? (int) config('backup.retention.keep_minimum_count', 5);

        $deleted = $this->backupRepo->cleanOldBackups($days, $count);

        if (!empty($deleted)) {
            Log::info("Backup cleanup removed " . count($deleted) . " old backups: " . implode(', ', $deleted));
        }

        return $deleted;
    }

    /**
     * Stream SQL dump of the database.
     *
     * @return array<string, mixed> Stats
     */
    private function dumpDatabase(string $outputPath): array
    {
        $connection = config('database.default', 'pgsql');
        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            throw new Exception("Unable to open SQL output file for writing: {$outputPath}");
        }

        fwrite($handle, "-- Uni-Activity University System Database Dump\n");
        fwrite($handle, "-- Timestamp: " . Carbon::now()->toDateTimeString() . "\n");
        fwrite($handle, "-- Connection: {$connection}\n\n");
        fwrite($handle, "BEGIN;\n\n");

        $excludedTables = config('backup.exclude_tables', []);
        $tableList = $this->getDatabaseTables();

        $tableStats = [];
        $totalRows = 0;

        foreach ($tableList as $table) {
            if (in_array($table, $excludedTables, true)) {
                continue;
            }

            $rowCount = DB::table($table)->count();
            $tableStats[$table] = $rowCount;
            $totalRows += $rowCount;

            fwrite($handle, "-- --------------------------------------------------------\n");
            fwrite($handle, "-- Table: {$table} ({$rowCount} rows)\n");
            fwrite($handle, "-- --------------------------------------------------------\n\n");

            // Chunk records to prevent high memory usage
            DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use ($handle, $table): void {
                if ($rows->isEmpty()) {
                    return;
                }

                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $columns = array_keys($rowArray);
                    $escapedColumns = array_map(fn ($col) => '"' . str_replace('"', '""', (string)$col) . '"', $columns);

                    $values = [];
                    foreach ($rowArray as $val) {
                        if (is_null($val)) {
                            $values[] = 'NULL';
                        } elseif (is_bool($val)) {
                            $values[] = $val ? 'TRUE' : 'FALSE';
                        } elseif (is_int($val) || is_float($val)) {
                            $values[] = (string) $val;
                        } else {
                            $escaped = str_replace("'", "''", (string) $val);
                            $values[] = "'{$escaped}'";
                        }
                    }

                    $colStr = implode(', ', $escapedColumns);
                    $valStr = implode(', ', $values);
                    fwrite($handle, "INSERT INTO \"{$table}\" ({$colStr}) VALUES ({$valStr});\n");
                }
            });

            fwrite($handle, "\n");
        }

        fwrite($handle, "COMMIT;\n");
        fclose($handle);

        return [
            'tables_count' => count($tableStats),
            'total_rows'   => $totalRows,
            'tables'       => $tableStats,
        ];
    }

    /**
     * Dump Biometric data and check-in audit trails.
     *
     * @return array<string, mixed>
     */
    private function dumpBiometricsAndAttendance(string $outputPath): array
    {
        // 1. Fetch users with biometric descriptors
        $users = User::select([
            'id', 'student_id', 'full_name', 'email', 'role', 'faculty', 'major',
            'face_descriptor', 'face_descriptor_js', 'profile_photo', 'created_at'
        ])->get();

        $usersWith512 = 0;
        $usersWith128 = 0;
        $usersWithPhoto = 0;

        $userPayloads = [];
        foreach ($users as $user) {
            $has512 = !empty($user->face_descriptor);
            $has128 = !empty($user->face_descriptor_js);
            $hasPhoto = !empty($user->profile_photo);

            if ($has512) $usersWith512++;
            if ($has128) $usersWith128++;
            if ($hasPhoto) $usersWithPhoto++;

            $userPayloads[] = [
                'id'                 => $user->id,
                'student_id'         => $user->student_id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'role'               => $user->role,
                'faculty'            => $user->faculty,
                'major'              => $user->major,
                'profile_photo'      => $user->profile_photo,
                'face_descriptor'    => $user->face_descriptor,
                'face_descriptor_js' => $user->face_descriptor_js,
                'created_at'         => $user->created_at?->toIso8601String(),
            ];
        }

        // 2. Fetch all attendance history
        $attendances = Attendance::with(['user:id,student_id,full_name', 'activity:id,title'])->get();
        $attendancePayloads = [];

        foreach ($attendances as $att) {
            $attendancePayloads[] = [
                'id'                 => $att->id,
                'user_id'            => $att->user_id,
                'student_id'         => $att->user?->student_id,
                'student_name'       => $att->user?->full_name,
                'activity_id'        => $att->activity_id,
                'activity_title'     => $att->activity?->title,
                'checked_in_at'      => $att->checked_in_at?->toIso8601String(),
                'checked_out_at'     => $att->checked_out_at?->toIso8601String(),
                'status'             => $att->status,
                'method'             => $att->method ?? 'qr_scan',
                'is_verified'        => $att->is_verified,
                'face_match_score'   => $att->face_match_score,
                'face_match_passed'  => $att->face_match_passed,
                'checkin_latitude'   => $att->checkin_latitude,
                'checkin_longitude'  => $att->checkin_longitude,
                'selfie_photo_path'  => $att->selfie_photo_path,
                'created_at'         => $att->created_at?->toIso8601String(),
            ];
        }

        $payload = [
            'exported_at'       => Carbon::now()->toIso8601String(),
            'total_users'       => count($userPayloads),
            'users_512d_count'  => $usersWith512,
            'users_128d_count'  => $usersWith128,
            'users_photo_count' => $usersWithPhoto,
            'total_attendances' => count($attendancePayloads),
            'users'             => $userPayloads,
            'attendances'       => $attendancePayloads,
        ];

        File::put($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'total_users'       => count($userPayloads),
            'users_512d_count'  => $usersWith512,
            'users_128d_count'  => $usersWith128,
            'total_attendances' => count($attendancePayloads),
        ];
    }

    /**
     * Add public storage files to the ZIP archive.
     *
     * @return array<string, mixed>
     */
    private function archiveStorageFiles(ZipArchive $zip, string $storagePath): array
    {
        if (!File::exists($storagePath)) {
            return ['file_count' => 0, 'size_bytes' => 0];
        }

        $files = File::allFiles($storagePath);
        $excludePatterns = config('backup.exclude_storage_paths', []);
        $fileCount = 0;
        $totalBytes = 0;

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();

            // Check exclusion
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                if (str_contains($relativePath, $pattern)) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            $zip->addFile($file->getPathname(), 'files/' . str_replace('\\', '/', $relativePath));
            $fileCount++;
            $totalBytes += $file->getSize();
        }

        return [
            'file_count' => $fileCount,
            'size_bytes' => $totalBytes,
            'formatted_size' => $this->backupRepo->formatBytes($totalBytes),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getDatabaseTables(): array
    {
        $connection = config('database.default', 'pgsql');
        $driver = config("database.connections.{$connection}.driver", 'pgsql');

        if ($driver === 'sqlite') {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
            return array_map(fn ($t) => (string)$t->name, $tables);
        }

        if ($driver === 'pgsql') {
            $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';");
            return array_map(fn ($t) => (string)$t->table_name, $tables);
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $key = "Tables_in_" . config("database.connections.{$connection}.database");
            return array_map(fn ($t) => (string)($t->$key ?? array_values((array)$t)[0]), $tables);
        }

        return [];
    }

    /**
     * Send notification upon backup success.
     *
     * @param array<string, mixed> $result
     */
    private function sendBackupNotification(array $result): void
    {
        try {
            $msg = "💾 [Uni-Activity] สำรองข้อมูลระบบสำเร็จ\n"
                 . "📁 ไฟล์: {$result['filename']}\n"
                 . "🏷 ประเภท: {$result['type']}\n"
                 . "📦 ขนาด: {$result['formatted_size']}\n"
                 . "⏱ เวลาที่ใช้: {$result['duration_sec']} วินาที\n"
                 . "📅 วันที่: " . Carbon::now()->toDateTimeString();

            $this->notifyLine($msg);
        } catch (Exception $e) {
            Log::warning("Failed to send backup notification: " . $e->getMessage());
        }
    }

    /**
     * Send notification upon backup failure.
     */
    private function sendFailureNotification(string $filename, string $type, string $errorMessage): void
    {
        try {
            $msg = "⚠️ [Uni-Activity Alert] การสำรองข้อมูลล้มเหลว!\n"
                 . "📁 ไฟล์: {$filename}\n"
                 . "🏷 ประเภท: {$type}\n"
                 . "❌ ข้อผิดพลาด: {$errorMessage}\n"
                 . "📅 วันที่: " . Carbon::now()->toDateTimeString();

            $this->notifyLine($msg);

            // Log security incident
            SecurityLog::create([
                'user_id'     => auth()->id(),
                'ip_address'  => request()->ip() ?? '127.0.0.1',
                'event_type'  => 'backup_failure',
                'severity'    => 'high',
                'description' => "Automated backup failed for {$filename}: {$errorMessage}",
                'is_reviewed' => false,
            ]);
        } catch (Exception $e) {
            Log::warning("Failed to send failure notification: " . $e->getMessage());
        }
    }

    private function notifyLine(string $message): void
    {
        $lineToken = env('LINE_NOTIFY_TOKEN') ?? env('LINE_CHANNEL_ACCESS_TOKEN');
        if (empty($lineToken)) {
            return;
        }

        Http::withHeaders([
            'Authorization' => "Bearer {$lineToken}",
        ])->asForm()->post('https://notify-api.line.me/api/notify', [
            'message' => $message,
        ]);
    }
}
