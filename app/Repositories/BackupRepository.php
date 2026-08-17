<?php

declare(strict_types=1);

namespace App\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupRepository
{
    private string $backupPath;

    public function __construct()
    {
        $this->backupPath = (string) config('backup.path', storage_path('app/backups'));
        $this->ensureDirectoryExists();
    }

    public function getBackupDirectory(): string
    {
        return $this->backupPath;
    }

    public function ensureDirectoryExists(): void
    {
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true, true);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllBackups(): array
    {
        $this->ensureDirectoryExists();
        $files = File::files($this->backupPath);

        $backups = [];
        foreach ($files as $file) {
            $filename = $file->getFilename();
            if (!str_ends_with($filename, '.zip') && !str_ends_with($filename, '.sql')) {
                continue;
            }

            $type = 'unknown';
            if (str_contains($filename, '-full')) {
                $type = 'full';
            } elseif (str_contains($filename, '-db')) {
                $type = 'db';
            } elseif (str_contains($filename, '-files')) {
                $type = 'files';
            } elseif (str_contains($filename, '-biometrics')) {
                $type = 'biometrics';
            }

            $sizeBytes = $file->getSize();
            $mtime = $file->getMTime();

            $backups[] = [
                'filename'       => $filename,
                'path'           => $file->getPathname(),
                'size_bytes'     => $sizeBytes,
                'formatted_size' => $this->formatBytes($sizeBytes),
                'type'           => $type,
                'created_at'     => Carbon::createFromTimestamp($mtime)->toDateTimeString(),
                'timestamp'      => $mtime,
                'sha256'         => hash_file('sha256', $file->getPathname()),
            ];
        }

        // Sort latest first
        usort($backups, fn (array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $filename): ?array
    {
        $safeName = basename($filename);
        $fullPath = $this->backupPath . DIRECTORY_SEPARATOR . $safeName;

        if (!File::exists($fullPath)) {
            return null;
        }

        $type = 'unknown';
        if (str_contains($safeName, '-full')) {
            $type = 'full';
        } elseif (str_contains($safeName, '-db')) {
            $type = 'db';
        } elseif (str_contains($safeName, '-files')) {
            $type = 'files';
        } elseif (str_contains($safeName, '-biometrics')) {
            $type = 'biometrics';
        }

        $sizeBytes = File::size($fullPath);
        $mtime = File::lastModified($fullPath);

        $manifest = null;
        if (str_ends_with($safeName, '.zip')) {
            $manifest = $this->readManifestFromZip($fullPath);
        }

        return [
            'filename'       => $safeName,
            'path'           => $fullPath,
            'size_bytes'     => $sizeBytes,
            'formatted_size' => $this->formatBytes($sizeBytes),
            'type'           => $type,
            'created_at'     => Carbon::createFromTimestamp($mtime)->toDateTimeString(),
            'timestamp'      => $mtime,
            'sha256'         => hash_file('sha256', $fullPath),
            'manifest'       => $manifest,
        ];
    }

    public function delete(string $filename): bool
    {
        $safeName = basename($filename);
        $fullPath = $this->backupPath . DIRECTORY_SEPARATOR . $safeName;

        if (File::exists($fullPath)) {
            return File::delete($fullPath);
        }

        return false;
    }

    public function getTotalSize(): int
    {
        $backups = $this->getAllBackups();
        $total = 0;
        foreach ($backups as $backup) {
            $total += (int) $backup['size_bytes'];
        }
        return $total;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestBackup(): ?array
    {
        $backups = $this->getAllBackups();
        return $backups[0] ?? null;
    }

    /**
     * @return array<int, string> List of deleted filenames
     */
    public function cleanOldBackups(int $keepDays, int $keepCount): array
    {
        $backups = $this->getAllBackups();
        if (count($backups) <= $keepCount) {
            return [];
        }

        $cutoffTime = Carbon::now()->subDays($keepDays)->timestamp;
        $deleted = [];

        // Skip the first $keepCount newest backups
        $candidates = array_slice($backups, $keepCount);

        foreach ($candidates as $backup) {
            if ($backup['timestamp'] < $cutoffTime) {
                if ($this->delete((string) $backup['filename'])) {
                    $deleted[] = (string) $backup['filename'];
                }
            }
        }

        return $deleted;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifestFromZip(string $zipPath): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $manifestContent = $zip->getFromName('manifest.json');
            $zip->close();

            if ($manifestContent !== false) {
                $decoded = json_decode($manifestContent, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
