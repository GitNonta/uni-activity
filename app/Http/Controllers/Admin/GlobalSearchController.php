<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GlobalSearchRequest;
use App\Models\Activity;
use App\Models\Announcement;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    /**
     * ค้นหาแบบรวมศูนย์ข้ามทุกโมดูล (Students, Activities, Jobs, Announcements)
     */
    public function search(GlobalSearchRequest $request): JsonResponse
    {
        $q = trim((string) $request->validated('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'query'   => $q,
                'results' => [],
            ]);
        }

        $user = $request->user();
        $isStaff = $user->isStaff();
        $results = [];

        // 1. ค้นหานักศึกษา
        $students = User::where('role', 'student')
            ->where(function ($query) use ($q): void {
                $query->where('full_name', 'like', "%{$q}%")
                      ->orWhere('student_id', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('faculty', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'full_name', 'student_id', 'faculty', 'department']);

        foreach ($students as $student) {
            $results[] = [
                'type'        => 'student',
                'type_label'  => 'นักศึกษา',
                'title'       => $student->full_name . " ({$student->student_id})",
                'subtitle'    => ($student->faculty ?? '') . ' / ' . ($student->department ?? ''),
                'url'         => route('admin.students.show', $student->id),
                'badge_color' => '#3b82f6',
            ];
        }

        // 2. ค้นหากิจกรรม
        $activitiesQuery = Activity::query()
            ->where(function ($query) use ($q): void {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('location', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            });

        if ($isStaff) {
            $activitiesQuery->where(function ($sq) use ($user): void {
                $sq->where('created_by', $user->id)
                   ->orWhere(function ($f) use ($user): void {
                       if ($user->faculty) {
                           $f->where('faculty', $user->faculty);
                       }
                   });
            });
        }

        $activities = $activitiesQuery->limit(5)->get(['id', 'title', 'activity_date', 'status', 'location']);

        foreach ($activities as $activity) {
            $results[] = [
                'type'        => 'activity',
                'type_label'  => 'กิจกรรม',
                'title'       => $activity->title,
                'subtitle'    => ($activity->activity_date ? $activity->activity_date->format('d/m/Y') : 'ยังไม่ระบุวัน') . ' · ' . ($activity->location ?? ''),
                'url'         => route('admin.activities.show', $activity->id),
                'badge_color' => '#10b981',
            ];
        }

        // 3. ค้นหาตำแหน่งงาน
        $jobs = JobListing::where(function ($query) use ($q): void {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('company_name', 'like', "%{$q}%");
            })
            ->limit(4)
            ->get(['id', 'title', 'company_name', 'status']);

        foreach ($jobs as $job) {
            $results[] = [
                'type'        => 'job',
                'type_label'  => 'งาน & พาร์ทไทม์',
                'title'       => $job->title,
                'subtitle'    => $job->company_name ?? '',
                'url'         => route('admin.jobs.show', $job->id),
                'badge_color' => '#f59e0b',
            ];
        }

        // 4. ค้นหาประกาศ
        $announcements = Announcement::where(function ($query) use ($q): void {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            })
            ->limit(4)
            ->get(['id', 'title', 'type', 'created_at']);

        foreach ($announcements as $announcement) {
            $results[] = [
                'type'        => 'announcement',
                'type_label'  => 'ประกาศข่าวสาร',
                'title'       => $announcement->title,
                'subtitle'    => $announcement->created_at ? $announcement->created_at->format('d/m/Y') : '',
                'url'         => route('admin.announcements.edit', $announcement->id),
                'badge_color' => '#8b5cf6',
            ];
        }

        return response()->json([
            'query'   => $q,
            'count'   => count($results),
            'results' => $results,
        ]);
    }
}
