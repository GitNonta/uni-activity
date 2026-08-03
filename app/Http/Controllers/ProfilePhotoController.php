<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * คอนโทรลเลอร์จัดการรูปโปรไฟล์นักศึกษา
 * รองรับ: อัปโหลดรูปใหม่ / ลบรูป
 */
class ProfilePhotoController extends Controller
{
    /** อัปโหลดหรือเปลี่ยนรูปโปรไฟล์ */
    public function store(Request $request)
    {
        \Log::info("🚀 [DEBUG] ProfilePhotoController::store() called - User: " . auth()->id());
        $validated = $request->validate([
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $user = auth()->user();
        
        if (!$user) {
            return back()->withErrors(['error' => 'ไม่สามารถระบุตัวตนของผู้ใช้']);
        }

        try {
            $file = $request->file('profile_photo');
            $filename = 'profile_' . $user->id . '_' . time() . '.webp';
            $directory = 'profile-photos';
            $fullPath = storage_path('app/public/' . $directory . '/' . $filename);

            // กำหนด imageInfo ก่อนเพื่อป้องกัน undefined variable
            $imageInfo = getimagesize($file->getRealPath());
            if (!$imageInfo) {
                return back()->withErrors(['error' => 'ไฟล์รูปภาพไม่ถูกต้องหรือเสียหาย']);
            }

            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!file_exists(storage_path('app/public/' . $directory))) {
                mkdir(storage_path('app/public/' . $directory), 0775, true);
            }

            $updateData = [
                'profile_photo' => $directory . '/' . $filename,
                // เคลียร์รหัสสกัดเก่าทั้งหมด จะสร้างใหม่จาก AI Server
                'face_descriptor' => null,
                'face_descriptor_js' => null
            ];
            
            // 🎯 ส่งรูปต้นฉบับให้ AI Server สกัด Vector (512D + 128D) ก่อนแปลงเป็น WebP
            $aiServerUrl = config('services.ai_server.url');
            if (!empty($aiServerUrl)) {
                try {
                    $originalFileSize = $file->getSize();
                    $originalDimensions = $imageInfo[0] . 'x' . $imageInfo[1];
                    
                    \Log::info("📤 [STEP 1] Starting face extraction process");
                    \Log::info("📋 [STEP 2] Image details: {$file->getClientOriginalName()} ({$originalDimensions}, " . number_format($originalFileSize/1024, 1) . "KB)");
                    \Log::info("🌐 [STEP 3] AI Server URL: {$aiServerUrl}");
                    \Log::info("⏱️  [STEP 4] Sending HTTP request to AI Server...");
                    
                    $startTime = microtime(true);
                    $response = \Illuminate\Support\Facades\Http::timeout(15)
                        ->attach('image', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                        ->post(rtrim($aiServerUrl, '/') . '/extract');
                    $responseTime = round((microtime(true) - $startTime) * 1000);
                    
                    \Log::info("📨 [STEP 5] AI Server response received in {$responseTime}ms");
                    \Log::info("📊 [STEP 6] HTTP Status: {$response->status()}");
                        
                    if ($response->successful()) {
                        $aiResult = $response->json();
                        \Log::info("📋 [STEP 7] Response keys: " . implode(', ', array_keys($aiResult)));
                        
                        $extracted = [];
                        
                        // สกัด 512D encoding (รูปแบบใหม่)
                        if (!empty($aiResult['embedding_512d'])) {
                            $updateData['face_descriptor'] = $aiResult['embedding_512d'];
                            $extracted[] = '512D';
                            \Log::info("✅ [STEP 8a] 512D face encoding extracted successfully (" . count($aiResult['embedding_512d']) . " dimensions)");
                        } else {
                            \Log::warning("⚠️  [STEP 8a] 512D face encoding NOT found in response");
                        }
                        
                        // สกัด 128D encoding (รูปแบบใหม่)
                        if (!empty($aiResult['embedding_128d'])) {
                            $updateData['face_descriptor_js'] = $aiResult['embedding_128d'];
                            $extracted[] = '128D';
                            \Log::info("✅ [STEP 8b] 128D face encoding extracted successfully (" . count($aiResult['embedding_128d']) . " dimensions)");
                        } else {
                            \Log::warning("⚠️  [STEP 8b] 128D face encoding NOT found in response");
                        }
                        
                        // รองรับ API เก่าที่ส่ง 'embedding' มา (512D)
                        if (empty($updateData['face_descriptor']) && !empty($aiResult['embedding'])) {
                            $updateData['face_descriptor'] = $aiResult['embedding'];
                            $extracted[] = '512D (legacy)';
                            \Log::info("✅ [STEP 9a] 512D face encoding extracted from legacy format (" . count($aiResult['embedding']) . " dimensions)");
                            
                            // สร้าง 128D จาก 512D โดยการตัดทอน (fallback)
                            if (empty($updateData['face_descriptor_js'])) {
                                $embedding512 = $aiResult['embedding'];
                                if (is_array($embedding512) && count($embedding512) >= 128) {
                                    $updateData['face_descriptor_js'] = array_slice($embedding512, 0, 128);
                                    $extracted[] = '128D (truncated)';
                                    \Log::info("✅ [STEP 9b] 128D face encoding created by truncating 512D (128 dimensions)");
                                } else {
                                    \Log::error("❌ [STEP 9b] Cannot create 128D: legacy embedding too small (" . count($embedding512) . " dimensions)");
                                }
                            }
                        } else if (empty($updateData['face_descriptor'])) {
                            \Log::warning("⚠️  [STEP 9] No 512D encoding available in any format");
                        }
                        
                        if (empty($extracted)) {
                            \Log::error("❌ [STEP 10] EXTRACTION FAILED: No valid embeddings extracted from AI Server response");
                            \Log::info("🔍 [DEBUG] Full AI Server response: " . json_encode($aiResult));
                        } else {
                            \Log::info("🎯 [STEP 10] EXTRACTION SUCCESS: " . implode(' + ', $extracted));
                        }
                    } else {
                        \Log::error("❌ [STEP 7] AI Server request failed with HTTP {$response->status()}");
                        \Log::error("📝 [ERROR] Response body: " . $response->body());
                    }
                } catch (\Exception $e) {
                    \Log::error("❌ [STEP ERROR] AI Server extraction exception: " . $e->getMessage());
                    \Log::error("🔍 [DEBUG] Exception type: " . get_class($e));
                    if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                        \Log::error("🌐 [DEBUG] Connection failed - AI Server may not be running or unreachable");
                    }
                }
            } else {
                \Log::error("❌ [STEP 0] AI_SERVER_URL not configured in .env file");
                \Log::info("💡 [HINT] Add AI_SERVER_URL=http://your-windows-ip:8001 to .env");
            }

            // --- ประมวลผลรูปภาพ (GD) เป็น WebP สำหรับการแสดงผล ---
            $mime = $imageInfo['mime'];

            // สร้าง Image Resource ตามประเภทไฟล์ต้นฉบับ
            switch ($mime) {
                case 'image/jpeg': $src = imagecreatefromjpeg($file->getRealPath()); break;
                case 'image/png':  $src = imagecreatefrompng($file->getRealPath()); break;
                case 'image/webp': $src = imagecreatefromwebp($file->getRealPath()); break;
                default: return back()->withErrors(['error' => 'ไม่รองรับประเภทไฟล์นี้']);
            }

            // ปรับขนาด (Resize) ให้เป็นจัตุรัสและไม่เกิน 400px (เหมาะสำหรับโปรไฟล์)
            $width = imagesx($src);
            $height = imagesy($src);
            $size = min($width, $height);
            $tmp = imagecreatetruecolor(400, 400);
            
            // ทำให้พื้นหลังโปร่งใส (สำหรับ PNG/WebP)
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            
            imagecopyresampled($tmp, $src, 0, 0, ($width-$size)/2, ($height-$size)/2, 400, 400, $size, $size);

            // บันทึกเป็น WebP (Quality 80% เพื่อลดขนาด)
            imagewebp($tmp, $fullPath, 80);

            // คืนหน่วยความจำ
            imagedestroy($src);
            imagedestroy($tmp);

            // ลบรูปเก่า
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
                \Log::info("🗑️  Deleted old profile photo: {$user->profile_photo}");
            }
            
            // บันทึกข้อมูลใหม่ (รวมการเคลียร์รหัสสกัดเก่า)
            $hadOldEncodings = $user->face_descriptor || $user->face_descriptor_js;
            if ($hadOldEncodings) {
                \Log::info("🔄 Replacing old face encodings with new ones from updated photo");
            }

            $user->update($updateData); 
            
            $encodingStatus512 = isset($updateData['face_descriptor']) ? '✅ extracted' : '❌ failed';
            $encodingStatus128 = isset($updateData['face_descriptor_js']) ? '✅ extracted' : '❌ failed';
            \Log::info("Profile photo updated: {$fullPath}, 512D encoding: {$encodingStatus512}, 128D encoding: {$encodingStatus128}");
            
            $successMessage = 'อัปโหลดและปรับปรุงรูปโปรไฟล์สำเร็จ (ความละเอียดสูง → 512D + 128D + WebP แสดงผล)';
            if (!isset($updateData['face_descriptor']) || !isset($updateData['face_descriptor_js'])) {
                $successMessage .= ' [เตือน: สกัดรหัสใบหน้าไม่ครบ]';
            }
            
            return back()->with('success', $successMessage);
        } catch (\Exception $e) {
            \Log::error('Profile photo upload error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการอัปโหลด: ' . $e->getMessage()]);
        }
    }

    /** ลบรูปโปรไฟล์ */
    public function destroy()
    {
        $user = auth()->user();

        if (!$user) {
            return back()->withErrors(['error' => 'ไม่สามารถระบุตัวตนของผู้ใช้']);
        }

        try {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $user->update([
                'profile_photo' => null,
                'face_descriptor' => null,
                'face_descriptor_js' => null
            ]);

            return back()->with('success', 'ลบรูปโปรไฟล์เรียบร้อยแล้ว');
        } catch (\Exception $e) {
            \Log::error('Profile photo delete error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการลบรูป: ' . $e->getMessage()]);
        }
    }

    /** บันทึก Face Descriptor ของ JS (128-d) ที่สร้างจากเบราว์เซอร์ */
    public function saveJsDescriptor(Request $request)
    {
        $request->validate([
            'descriptor' => 'required|array',
            'descriptor.*' => 'numeric'
        ]);

        $user = auth()->user();
        if ($user) {
            $user->update(['face_descriptor_js' => $request->descriptor]);
            return response()->json(['success' => true]);
        }
        
        return response()->json(['success' => false], 401);
    }
}
