<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $activities = Activity::with(['category', 'registrations', 'attendances'])
            ->when($request->from, fn($q) => $q->where('activity_date', '>=', $request->from))
            ->when($request->to, fn($q) => $q->where('activity_date', '<=', $request->to))
            ->orderByDesc('activity_date')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'totalActivities' => Activity::count(),
            'totalAttendances' => Attendance::count(),
            'totalStudents' => User::where('role', 'student')->count(),
            'totalHours' => Attendance::with('activity')->get()->sum(fn($a) => (float) $a->activity->activity_hours),
        ];

        return view('admin.reports.index', compact('activities', 'summary'));
    }
}
