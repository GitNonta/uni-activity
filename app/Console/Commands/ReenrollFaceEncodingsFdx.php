<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Re-enroll every profile photo through the fdx decoder (gpu-pc node).
 *
 * fdx and the old InsightFace ArcFace embeddings share no basis
 * (cos ≈ 0.037 — see ai_service/FDX_MIGRATION.md), so after switching the
 * AI server to fdx every stored face_descriptor MUST be re-extracted.
 * This command is idempotent: it re-extracts ALL users with a profile
 * photo, overwriting stored vectors with the current fdx-space embeddings.
 *
 * Safety gates:
 *  - refuses to run unless AI /health reports models.fdx = true (prevents
 *    re-enrolling in the wrong embedding space);
 *  - on extraction failure the existing vector is left untouched
 *    (fail-safe: stale-but-working data is never replaced by nothing);
 *  - face_descriptor_js (128D) is set to null: the 128D PCA reducer is not
 *    re-fit for fdx space yet, and stale 128D data is worse than absent.
 *
 * Usage:
 *   php artisan face:reenroll-fdx --dry-run
 *   php artisan face:reenroll-fdx --limit=50
 *   php artisan face:reenroll-fdx          # full migration pass
 */
class ReenrollFaceEncodingsFdx extends Command
{
    protected $signature = 'face:reenroll-fdx
                            {--limit=0 : Max users to process (0 = all)}
                            {--dry-run : List who would be processed without calling the AI server}';

    protected $description = 'Re-extract all stored face vectors through the fdx decoder (FDX_MIGRATION.md step 2)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $aiServerUrl = rtrim((string) config('services.ai_server.url'), '/');
        $aiKey = (string) config('services.ai_server.key');

        $this->info('fdx face re-enrollment');
        $this->info('AI server: ' . $aiServerUrl);

        // ── Gate 1: the AI server must be fdx-backed, or we would write
        //    another round of incompatible vectors. ──────────────────────
        $healthReq = Http::timeout(8);
        if ($aiKey !== '') {
            $healthReq = $healthReq->withHeaders(['X-API-Key' => $aiKey]);
        }
        try {
            $health = $healthReq->get($aiServerUrl . '/health')->json();
        } catch (\Throwable $e) {
            $this->error("AI server unreachable: {$e->getMessage()}");
            return 1;
        }

        $fdxReady = (bool) ($health['models']['fdx'] ?? false);
        $embedder = (string) ($health['embedder'] ?? 'unknown');
        $node = (string) ($health['node'] ?? 'unknown');
        if (!$fdxReady) {
            $this->error("AI server is NOT fdx-backed (embedder={$embedder}). Refusing to re-enroll in the wrong space.");
            return 1;
        }
        $this->info("fdx confirmed: embedder={$embedder} node={$node} "
            . 'threshold=' . ($health['fdx']['threshold'] ?? '?'));

        // ── Targets: every user with a profile photo. Idempotent by design
        //    (there is no space tag column — rerun = refresh). ────────────
        $query = User::whereNotNull('profile_photo')->where('profile_photo', '!=', '');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users with profile photos found.');
            return 0;
        }

        $withVector = $users->whereNotNull('face_descriptor')->count();
        $this->info("Users to process: {$users->count()} ({$withVector} already have a stale-space vector)");

        if ($dryRun) {
            foreach ($users as $user) {
                $state = $user->face_descriptor ? 're-enroll' : 'first-time';
                $this->line("  [{$state}] User #{$user->id} {$user->full_name} — {$user->profile_photo}");
            }
            $this->info('DRY RUN — nothing was changed.');
            return 0;
        }

        // ── Re-extract each profile photo through /extract. ─────────────
        $ok = 0;
        $noFace = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $fullPath = storage_path('app/public/' . $user->profile_photo);
            if (!is_file($fullPath)) {
                $failed++;
                $this->line("\n  ✗ #{$user->id} photo missing on disk: {$user->profile_photo}");
                $bar->advance();
                continue;
            }

            try {
                $req = Http::timeout(30);
                if ($aiKey !== '') {
                    $req = $req->withHeaders(['X-API-Key' => $aiKey]);
                }
                $resp = $req->attach('image', file_get_contents($fullPath), basename($fullPath))
                    ->post($aiServerUrl . '/extract');

                if (!$resp->successful()) {
                    // 400 = no face detected → keep old vector, list for manual fix
                    $noFace += $resp->status() === 400 ? 1 : 0;
                    $failed += $resp->status() !== 400 ? 1 : 0;
                    $this->line("\n  ✗ #{$user->id} HTTP {$resp->status()}: " . \Illuminate\Support\Str::limit($resp->body(), 80));
                    $bar->advance();
                    continue;
                }

                $result = $resp->json();
                $embedding = $result['embedding_512d'] ?? null;
                $space = (string) ($result['embedding_space'] ?? 'unknown');
                if (!is_array($embedding) || count($embedding) !== 512 || $space !== 'fdx-w600k-mbf') {
                    $failed++;
                    $this->line("\n  ✗ #{$user->id} unexpected extract response (space={$space})");
                    $bar->advance();
                    continue;
                }

                $user->update([
                    'face_descriptor' => $embedding,          // fdx 512D (encrypted via cast)
                    'face_descriptor_js' => null,             // 128D PCA not re-fit for fdx yet
                ]);
                $ok++;
                Log::info("fdx re-enrollment: user {$user->id} vector replaced (space={$space})");
            } catch (\Throwable $e) {
                $failed++;
                $this->line("\n  ✗ #{$user->id} " . $e->getMessage());
                Log::error("fdx re-enrollment failed for user {$user->id}: " . $e->getMessage());
            }

            $bar->advance();
            usleep(200000); // 0.2 s — gpu-pc handles ~30 img/s; be gentle anyway
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('SUMMARY');
        $this->info("  re-enrolled (fdx 512D stored): {$ok}");
        $this->info("  no face detected (kept old vector): {$noFace}");
        $this->info("  other failures (kept old vector): {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
