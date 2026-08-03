<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FaceVerificationService;
use Illuminate\Http\Request;

/**
 * Optimized Face Verification API Controller
 * Balanced processing between frontend and AI server
 */
class FaceVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $user = auth()->user();
        
        // Validate request
        $request->validate([
            'image' => 'required|string',
            'mode' => 'sometimes|in:python,js,hybrid',
            'priority' => 'sometimes|in:speed,accuracy'
        ]);
        
        $mode = $request->input('mode', 'hybrid');
        $priority = $request->input('priority', 'speed');
        
        // Use centralized service
        $verificationService = app(FaceVerificationService::class);
        
        $result = $verificationService->verifyFace($user, $request->input('image'), [
            'mode' => $mode,
            'priority' => $priority
        ]);
        
        // Determine HTTP status code
        $statusCode = 200;
        if (!$result['success']) {
            if (isset($result['error_type'])) {
                switch ($result['error_type']) {
                    case 'configuration':
                    case 'server_unavailable':
                        $statusCode = 503;
                        break;
                    case 'failure_threshold':
                        $statusCode = 429;
                        break;
                    case 'server_error':
                        $statusCode = 502;
                        break;
                    default:
                        $statusCode = 400;
                        break;
                }
            } else {
                $statusCode = 400;
            }
        }
        
        return response()->json($result, $statusCode);
    }
}
    
    public function metrics()
    {
        $user = auth()->user();
        
        // Check if user is admin/staff
        if (!in_array($user->role, ['admin', 'staff'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $verificationService = app(FaceVerificationService::class);
        $metrics = $verificationService->getMetrics();
        
        return response()->json($metrics);
    }
}