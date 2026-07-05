@extends('layouts.admin')

@section('title', 'Security Logs — ความปลอดภัย')

@section('styles')
<style>
.sec-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);
    border-radius: 16px;
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 20px;
}
.sec-hero-icon { font-size: 3rem; }
.sec-hero h1 { margin: 0 0 4px; font-size: 1.5rem; font-weight: 700; }
.sec-hero p  { margin: 0; opacity: .75; font-size: .9rem; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 6px rgba(0,0,0,.07);
    border-left: 4px solid transparent;
}
.stat-card.red    { border-color: #ef4444; }
.stat-card.yellow { border-color: #f59e0b; }
.stat-card.blue   { border-color: #3b82f6; }
.stat-card.green  { border-color: #10b981; }
.stat-card.purple { border-color: #8b5cf6; }
.stat-num  { font-size: 2rem; font-weight: 700; color: #111; }
.stat-label { font-size: .8rem; color: #6b7280; margin-top: 4px; }

.filter-bar {
    background: #fff;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 6px rgba(0,0,0,.07);
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}
.filter-bar select,
.filter-bar input {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: .875rem;
    color: #374151;
    background: #f9fafb;
    min-width: 150px;
}
.btn-filter { padding: 8px 20px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: .875rem; cursor: pointer; font-weight: 600; }
.btn-clear  { padding: 8px 16px; background: #f3f4f6; color: #6b7280; border: none; border-radius: 8px; font-size: .875rem; cursor: pointer; text-decoration: none; display: inline-block; }

.log-table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 6px rgba(0,0,0,.07); overflow: hidden; }
.log-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.log-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}
.log-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.log-table tr:last-child td { border-bottom: none; }
.log-table tr:hover td { background: #fafbff; }

.badge-event {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    white-space: nowrap;
}
.badge-multi    { background: #fee2e2; color: #991b1b; }
.badge-checkin  { background: #fef3c7; color: #92400e; }
.badge-mismatch { background: #ffedd5; color: #9a3412; }

.badge-reviewed   { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
.badge-unreviewed { background: #fef2f2; color: #991b1b; padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; }

.btn-review {
    padding: 5px 14px;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: .78rem;
    cursor: pointer;
    font-weight: 600;
}
.btn-detail { padding: 5px 12px; background: #f3f4f6; color: #374151; border-radius: 7px; font-size: .78rem; text-decoration: none; display: inline-block; }

.review-all-btn {
    background: #10b981;
    color: #fff;
    border: none;
    padding: 8px 20px;
    border-radius: 9px;
    font-weight: 600;
    font-size: .85rem;
    cursor: pointer;
}

.empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
.empty-state svg { width: 64px; opacity: .3; margin-bottom: 16px; }
</style>
@endsection

@section('content')
<div class="sec-hero">
    <div class="sec-hero-icon">🛡️</div>
    <div>
        <h1>Security Logs — ระบบตรวจจับความปลอดภัย</h1>
        <p>ตรวจสอบพฤติกรรมต้องสงสัย: การ Login หลาย Account จากเครื่องเดียวกัน / การเช็คอินแทนกัน</p>
    </div>
</div>

{{-- Summary Cards --}}
<div class="stat-grid">
    <div class="stat-card purple">
        <div class="stat-num">{{ $summary['total'] }}</div>
        <div class="stat-label">เหตุการณ์ทั้งหมด</div>
    </div>
    <div class="stat-card red">
        <div class="stat-num">{{ $summary['unreviewed'] }}</div>
        <div class="stat-label">ยังไม่ได้ตรวจสอบ</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-num">{{ $summary['today'] }}</div>
        <div class="stat-label">วันนี้</div>
    </div>
    <div class="stat-card red">
        <div class="stat-num">{{ $summary['multi_acct'] }}</div>
        <div class="stat-label">Multi-Account Login</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-num">{{ $summary['suspicious'] }}</div>
        <div class="stat-label">Suspicious Check-in</div>
    </div>
</div>

{{-- Filter Bar --}}
<form method="GET" action="{{ route('admin.security-logs.index') }}" class="filter-bar">
    <select name="event_type">
        <option value="">ทุกประเภท</option>
        <option value="multi_account_login" @selected(request('event_type') === 'multi_account_login')>Multi-Account Login</option>
        <option value="suspicious_checkin"  @selected(request('event_type') === 'suspicious_checkin')>Suspicious Check-in</option>
        <option value="device_mismatch"     @selected(request('event_type') === 'device_mismatch')>Device Mismatch</option>
    </select>
    <select name="reviewed">
        <option value="">ทุกสถานะ</option>
        <option value="0" @selected(request('reviewed') === '0')>ยังไม่ตรวจสอบ</option>
        <option value="1" @selected(request('reviewed') === '1')>ตรวจสอบแล้ว</option>
    </select>
    <input type="date" name="date" value="{{ request('date') }}" placeholder="วันที่">
    <input type="text" name="ip" value="{{ request('ip') }}" placeholder="IP Address" style="min-width:140px;">
    <button type="submit" class="btn-filter">🔍 กรอง</button>
    <a href="{{ route('admin.security-logs.index') }}" class="btn-clear">ล้าง</a>

    @if($summary['unreviewed'] > 0)
    <button type="button" class="review-all-btn" onclick="markAllReviewed()">
        ✅ Mark ทั้งหมดว่าตรวจสอบแล้ว ({{ $summary['unreviewed'] }})
    </button>
    @endif
</form>

{{-- Logs Table --}}
<div class="log-table-wrap">
    @if($logs->isEmpty())
        <div class="empty-state">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <p style="font-size:1rem; font-weight:600; color:#374151;">ไม่พบเหตุการณ์น่าสงสัย</p>
            <p>ระบบยังไม่ตรวจพบพฤติกรรมผิดปกติ หรือกรองไม่พบข้อมูล</p>
        </div>
    @else
    <table class="log-table">
        <thead>
            <tr>
                <th>#</th>
                <th>ประเภท</th>
                <th>นักศึกษา</th>
                <th>IP Address</th>
                <th>เวลา</th>
                <th>สถานะ</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr id="row-{{ $log->id }}">
                <td style="color:#9ca3af; font-size:.8rem;">{{ $log->id }}</td>
                <td>
                    @php
                        $badgeClass = match($log->event_type) {
                            'multi_account_login' => 'badge-multi',
                            'suspicious_checkin'  => 'badge-checkin',
                            default               => 'badge-mismatch',
                        };
                    @endphp
                    <span class="badge-event {{ $badgeClass }}">{{ $log->event_type_label }}</span>
                </td>
                <td>
                    @if($log->user)
                        <div style="font-weight:600; color:#111;">{{ $log->user->full_name }}</div>
                        <div style="font-size:.78rem; color:#6b7280;">{{ $log->user->student_id }}</div>
                    @else
                        <span style="color:#9ca3af;">—</span>
                    @endif
                </td>
                <td style="font-family:monospace; font-size:.82rem; color:#374151;">{{ $log->ip_address ?? '—' }}</td>
                <td style="font-size:.82rem; color:#6b7280; white-space:nowrap;">
                    {{ $log->created_at->format('d/m/Y H:i:s') }}<br>
                    <span style="font-size:.72rem;">{{ $log->created_at->diffForHumans() }}</span>
                </td>
                <td>
                    @if($log->is_reviewed)
                        <span class="badge-reviewed">✓ ตรวจสอบแล้ว</span>
                    @else
                        <span class="badge-unreviewed">⚠ ยังไม่ตรวจสอบ</span>
                    @endif
                </td>
                <td style="display:flex; gap:6px; flex-wrap:wrap;">
                    <a href="{{ route('admin.security-logs.show', $log) }}" class="btn-detail">ดูรายละเอียด</a>
                    @if(!$log->is_reviewed)
                    <button class="btn-review" onclick="markReviewed({{ $log->id }})">Mark ตรวจแล้ว</button>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px;">
        {{ $logs->links() }}
    </div>
    @endif
</div>

<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

async function markReviewed(id) {
    const res = await fetch(`/admin/security-logs/${id}/review`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    });
    if (res.ok) {
        const row = document.getElementById(`row-${id}`);
        row.querySelector('.badge-unreviewed').outerHTML = '<span class="badge-reviewed">✓ ตรวจสอบแล้ว</span>';
        row.querySelector('.btn-review').remove();
    }
}

async function markAllReviewed() {
    if (!confirm('ยืนยันทำเครื่องหมายทั้งหมดว่าตรวจสอบแล้ว?')) return;
    const res = await fetch('/admin/security-logs/review-all', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    });
    if (res.ok) location.reload();
}
</script>
@endsection
