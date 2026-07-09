@extends('layouts.admin')

@section('title', 'Security Logs — ระบบรักษาความปลอดภัย')

@section('styles')
<style>
/* Official & Professional UI Design */
:root {
    --primary: #4f46e5;
    --primary-hover: #4338ca;
    --surface: #ffffff;
    --background: #f8fafc;
    --border: #e2e8f0;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --danger: #ef4444;
    --warning: #f59e0b;
    --success: #10b981;
    --info: #3b82f6;
}

.sec-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.sec-header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sec-header-icon {
    width: 40px;
    height: 40px;
    background: #e0e7ff;
    color: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sec-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
}

.sec-header p {
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 0.875rem;
}

.stat-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 20px; 
    margin-bottom: 24px; 
}

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon.purple { background: #f3e8ff; color: #9333ea; }
.stat-icon.red    { background: #fee2e2; color: #dc2626; }
.stat-icon.blue   { background: #dbeafe; color: #2563eb; }
.stat-icon.orange { background: #ffedd5; color: #ea580c; }

.stat-info { flex: 1; }
.stat-num  { font-size: 1.75rem; font-weight: 700; color: var(--text-main); line-height: 1.2; }
.stat-label { font-size: 0.8125rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4px; }

.filter-bar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 200px;
}

.filter-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.875rem;
    color: var(--text-main);
    background: #f8fafc;
    transition: border-color 0.2s;
}

.filter-input:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
    text-decoration: none;
}

.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-hover); }
.btn-secondary { background: #f1f5f9; color: var(--text-main); border-color: var(--border); }
.btn-secondary:hover { background: #e2e8f0; }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #059669; }

.table-container {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.log-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.log-table th {
    background: #f8fafc;
    padding: 14px 20px;
    text-align: left;
    font-weight: 600;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.log-table td { 
    padding: 16px 20px; 
    border-bottom: 1px solid var(--border); 
    vertical-align: middle; 
}
.log-table tr:last-child td { border-bottom: none; }
.log-table tr:hover td { background: #f8fafc; }

.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-multi    { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge-checkin  { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
.badge-mismatch { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8125rem;
    font-weight: 500;
}
.status-reviewed { color: var(--success); }
.status-unreviewed { color: var(--danger); }

.user-info { display: flex; flex-direction: column; gap: 2px; }
.user-name { font-weight: 600; color: var(--text-main); }
.user-id { font-size: 0.8125rem; color: var(--text-muted); }

.time-info { display: flex; flex-direction: column; gap: 2px; }
.time-date { color: var(--text-main); font-weight: 500; }
.time-human { font-size: 0.75rem; color: var(--text-muted); }

.action-buttons { display: flex; gap: 8px; }
.btn-sm { padding: 6px 12px; font-size: 0.8125rem; }

.empty-state {
    text-align: center;
    padding: 80px 20px;
}
.empty-icon {
    width: 64px;
    height: 64px;
    color: #cbd5e1;
    margin: 0 auto 16px;
}
.empty-title { font-size: 1.125rem; font-weight: 600; color: var(--text-main); margin-bottom: 8px; }
.empty-desc { color: var(--text-muted); font-size: 0.875rem; }
</style>
@endsection

@section('content')
<div class="sec-header">
    <div class="sec-header-title">
        <div class="sec-header-icon">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </div>
        <div>
            <h1>Security Logs</h1>
            <p>บันทึกพฤติกรรมต้องสงสัย และตรวจสอบความปลอดภัยของระบบ</p>
        </div>
    </div>
    @if($summary['unreviewed'] > 0)
    <button type="button" class="btn btn-success" onclick="markAllReviewed()">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        ทำเครื่องหมายตรวจสอบแล้ว ({{ $summary['unreviewed'] }})
    </button>
    @endif
</div>

{{-- Summary Cards --}}
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-num">{{ $summary['total'] }}</div>
            <div class="stat-label">เหตุการณ์ทั้งหมด</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-num">{{ $summary['unreviewed'] }}</div>
            <div class="stat-label">ยังไม่ได้ตรวจสอบ</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-num">{{ $summary['multi_acct'] }}</div>
            <div class="stat-label">Multi-Account Login</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-num">{{ $summary['suspicious'] }}</div>
            <div class="stat-label">Suspicious Check-in</div>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<form method="GET" action="{{ route('admin.security-logs.index') }}" class="filter-bar">
    <div class="filter-group">
        <select name="event_type" class="filter-input">
            <option value="">ทุกประเภทเหตุการณ์</option>
            <option value="multi_account_login" @selected(request('event_type') === 'multi_account_login')>Multi-Account Login</option>
            <option value="suspicious_checkin"  @selected(request('event_type') === 'suspicious_checkin')>Suspicious Check-in</option>
            <option value="device_mismatch"     @selected(request('event_type') === 'device_mismatch')>Device Mismatch</option>
        </select>
    </div>
    <div class="filter-group">
        <select name="reviewed" class="filter-input">
            <option value="">สถานะทั้งหมด</option>
            <option value="0" @selected(request('reviewed') === '0')>ยังไม่ตรวจสอบ</option>
            <option value="1" @selected(request('reviewed') === '1')>ตรวจสอบแล้ว</option>
        </select>
    </div>
    <div class="filter-group" style="max-width: 180px;">
        <input type="date" name="date" value="{{ request('date') }}" class="filter-input" placeholder="วันที่">
    </div>
    <div class="filter-group" style="max-width: 200px;">
        <input type="text" name="ip" value="{{ request('ip') }}" class="filter-input" placeholder="ค้นหาด้วย IP Address">
    </div>
    
    <button type="submit" class="btn btn-primary">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        ค้นหา
    </button>
    <a href="{{ route('admin.security-logs.index') }}" class="btn btn-secondary">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        รีเซ็ต
    </a>
</form>

{{-- Logs Table --}}
<div class="table-container">
    @if($logs->isEmpty())
        <div class="empty-state">
            <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <div class="empty-title">ไม่พบประวัติที่ต้องสงสัย</div>
            <div class="empty-desc">ระบบยังไม่พบพฤติกรรมการใช้งานที่ผิดปกติในขณะนี้ หรือไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div>
        </div>
    @else
    <table class="log-table">
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>ประเภทเหตุการณ์</th>
                <th>ข้อมูลผู้ใช้ (นักศึกษา)</th>
                <th>IP Address</th>
                <th>เวลาที่เกิดเหตุ</th>
                <th>สถานะ</th>
                <th style="text-align: right;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr id="row-{{ $log->id }}">
                <td style="color:var(--text-muted);">#{{ $log->id }}</td>
                <td>
                    @php
                        $badgeClass = match($log->event_type) {
                            'multi_account_login' => 'badge-multi',
                            'suspicious_checkin'  => 'badge-checkin',
                            default               => 'badge-mismatch',
                        };
                        $iconPath = match($log->event_type) {
                            'multi_account_login' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />',
                            'suspicious_checkin'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />',
                            default               => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">{!! $iconPath !!}</svg>
                        {{ $log->event_type_label }}
                    </span>
                </td>
                <td>
                    @if($log->user)
                        <div class="user-info">
                            <span class="user-name">{{ $log->user->full_name }}</span>
                            <span class="user-id">รหัส: {{ $log->user->student_id }}</span>
                        </div>
                    @else
                        <span style="color:var(--text-muted);">ไม่ทราบตัวตน</span>
                    @endif
                </td>
                <td style="font-family: monospace; color: var(--text-muted);">
                    {{ $log->ip_address ?? '—' }}
                </td>
                <td>
                    <div class="time-info">
                        <span class="time-date">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                        <span class="time-human">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                </td>
                <td>
                    @if($log->is_reviewed)
                        <div class="status-badge status-reviewed">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            ตรวจสอบแล้ว
                        </div>
                    @else
                        <div class="status-badge status-unreviewed">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            รอตรวจสอบ
                        </div>
                    @endif
                </td>
                <td style="text-align: right;">
                    <div class="action-buttons" style="justify-content: flex-end;">
                        @if(!$log->is_reviewed)
                        <button class="btn btn-secondary btn-sm btn-review" onclick="markReviewed({{ $log->id }})">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            รับทราบ
                        </button>
                        @endif
                        <a href="{{ route('admin.security-logs.show', $log) }}" class="btn btn-secondary btn-sm">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            รายละเอียด
                        </a>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--border);">
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
        const statusTd = row.querySelector('.status-unreviewed').parentElement;
        statusTd.innerHTML = `
            <div class="status-badge status-reviewed">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ตรวจสอบแล้ว
            </div>
        `;
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
