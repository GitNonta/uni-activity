<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\UserLocationUpdated;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Activity;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MapController extends Controller
{
    /**
     * Display the Unified Interactive Explorer Map page.
     */
    public function index(Request $request): View
    {
        return view('map.index');
    }

    /**
     * Broadcast live user location via WebSocket (Reverb / Octane).
     */
    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->validated();
        $avatar = $user->profile_photo_path ? Storage::url($user->profile_photo_path) : null;

        broadcast(new UserLocationUpdated(
            userId: (int) $user->id,
            userName: (string) ($user->full_name ?? $user->name ?? 'User'),
            userRole: (string) ($user->role ?? 'student'),
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            heading: isset($data['heading']) ? (float) $data['heading'] : null,
            speed: isset($data['speed']) ? (float) $data['speed'] : null,
            accuracy: isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            avatar: $avatar,
            timestamp: time(),
        ))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Location updated and broadcasted successfully',
        ]);
    }

    /**
     * API endpoint returning structured locations for activities, jobs, and campus landmarks.
     */
    public function locationsApi(Request $request): JsonResponse
    {
        // 1. Fetch activities with valid coordinates
        $activities = Activity::query()
            ->with('category')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(function (Activity $act): array {
                $img = null;
                $rawImg = $act->image_path ?? null;
                if ($rawImg) {
                    $img = str_starts_with($rawImg, 'http')
                        ? $rawImg
                        : asset('storage/' . ltrim($rawImg, '/'));
                }

                $timeText = $act->activity_date ? $act->activity_date->format('d/m/Y') . ($act->start_time ? ' (' . substr((string) $act->start_time, 0, 5) . ($act->end_time ? ' - ' . substr((string) $act->end_time, 0, 5) : '') . ' น.)' : '') : null;
                $quotaText = $act->max_participants ? $act->max_participants . ' คน' : 'ไม่จำกัดจำนวน';

                return [
                    'id' => $act->id,
                    'type' => 'activity',
                    'title' => $act->title,
                    'subtitle' => $act->category->name ?? 'กิจกรรมทั่วไป',
                    'location_name' => $act->location ?? 'มหาวิทยาลัย',
                    'lat' => (float) $act->latitude,
                    'lng' => (float) $act->longitude,
                    'image' => $img,
                    'description' => $act->description ?? 'ไม่มีรายละเอียดเพิ่มเติม',
                    'time_text' => $timeText,
                    'quota_text' => $quotaText,
                    'badge' => $act->is_mandatory ? 'กิจกรรมบังคับ' : 'กิจกรรม',
                    'badge_class' => $act->is_mandatory ? 'badge-red' : 'badge-orange',
                    'status' => $act->computed_status ?? $act->status,
                    'meta_info' => $act->activity_hours . ' ชม. | ' . ($act->activity_date ? $act->activity_date->format('d/m/Y') : ''),
                    'detail_url' => route('activities.show', $act->id),
                    'detail_button_text' => 'ดูรายละเอียดกิจกรรมเต็ม',
                    'checkin_radius' => (int) ($act->checkin_radius ?? 100),
                ];
            });

        // 2. Fetch jobs with valid coordinates
        $jobs = JobListing::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(function (JobListing $job): array {
                $img = null;
                $rawImg = $job->image_path ?? $job->poster_image ?? null;
                if ($rawImg) {
                    $img = str_starts_with($rawImg, 'http')
                        ? $rawImg
                        : asset('storage/' . ltrim($rawImg, '/'));
                }

                $timeText = $job->work_period ?? ($job->start_date ? 'เริ่ม ' . $job->start_date->format('d/m/Y') . ($job->end_date ? ' ถึง ' . $job->end_date->format('d/m/Y') : '') : null);
                $quotaText = $job->quota ? $job->quota . ' อัตรา' : 'รับหลายอัตรา';

                return [
                    'id' => $job->id,
                    'type' => 'job',
                    'title' => $job->title,
                    'subtitle' => $job->position ?? ($job->job_type === 'parttime' ? 'Part-time' : 'งานทั่วไป'),
                    'location_name' => $job->location ?? 'สถานที่ทำงาน',
                    'lat' => (float) $job->latitude,
                    'lng' => (float) $job->longitude,
                    'image' => $img,
                    'description' => $job->description ?? $job->note ?? 'ไม่มีรายละเอียดเพิ่มเติม',
                    'time_text' => $timeText,
                    'quota_text' => $quotaText,
                    'badge' => $job->job_type === 'parttime' ? 'Part-time' : 'งานทั่วไป',
                    'badge_class' => 'badge-blue',
                    'status' => $job->status,
                    'meta_info' => ($job->compensation ? $job->compensation . ' | ' : '') . ($job->start_date ? 'เริ่ม ' . $job->start_date->format('d/m/Y') : ''),
                    'detail_url' => route('jobs.show', $job->id),
                    'detail_button_text' => 'ดูรายละเอียดงานเต็ม',
                    'checkin_radius' => 150,
                ];
            });

        // 3. Campus Landmarks & Building POIs
        $landmarks = collect([
            [
                'id' => 'landmark-1',
                'type' => 'landmark',
                'title' => 'อาคารกิจกรรมนักศึกษาและนันทนาการ (SAC)',
                'subtitle' => 'ศูนย์รวมกิจกรรม กีฬา และองค์กรนักศึกษา',
                'location_name' => 'Student Activity Center',
                'lat' => 16.4745,
                'lng' => 102.8235,
                'image' => null,
                'badge' => 'จุดสำคัญ',
                'badge_class' => 'badge-green',
                'status' => 'open',
                'meta_info' => 'เปิด 08:00 - 20:00 น.',
                'detail_url' => null,
                'checkin_radius' => 200,
            ],
            [
                'id' => 'landmark-2',
                'type' => 'landmark',
                'title' => 'หอประชุมใหญ่และศูนย์การประชุม',
                'subtitle' => 'สถานที่จัดพิธีการและกิจกรรมปฐมนิเทศ',
                'location_name' => 'Main Auditorium & Convention Hall',
                'lat' => 16.4760,
                'lng' => 102.8250,
                'image' => null,
                'badge' => 'หอประชุม',
                'badge_class' => 'badge-green',
                'status' => 'open',
                'meta_info' => 'ความจุ 3,000 ที่นั่ง',
                'detail_url' => null,
                'checkin_radius' => 200,
            ],
            [
                'id' => 'landmark-3',
                'type' => 'landmark',
                'title' => 'สำนักวิทยบริการและหอสมุดกลาง',
                'subtitle' => 'ศูนย์การเรียนรู้ ห้องศึกษาค้นคว้า และ Co-working Space',
                'location_name' => 'Central Library & Learning Center',
                'lat' => 16.4730,
                'lng' => 102.8220,
                'image' => null,
                'badge' => 'ห้องสมุด',
                'badge_class' => 'badge-green',
                'status' => 'open',
                'meta_info' => 'เปิดบริการทุกวัน',
                'detail_url' => null,
                'checkin_radius' => 150,
            ],
            [
                'id' => 'landmark-4',
                'type' => 'landmark',
                'title' => 'ศูนย์กีฬาและโรงยิมเนเซียมกลาง',
                'subtitle' => 'สนามแข่งขันกีฬาและกิจกรรมนันทนาการ',
                'location_name' => 'University Sports Complex',
                'lat' => 16.4780,
                'lng' => 102.8210,
                'image' => null,
                'badge' => 'ศูนย์กีฬา',
                'badge_class' => 'badge-green',
                'status' => 'open',
                'meta_info' => 'สนามกีฬากลางแจ้งและในร่ม',
                'detail_url' => null,
                'checkin_radius' => 250,
            ],
            [
                'id' => 'landmark-5',
                'type' => 'landmark',
                'title' => 'อาคารเรียนรวมและศูนย์อาหารกลาง (Food Complex)',
                'subtitle' => 'ศูนย์รวมร้านอาหารและลานพบปะนักศึกษา',
                'location_name' => 'Central Food Complex',
                'lat' => 16.4720,
                'lng' => 102.8245,
                'image' => null,
                'badge' => 'ศูนย์อาหาร',
                'badge_class' => 'badge-green',
                'status' => 'open',
                'meta_info' => 'ศูนย์อาหารและบริการนักศึกษา',
                'detail_url' => null,
                'checkin_radius' => 150,
            ],
        ]);

        $allLocations = $activities->concat($jobs)->concat($landmarks)->values();

        return response()->json([
            'success' => true,
            'activities' => $activities,
            'jobs' => $jobs,
            'landmarks' => $landmarks,
            'locations' => $allLocations,
            'total_locations' => $allLocations->count(),
        ]);
    }
}
