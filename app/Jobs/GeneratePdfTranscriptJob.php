<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\ActivitySummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePdfTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public readonly int $userId,
        public readonly ?string $targetDisk = 'local',
        public readonly ?string $targetDirectory = 'transcripts'
    ) {
        $this->onQueue('exports');
    }

    public function handle(ActivitySummaryService $summaryService): string
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::warning("GeneratePdfTranscriptJob: User #{$this->userId} not found.");
            return '';
        }

        $pdf = $summaryService->generateTranscriptPdf($user);
        $filename = 'transcript_' . ($user->student_id ?: $user->id) . '_' . now()->format('YmdHis') . '.pdf';
        $relativeFilePath = rtrim($this->targetDirectory ?? 'transcripts', '/') . '/' . $filename;

        Storage::disk($this->targetDisk ?? 'local')->put($relativeFilePath, $pdf->output());

        Log::info("GeneratePdfTranscriptJob: Successfully compiled transcript for User #{$user->id} at {$relativeFilePath}");

        return $relativeFilePath;
    }

    public function failed(Throwable $exception): void
    {
        Log::error("GeneratePdfTranscriptJob failed permanently for User #{$this->userId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
