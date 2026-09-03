<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Test endpoint: Compare uploaded image against user 6710886217's face descriptor
 * POST /api/face/test-compare
 * Body: multipart/form-data { image: file }
 * Header: X-API-Key: <ai_server_key>
 */
class TestFaceController extends Controller
{
    private string $aiServerUrl;
    private string $aiServerKey;

    public function __construct()
    {
        $this->aiServerUrl = (string) config('services.ai_server.url');
        $this->aiServerKey = (string) config('services.ai_server.key');
    }

    /**
     * Compare uploaded image against user 6710886217 (student_id)
     *
     * @POST /api/face/test-compare
     *
     * @bodyParam image file required The face image to compare (jpg/png/webp). Example: face.jpg
     * @bodyParam liveness bool optional Check liveness (default: false for testing)
     */
    public function compare(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        // 1. Validate
        $request->validate([
            'image'     => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'liveness'  => 'sometimes|boolean',
        ]);

        // 2. Get target user (6710886217)
        $targetUser = User::where('student_id', '6710886217')->first();
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'error'   => 'User 6710886217 not found',
            ], 404);
        }

        if (empty($targetUser->face_descriptor)) {
            return response()->json([
                'success' => false,
                'error'   => 'User 6710886217 has no registered face descriptor',
                'user'    => [
                    'id'          => $targetUser->id,
                    'student_id'  => $targetUser->student_id,
                    'full_name'   => $targetUser->full_name,
                ],
            ], 422);
        }

        // 3. Read uploaded image
        $imageFile = $request->file('image');
        if (!$imageFile || !$imageFile->isValid()) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid image file',
            ], 400);
        }

        $imageContents = file_get_contents($imageFile->getRealPath());
        $imageSize = $imageFile->getSize();
        $imageMime = $imageFile->getMimeType();

        Log::info('Test face compare: received image', [
            'size'       => $imageSize,
            'mime'       => $imageMime,
            'target_user'=> $targetUser->student_id,
        ]);

        // 4. Send to AI server for verification
        $checkLiveness = $request->boolean('liveness', false);

        try {
            $response = Http::timeout(30)
                ->withHeaders(['X-API-Key' => $this->aiServerKey])
                ->attach('image', $imageContents, 'test_face.' . ($imageFile->getClientOriginalExtension() ?: 'jpg'))
                ->post(rtrim($this->aiServerUrl, '/') . '/verify', [
                    'known_embedding' => json_encode($targetUser->face_descriptor),
                    'check_liveness'  => $checkLiveness ? 'true' : 'false',
                ]);

            if (!$response->successful()) {
                Log::error('AI server error', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json([
                    'success' => false,
                    'error'   => 'AI server returned HTTP ' . $response->status(),
                    'body'    => $response->body(),
                ], 502);
            }

            $aiResult = $response->json();
            $processingMs = (int) round((microtime(true) - $startTime) * 1000);

            // 5. Build response
            $result = [
                'success'          => true,
                'is_match'         => (bool) ($aiResult['is_match'] ?? false),
                'face_match'       => (bool) ($aiResult['face_match'] ?? false),
                'similarity'       => (float) ($aiResult['similarity'] ?? 0.0),
                'score_percentage' => (float) ($aiResult['score_percentage'] ?? 0.0),
                'threshold'        => 65.0,
                'processing_ms'    => $processingMs,
                'server_ms'        => (int) ($aiResult['processing_ms'] ?? 0),
                'detector_used'    => $aiResult['detector_used'] ?? 'unknown',
                'message'          => $aiResult['message'] ?? '',
                'target_user'      => [
                    'id'         => $targetUser->id,
                    'student_id' => $targetUser->student_id,
                    'full_name'  => $targetUser->full_name,
                ],
                'image_info'       => [
                    'size_bytes' => $imageSize,
                    'mime_type'  => $imageMime,
                ],
            ];

            // Add liveness info if checked
            if ($checkLiveness) {
                $result['liveness'] = [
                    'passed'  => (bool) ($aiResult['liveness_passed'] ?? true),
                    'score'   => (float) ($aiResult['liveness_score'] ?? 0.0),
                    'checks'  => $aiResult['liveness_checks'] ?? [],
                ];
            }

            Log::info('Test face compare result', [
                'match'      => $result['is_match'],
                'similarity' => $result['score_percentage'],
                'user_id'    => $targetUser->id,
                'ms'         => $processingMs,
            ]);

            return response()->json($result, $result['is_match'] ? 200 : 200);

        } catch (\Throwable $e) {
            Log::error('Test face compare exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error'   => 'Failed to connect to AI server: ' . $e->getMessage(),
            ], 503);
        }
    }
}
