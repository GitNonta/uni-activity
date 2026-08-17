<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\BackupRepository;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class RestoreBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore 
                            {file : The backup zip filename}
                            {--force : Force restore without prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely inspect and restore a backup archive';

    public function handle(BackupRepository $backupRepo): int
    {
        $filename = (string) $this->argument('file');
        $backup = $backupRepo->find($filename);

        if (!$backup) {
            $this->error("Backup file not found: {$filename}");
            return Command::FAILURE;
        }

        $this->info("📦 Backup details:");
        $this->line("  Filename: {$backup['filename']}");
        $this->line("  Type: " . strtoupper((string)$backup['type']));
        $this->line("  Size: {$backup['formatted_size']}");
        $this->line("  Created: {$backup['created_at']}");

        if (!$this->option('force')) {
            if (!$this->confirm('⚠️ Restoring a backup might overwrite existing data. Are you sure you want to proceed?', false)) {
                $this->info("Restore operation cancelled.");
                return Command::SUCCESS;
            }
        }

        $this->info("🔄 Extracting backup archive...");
        $tempDir = storage_path('app/temp/restore_' . uniqid());
        File::makeDirectory($tempDir, 0755, true, true);

        try {
            $zip = new ZipArchive();
            if ($zip->open((string)$backup['path']) !== true) {
                throw new Exception("Unable to open zip file: {$backup['path']}");
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // 1. Restore Database if present
            $sqlPath = $tempDir . DIRECTORY_SEPARATOR . 'database.sql';
            if (File::exists($sqlPath)) {
                $this->info("💾 Restoring database SQL dump...");
                $sql = File::get($sqlPath);
                DB::unprepared($sql);
                $this->info("✅ Database restored successfully.");
            }

            // 2. Restore Files if present
            $filesDir = $tempDir . DIRECTORY_SEPARATOR . 'files';
            if (File::exists($filesDir)) {
                $this->info("📁 Restoring storage files...");
                $publicStorage = storage_path('app/public');
                File::copyDirectory($filesDir, $publicStorage);
                $this->info("✅ Storage files restored successfully.");
            }

            File::deleteDirectory($tempDir);
            $this->info("🎉 Restore completed successfully!");
            return Command::SUCCESS;
        } catch (Exception $e) {
            File::deleteDirectory($tempDir);
            $this->error("❌ Restore failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
