<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BackupService;
use Exception;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run 
                            {--type=full : Type of backup (full, db, files, biometrics)}
                            {--no-notify : Do not send notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a scheduled or manual automated backup of database, files, and biometric data';

    public function handle(BackupService $backupService): int
    {
        $type = (string) $this->option('type');
        $validTypes = ['full', 'db', 'files', 'biometrics'];

        if (!in_array($type, $validTypes, true)) {
            $this->error("Invalid backup type: {$type}. Must be one of: " . implode(', ', $validTypes));
            return Command::FAILURE;
        }

        $notify = !$this->option('no-notify');

        $this->info("🚀 Starting backup [type: {$type}]...");

        try {
            $result = $backupService->runBackup($type, $notify);

            $this->newLine();
            $this->info("✅ Backup completed successfully!");
            $this->table(
                ['Key', 'Value'],
                [
                    ['Filename', $result['filename']],
                    ['Type', $result['type']],
                    ['Size', $result['formatted_size']],
                    ['Duration', $result['duration_sec'] . ' s'],
                    ['SHA256', substr((string)$result['sha256'], 0, 16) . '...'],
                    ['Path', $result['path']],
                ]
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("❌ Backup failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
