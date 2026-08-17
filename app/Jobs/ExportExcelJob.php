<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\ActivitiesExport;
use App\Exports\StatisticsExport;
use App\Exports\StudentAttendancesExport;
use App\Exports\StudentsExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'exports';
    public int $tries = 2;
    public int $backoff = 30;

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string> $fields
     */
    public function __construct(
        public readonly string $exportType,
        public readonly array $filters = [],
        public readonly array $fields = [],
        public readonly string $targetDisk = 'local',
        public readonly string $targetDirectory = 'exports'
    ) {}

    public function handle(): string
    {
        $timestamp = now()->format('Ymd_His');
        $fileName = "{$this->exportType}_{$timestamp}.xlsx";
        $filePath = rtrim($this->targetDirectory, '/') . '/' . $fileName;

        $export = match ($this->exportType) {
            'students'    => new StudentsExport($this->filters, $this->fields),
            'activities'  => new ActivitiesExport($this->filters),
            'statistics'  => new StatisticsExport(
                (string) ($this->filters['date_from'] ?? ''),
                (string) ($this->filters['date_to'] ?? '')
            ),
            'attendances' => new StudentAttendancesExport((string) ($this->filters['student_id'] ?? '')),
            default       => throw new \InvalidArgumentException("Unsupported export type: {$this->exportType}"),
        };

        Excel::store($export, $filePath, $this->targetDisk);

        Log::info("ExportExcelJob: Successfully stored {$this->exportType} export at {$filePath} on disk [{$this->targetDisk}]");

        return $filePath;
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ExportExcelJob failed permanently for type {$this->exportType}", [
            'filters' => $this->filters,
            'error'   => $exception->getMessage(),
        ]);
    }
}
