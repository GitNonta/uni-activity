{{-- หน้า Dashboard ผู้ดูแล: สถิติ + Unified Approval Queue + Quick Actions --}}
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

{{-- การ์ดสถิติ --}}
<div class="grid-4 mb-6">
    <div class="card stat-card" style="border-bottom:3px solid #6366f1;">
        <p class="stat-label">กิจกรรมทั้งหมด</p>
        <p class="stat-value">{{ $stats['totalActivities'] }}</p>
    </div>
    <div class="card stat-card" style="border-bottom:3px solid #8b5cf6;">
        <p class="stat-label">เปิดรับสมัคร</p>
        <p class="stat-value primary">{{ $stats['upcomingActivities'] }}</p>
    </div>
    <div class="card stat-card" style="border-bottom:3px solid #3b82f6;">
        <p class="stat-label">นักศึกษา</p>
        <p class="stat-value">{{ $stats['totalStudents'] }}</p>
    </div>
    <div class="card stat-card" style="border-bottom:3px solid #f59e0b; position:relative;">
        <p class="stat-label">รออนุมัติ</p>
        <p class="stat-value" style="color:#d97706;" id="pending-badge-count">{{ $stats['pendingRegistrations'] + $stats['pendingAttendances'] }}</p>
        <p class="text-xs text-muted">ลงทะเบียน {{ $stats['pendingRegistrations'] }} · เช็คอิน {{ $stats['pendingAttendances'] }}</p>
    </div>
</div>

{{-- การ์ดสถิติเพิ่มเติม (งาน / แชท / ประเมิน) --}}
<div class="grid-4 mb-6">
    <a href="{{ route('admin.jobs.index') }}" class="card stat-card hover-lift" style="border-bottom:3px solid #10b981; text-decoration:none;">
        <p class="stat-label">งานทั้งหมด</p>
        <p class="stat-value" style="color:#059669;">{{ $stats['totalJobs'] }}</p>
    </a>
    <a href="{{ route('admin.inbox.index') }}" class="card stat-card hover-lift" style="border-bottom:3px solid #f43f5e; text-decoration:none;">
        <p class="stat-label">ข้อความใหม่</p>
        <p class="stat-value" style="color:#e11d48;">{{ $stats['unreadMessages'] }}</p>
    </a>
    <a href="{{ route('admin.feedbacks.index') }}" class="card stat-card hover-lift" style="border-bottom:3px solid #0ea5e9; text-decoration:none;">
        <p class="stat-label">ผลการประเมิน</p>
        <p class="stat-value" style="color:#0284c7;">{{ $stats['totalFeedbacks'] }}</p>
    </a>
    <div class="card stat-card" style="border-bottom:3px solid #64748b;">
        <p class="stat-label">กิจกรรมสัปดาห์นี้</p>
        <p class="stat-value" style="color:#475569;">{{ $stats['upcomingThisWeek'] }}</p>
    </div>
</div>

{{-- Unified Approval Queue --}}
@php
    $allPending = collect();
    foreach($pendingRegistrations as $reg) {
        $allPending->push([
            'id'       => $reg->id,
            'type'     => 'registration',
            'name'     => $reg->user->full_name ?? '-',
            'sid'      => $reg->user->student_id ?? '',
            'faculty'  => $reg->user->faculty ?? '',
            'activity' => $reg->activity->title ?? '-',
            'time'     => $reg->created_at,
            'detail'   => 'ขอลงทะเบียนเข้าร่วม',
        ]);
    }
    foreach($pendingAttendances as $att) {
        $allPending->push([
            'id'       => $att->id,
            'type'     => 'attendance',
            'name'     => $att->user->full_name ?? '-',
            'sid'      => $att->user->student_id ?? '',
            'faculty'  => $att->user->faculty ?? '',
            'activity' => $att->activity->title ?? '-',
            'time'     => $att->created_at,
            'detail'   => $att->distance_meters ? 'เช็คอิน GPS ห่าง '.number_format($att->distance_meters,0).' ม.' : 'บันทึกด้วยตนเอง',
        ]);
    }
    $allPending = $allPending->sortByDesc('time');
    $totalPending = $stats['pendingRegistrations'] + $stats['pendingAttendances'];
@endphp

@if($totalPending > 0)
<div class="card mb-6">
    <div class="card-header" style="background:linear-gradient(135deg,#fff7ed,#fffbeb);border-bottom:2px solid #fbbf24;">
        <div class="flex items-center gap-2">
            <svg style="width:20px;height:20px;color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="card-title" style="color:#92400e;">
                รออนุมัติทั้งหมด
                <span id="queue-count" style="background:#f59e0b;color:#fff;border-radius:999px;padding:2px 10px;font-size:.8rem;margin-left:6px;">{{ $totalPending }}</span>
            </h3>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.activities.index') }}" class="btn btn-outline btn-sm">ดูทั้งหมด</a>
        </div>
    </div>
    <div id="approval-queue" style="max-height:480px;overflow-y:auto;">
        @foreach($allPending as $item)
        <div class="approval-row" id="row-{{ $item['type'] }}-{{ $item['id'] }}"
             style="display:flex;align-items:center;gap:.75rem;padding:.85rem 1.1rem;border-bottom:1px solid #fef3c7;transition:background .2s;">
            {{-- Type Badge --}}
            <span style="flex-shrink:0;font-size:.7rem;font-weight:600;padding:3px 8px;border-radius:999px;
                {{ $item['type'] === 'registration' ? 'background:#ede9fe;color:#6d28d9;' : 'background:#dcfce7;color:#15803d;' }}">
                {{ $item['type'] === 'registration' ? 'ลงทะเบียน' : 'เช็คอิน' }}
            </span>
            {{-- Info --}}
            <div style="flex:1;min-width:0;">
                <div class="font-semi text-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $item['name'] }}
                    <span class="text-xs text-muted" style="font-weight:400;"> · {{ $item['sid'] }}</span>
                    @if($item['faculty']) <span class="text-xs text-muted"> · {{ $item['faculty'] }}</span> @endif
                </div>
                <div class="text-xs text-muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    📌 {{ $item['activity'] }} &nbsp;·&nbsp; {{ $item['detail'] }} &nbsp;·&nbsp; {{ $item['time']->diffForHumans() }}
                </div>
            </div>
            {{-- Actions --}}
            <div class="flex gap-1" style="flex-shrink:0;">
                <button class="btn btn-success btn-sm"
                    onclick="quickAction('approve','{{ $item['type'] }}',{{ $item['id'] }},this)"
                    title="อนุมัติ">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    อนุมัติ
                </button>
                <button class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#fca5a5;"
                    onclick="quickAction('reject','{{ $item['type'] }}',{{ $item['id'] }},this)"
                    title="ปฏิเสธ">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    ปฏิเสธ
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @if($totalPending > 8)
    <div style="padding:.75rem 1.1rem;text-align:center;background:#fffbeb;">
        <span class="text-xs text-muted">แสดง 8 รายการล่าสุด — <a href="{{ route('admin.activities.index') }}" style="color:#d97706;">ดูทั้งหมด {{ $totalPending }} รายการ</a></span>
    </div>
    @endif
</div>
{{-- Toast notification --}}
<div id="toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1e293b;color:#fff;padding:.75rem 1.25rem;border-radius:10px;
    font-size:.875rem;box-shadow:0 4px 24px rgba(0,0,0,.2);transition:opacity .3s;"></div>
@else
<div class="card mb-6" style="border-left:4px solid #10b981;background:#f0fdf4;">
    <div class="card-body">
        <div class="flex items-center gap-2">
            <svg style="width:20px;height:20px;color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="font-semi" style="color:#15803d;">ไม่มีรายการรออนุมัติ ✅</p>
        </div>
    </div>
</div>
@endif

{{-- ประวัติการทำงานล่าสุด --}}
@if(auth()->user()->isAdmin())
<div class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold flex items-center gap-2">
            <svg style="width:20px;height:20px;color:#6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2-2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01m-.01 4h.01"/></svg>
            ประวัติการทำงานล่าสุด (Audit Logs)
        </h2>
        <a href="{{ route('admin.audit-logs.index') }}" class="text-sm text-indigo-600 hover:underline">ดูประวัติทั้งหมด →</a>
    </div>
    <div class="card p-0 overflow-hidden">
        <div class="space-y-0">
            @forelse($recentAuditLogs ?? [] as $log)
                <div class="flex items-center gap-4 p-4 border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-sm font-bold text-gray-900">{{ $log->user->full_name ?? 'System' }}</span>
                            <span class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-gray-600">{{ $log->description }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge {{ $log->action_color }}" style="font-size:11px;">{{ $log->action_label }}</span>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-400">ไม่มีประวัติการทำงานล่าสุด</div>
            @endforelse
        </div>
    </div>
</div>
@endif

{{-- กิจกรรมล่าสุด --}}
<div class="flex items-center justify-between mb-2">
    <h2 class="font-bold">กิจกรรมล่าสุด</h2>
    <a href="{{ route('admin.activities.create') }}" class="btn btn-primary btn-sm">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        สร้างใหม่
    </a>
</div>
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
                            $regCount = $act->registrations()->where('status','approved')->count();
                            $attCount = $act->attendances()->where('status','approved')->count();
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

{{-- Modal สร้างกิจกรรมด่วน --}}
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
                <p class="text-xs text-muted mb-4">* ค่าเริ่มต้น: รับสมัคร 50 คน, เปิดรับสมัครทันที</p>
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

{{-- AJAX Script --}}
<script>
const CSRF = '{{ csrf_token() }}';
const APPROVE_URL = '{{ route("admin.quick.approve") }}';
const REJECT_URL  = '{{ route("admin.quick.reject") }}';

function showToast(msg, ok) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.style.background = ok ? '#15803d' : '#dc2626';
    t.style.display = 'block';
    t.style.opacity = '1';
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.style.display='none', 300); }, 2800);
}

function updateBadges(count) {
    const el = document.getElementById('pending-badge-count');
    const qc = document.getElementById('queue-count');
    if (el) el.textContent = count;
    if (qc) qc.textContent = count;
}

async function quickAction(action, type, id, btn) {
    const row = document.getElementById(`row-${type}-${id}`);
    const url = action === 'approve' ? APPROVE_URL : REJECT_URL;
    btn.disabled = true;
    row.style.opacity = '.5';
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ type, id })
        });
        const data = await res.json();
        if (data.ok) {
            row.style.transition = 'all .3s';
            row.style.maxHeight = row.offsetHeight + 'px';
            row.style.overflow = 'hidden';
            requestAnimationFrame(() => {
                row.style.maxHeight = '0';
                row.style.padding = '0';
                row.style.opacity = '0';
            });
            setTimeout(() => row.remove(), 320);
            updateBadges(data.pending_count);
            showToast(data.message, true);
            // ซ่อน section ถ้าหมดแล้ว
            if (data.pending_count === 0) {
                setTimeout(() => location.reload(), 600);
            }
        } else {
            showToast('เกิดข้อผิดพลาด', false);
            row.style.opacity = '1';
            btn.disabled = false;
        }
    } catch (e) {
        showToast('ไม่สามารถเชื่อมต่อได้', false);
        row.style.opacity = '1';
        btn.disabled = false;
    }
}
</script>
@endsection
