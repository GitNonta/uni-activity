<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class CleanBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:clean 
                            {--keep-days= : Number of days to keep backups (defaults to config)}
                            {--keep-count= : Minimum number of backups to always keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old backups according to retention policy';

    public function handle(BackupService $backupService): int
    {
        $keepDays = $this->option('keep-days') !== null ? (int) $this->option('keep-days') : null;
        $keepCount = $this->option('keep-count') !== null ? (int) $this->option('keep-count') : null;

        $this->info("🧹 Cleaning up old backups...");

        $deleted = $backupService->cleanOldBackups($keepDays, $keepCount);

        if (empty($deleted)) {
            $this->info("✨ No expired backups to clean.");
        } else {
            $this->info("✅ Successfully deleted " . count($deleted) . " old backup(s):");
            foreach ($deleted as $file) {
                $this->line("  - {$file}");
            }
        }

        return Command::SUCCESS;
    }
}
