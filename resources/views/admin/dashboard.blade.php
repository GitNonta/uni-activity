{{-- หน้า Dashboard ผู้ดูแล: สถิติภาพรวม + ตารางกิจกรรมล่าสุด + ปุ่มสร้างกิจกรรมด่วน --}}
@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="font-bold" style="font-size:1.5rem;">Dashboard</h1>
    <button onclick="document.getElementById('quickModal').classList.add('open')" class="btn btn-success btn-sm">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        บันทึกกิจกรรมด่วน
    </button>
</div>

{{-- การ์ดสถิติ: กิจกรรมทั้งหมด, เปิดรับสมัคร, นักศึกษา, ลงทะเบียน --}}
<div class="grid-4 mb-6">
    <div class="card stat-card" style="border-bottom: 3px solid #6366f1;">
        <p class="stat-label">กิจกรรมทั้งหมด</p>
        <p class="stat-value">{{ $stats['totalActivities'] }}</p>
    </div>
    <div class="card stat-card" style="border-bottom: 3px solid #8b5cf6;">
        <p class="stat-label">เปิดรับสมัคร</p>
        <p class="stat-value primary">{{ $stats['upcomingActivities'] }}</p>
    </div>
    <div class="card stat-card" style="border-bottom: 3px solid #3b82f6;">
        <p class="stat-label">นักศึกษา</p>
        <p class="stat-value">{{ $stats['totalStudents'] }}</p>
    </div>
    <div class="card stat-card" style="border-bottom: 3px solid #10b981;">
        <p class="stat-label">ลงทะเบียนทั้งหมด</p>
        <p class="stat-value success">{{ $stats['totalRegistrations'] }}</p>
    </div>
</div>

{{-- การ์ดแจ้งเตือน --}}
<div class="grid-3 mb-6">
    <div class="card" style="border-left:4px solid #f59e0b;background:#fffbeb;">
        <div class="card-body">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semi" style="color:#92400e;">รอการอนุมัติ</p>
                <svg class="icon-sm" style="color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="font-bold" style="font-size:1.5rem;color:#92400e;">{{ $stats['pendingRegistrations'] }}</p>
            <p class="text-xs" style="color:#92400e;margin-top:.25rem;">การลงทะเบียนรออนุมัติ</p>
        </div>
    </div>
    <div class="card" style="border-left:4px solid #d97706;background:#fef3c7;">
        <div class="card-body">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semi" style="color:#92400e;">เช็คอินรออนุมัติ</p>
                <svg class="icon-sm" style="color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <p class="font-bold" style="font-size:1.5rem;color:#92400e;">{{ $stats['pendingAttendances'] }}</p>
            <p class="text-xs" style="color:#92400e;margin-top:.25rem;">การเข้าร่วมรออนุมัติ</p>
        </div>
    </div>
    <div class="card" style="border-left:4px solid #3b82f6;background:#eff6ff;">
        <div class="card-body">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semi" style="color:#1e40af;">กิจกรรมใกล้จะถึง</p>
                <svg class="icon-sm" style="color:#3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <p class="font-bold" style="font-size:1.5rem;color:#1e40af;">{{ $stats['upcomingThisWeek'] }}</p>
            <p class="text-xs" style="color:#1e40af;margin-top:.25rem;">กิจกรรมในสัปดาห์นี้</p>
        </div>
    </div>
</div>

{{-- กิจกรรมที่ต้องดำเนินการ --}}
@if($stats['pendingRegistrations'] > 0 || $stats['pendingAttendances'] > 0)
<div class="card mb-4" style="border-left:4px solid #ef4444;background:#fef2f2;">
    <div class="card-body">
        <div class="flex items-center gap-2 mb-3">
            <svg class="icon-sm" style="color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <h3 class="font-semi" style="color:#dc2626;">ต้องดำเนินการ</h3>
        </div>
        <div class="flex gap-4" style="flex-wrap:wrap;">
            @if($stats['pendingRegistrations'] > 0)
            <a href="{{ route('admin.activities.index') }}" class="text-sm" style="color:#dc2626;">
                → มีการลงทะเบียน <strong>{{ $stats['pendingRegistrations'] }}</strong> รายการรออนุมัติ
            </a>
            @endif
            @if($stats['pendingAttendances'] > 0)
            <a href="{{ route('admin.activities.index') }}" class="text-sm" style="color:#dc2626;">
                → มีการเช็คอิน <strong>{{ $stats['pendingAttendances'] }}</strong> รายการรออนุมัติ
            </a>
            @endif
        </div>
    </div>
</div>
@endif

<div class="flex items-center justify-between mb-2">
    <h2 class="font-bold">กิจกรรมล่าสุด</h2>
    <a href="{{ route('admin.activities.create') }}" class="btn btn-primary btn-sm">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        สร้างใหม่
    </a>
</div>

{{-- ตารางกิจกรรมล่าสุด --}}
<div class="card">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>ชื่อกิจกรรม</th>
                    <th class="text-center">วันที่</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-center">ผู้เข้าร่วม</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentActivities as $act)
                <tr>
                    <td data-label="ชื่อกิจกรรม" class="font-semi">{{ $act->title }}</td>
                    <td data-label="วันที่" class="text-center text-muted">{{ $act->activity_date->format('d/m/Y') }}</td>
                    <td data-label="สถานะ" class="text-center">@include('components.status-badge', ['status' => $act->computed_status])</td>
                    <td data-label="ผู้เข้าร่วม" class="text-center">
                        @php
                            $regCount = $act->registrations()->where('status', 'approved')->count();
                            $attCount = $act->attendances()->where('status', 'approved')->count();
                        @endphp
                        <span class="text-sm text-muted">{{ $regCount }}/{{ $act->max_participants }}</span>
                        @if($attCount > 0)
                            <span class="text-xs" style="color:#16a34a;"> (✓{{ $attCount }})</span>
                        @endif
                    </td>
                    <td data-label="จัดการ" class="text-right">
                        <div class="flex justify-end gap-2" style="justify-content:flex-end;">
                            <a href="{{ route('admin.activities.show', $act->id) }}" class="btn btn-outline btn-sm">ดู</a>
                            <a href="{{ route('admin.activities.edit', $act->id) }}" class="btn btn-outline btn-sm">แก้ไข</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">ยังไม่มีกิจกรรม</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{-- Modal สร้างกิจกรรมด่วน: กรอกแค่ชื่อ สถานที่ วันที่ เวลา ชั่วโมง หมวดหมู่ (ค่าอื่นใช้ค่าเริ่มต้น) --}}
<div id="quickModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal">
        <div class="modal-header">
            <h2>
                <svg class="icon-sm" style="display:inline;color:#16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                บันทึกกิจกรรมด่วน
            </h2>
            <button class="modal-close" onclick="document.getElementById('quickModal').classList.remove('open')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.activities.quick-store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">ชื่อกิจกรรม</label>
                    <input type="text" name="title" class="form-control" placeholder="เช่น ประชุมชมรม, อบรม Excel" required autofocus>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">สถานที่</label>
                        <input type="text" name="location" class="form-control" placeholder="เช่น ห้อง 101" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">หมวดหมู่</label>
                        <select name="category_id" class="form-control" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">วันที่</label>
                        <input type="date" name="activity_date" class="form-control" value="{{ now()->addDays(3)->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชั่วโมง</label>
                        <input type="number" name="activity_hours" class="form-control" value="2" step="0.5" min="0.5" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">เวลาเริ่ม</label>
                        <input type="time" name="start_time" class="form-control" value="09:00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">เวลาสิ้นสุด</label>
                        <input type="time" name="end_time" class="form-control" value="12:00" required>
                    </div>
                </div>
                <p class="text-xs text-muted mb-4">* ค่าเริ่มต้น: รับสมัคร 50 คน, เปิดรับสมัครทันที, เช็คอินก่อนเริ่ม 30 นาที</p>
                <div class="flex gap-2" style="justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('quickModal').classList.remove('open')">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">
                        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
