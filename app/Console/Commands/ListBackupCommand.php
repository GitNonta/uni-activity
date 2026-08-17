<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\BackupRepository;
use Illuminate\Console\Command;

class ListBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available system backups and their sizes';

    public function handle(BackupRepository $backupRepo): int
    {
        $backups = $backupRepo->getAllBackups();

        if (empty($backups)) {
            $this->warn("No backups found in " . $backupRepo->getBackupDirectory());
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($backups as $b) {
            $rows[] = [
                $b['filename'],
                strtoupper((string) $b['type']),
                $b['formatted_size'],
                $b['created_at'],
                substr((string) $b['sha256'], 0, 12) . '...',
            ];
        }

        $this->info("📁 Available System Backups (" . count($backups) . " files, Total: " . $backupRepo->formatBytes($backupRepo->getTotalSize()) . "):");
        $this->table(['Filename', 'Type', 'Size', 'Created At', 'SHA256'], $rows);

        return Command::SUCCESS;
    }
}
