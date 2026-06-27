{{-- หน้า Audit Log: แสดงประวัติการกระทำของ Admin ทั้งหมด (Premium UI) --}}
@extends('layouts.admin')
@section('title', 'Audit Logs — ประวัติการดำเนินงาน')

@section('styles')
<style>
/* ═══════════════════════════════
   AUDIT LOG PAGE — Premium Styles
   ═══════════════════════════════ */

/* Page Header */
.audit-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.audit-header-left {
    display: flex;
    align-items: center;
    gap: .75rem;
}
.audit-header-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(99,102,241,0.25);
}
.audit-header-icon svg { width: 24px; height: 24px; color: #fff; }
.audit-title { font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
.audit-subtitle { font-size: .8rem; color: #64748b; margin-top: 2px; }
.audit-header-badges { display: flex; gap: .5rem; flex-wrap: wrap; }
.header-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 10px;
    font-size: .78rem; font-weight: 600;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(0,0,0,.04);
}
.header-badge-total { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #1d4ed8; }
.header-badge-today { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #15803d; }
.header-badge svg { width: 14px; height: 14px; }

/* Stat Cards */
.audit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: .75rem;
    margin-bottom: 1.5rem;
}
.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    display: flex; align-items: center; gap: .875rem;
    transition: all .25s ease;
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 14px 14px 0 0;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,.06);
}
.stat-card-green::before { background: linear-gradient(90deg, #22c55e, #4ade80); }
.stat-card-yellow::before { background: linear-gradient(90deg, #eab308, #facc15); }
.stat-card-red::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.stat-card-blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }

.stat-icon {
    width: 40px; height: 40px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-icon svg { width: 20px; height: 20px; }
.stat-icon-green { background: #f0fdf4; color: #16a34a; }
.stat-icon-yellow { background: #fefce8; color: #ca8a04; }
.stat-icon-red { background: #fef2f2; color: #dc2626; }
.stat-icon-blue { background: #eff6ff; color: #2563eb; }

.stat-info { min-width: 0; }
.stat-number { font-size: 1.35rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
.stat-label { font-size: .72rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }

/* Filter Section */
.audit-filter-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    margin-bottom: 1.25rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.audit-filter-header {
    display: flex; align-items: center; gap: .5rem;
    padding: .75rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: .82rem; font-weight: 700; color: #475569;
}
.audit-filter-header svg { width: 16px; height: 16px; color: #94a3b8; }
.audit-filter-body {
    padding: 1rem 1.25rem;
}
.filter-grid {
    display: flex; gap: .625rem; flex-wrap: wrap; align-items: end;
}
.filter-field {
    min-width: 140px; flex: 1;
}
.filter-field label {
    display: block;
    font-size: .72rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: .25rem;
}
.filter-field .form-control {
    font-size: .82rem;
    padding: .5rem .75rem;
    border-radius: 10px;
    border-color: #e2e8f0;
}
.filter-field .form-control:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.1);
}
.filter-actions {
    display: flex; gap: .5rem; flex-shrink: 0;
    padding-top: .25rem;
}
.filter-btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    border: none;
    padding: .5rem 1.25rem;
    border-radius: 10px;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: .375rem;
    transition: all .2s;
    box-shadow: 0 2px 8px rgba(99,102,241,.2);
}
.filter-btn-primary:hover {
    box-shadow: 0 4px 16px rgba(99,102,241,.35);
    transform: translateY(-1px);
}
.filter-btn-primary svg { width: 15px; height: 15px; }
.filter-btn-clear {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
    padding: .5rem 1rem;
    border-radius: 10px;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: .375rem;
    transition: all .2s;
}
.filter-btn-clear:hover { background: #f1f5f9; text-decoration: none; color: #475569; }

/* Table Container */
.audit-table-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.audit-table-card table {
    width: 100%;
    font-size: .82rem;
}
.audit-table-card thead th {
    background: #fafbfc;
    padding: .75rem 1rem;
    font-size: .72rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.audit-table-card tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
}
.audit-table-card tbody tr:last-child { border-bottom: none; }
.audit-table-card tbody tr:hover { background: #fafbfe; }
.audit-table-card tbody td {
    padding: .75rem 1rem;
    vertical-align: middle;
    color: #334155;
}

/* Table Cell Helpers */
.td-time {
    white-space: nowrap;
    font-size: .78rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
.td-user {
    display: flex; align-items: center; gap: .5rem;
}
.td-user-avatar {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: .65rem;
    font-weight: 700;
    flex-shrink: 0;
}
.td-user-name { font-weight: 600; color: #1e293b; font-size: .82rem; }
.td-desc {
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #475569;
}
.td-ip {
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
    font-size: .72rem;
    color: #94a3b8;
}
.td-action-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #6366f1;
    font-size: .72rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
}
.td-action-btn:hover {
    background: #6366f1; color: #fff; border-color: #6366f1;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(99,102,241,.2);
}
.td-action-btn svg { width: 12px; height: 12px; }

/* Action Badges (enhanced) */
.action-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 8px;
    font-size: .7rem; font-weight: 700;
    letter-spacing: .01em;
}
.action-badge svg { width: 12px; height: 12px; }
.action-badge-create { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.action-badge-update { background: #fffbeb; color: #a16207; border: 1px solid #fde68a; }
.action-badge-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.action-badge-approve { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.action-badge-reject { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.action-badge-toggle { background: #fffbeb; color: #a16207; border: 1px solid #fde68a; }
.action-badge-login { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.action-badge-logout { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

/* Model Type Tag */
.model-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: .72rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #475569;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}
.empty-state-icon {
    width: 56px; height: 56px;
    background: #f8fafc;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto .75rem;
}
.empty-state-icon svg { width: 28px; height: 28px; color: #cbd5e1; }
.empty-state-title { font-size: .9rem; font-weight: 700; color: #64748b; margin-bottom: .25rem; }
.empty-state-desc { font-size: .8rem; color: #94a3b8; }

/* Pagination */
.audit-pagination {
    margin-top: 1.25rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: .25rem;
}
.audit-pagination nav { display: flex; }

/* Responsive */
@media (max-width: 768px) {
    .audit-stats { grid-template-columns: repeat(2, 1fr); }
    .filter-grid { flex-direction: column; }
    .filter-field { min-width: 100%; }
    .audit-header { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
    .audit-stats { grid-template-columns: 1fr; }
}
</style>
@endsection

@section('content')

{{-- ─── Header ─── --}}
<div class="audit-header">
    <div class="audit-header-left">
        <div class="audit-header-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01m-.01 4h.01"/>
            </svg>
        </div>
        <div>
            <h1 class="audit-title">Audit Logs</h1>
            <p class="audit-subtitle">ประวัติการดำเนินงานของผู้ดูแลระบบทั้งหมด</p>
        </div>
    </div>
    <div class="audit-header-badges">
        <span class="header-badge header-badge-total">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            ทั้งหมด {{ number_format($stats['total']) }} รายการ
        </span>
        <span class="header-badge header-badge-today">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            วันนี้ {{ $stats['today'] }} รายการ
        </span>
    </div>
</div>

{{-- ─── Stats Cards ─── --}}
<div class="audit-stats">
    <div class="stat-card stat-card-green">
        <div class="stat-icon stat-icon-green">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number">{{ number_format($stats['creates']) }}</div>
            <div class="stat-label">สร้าง</div>
        </div>
    </div>
    <div class="stat-card stat-card-yellow">
        <div class="stat-icon stat-icon-yellow">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number">{{ number_format($stats['updates']) }}</div>
            <div class="stat-label">แก้ไข</div>
        </div>
    </div>
    <div class="stat-card stat-card-red">
        <div class="stat-icon stat-icon-red">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number">{{ number_format($stats['deletes']) }}</div>
            <div class="stat-label">ลบ</div>
        </div>
    </div>
    <div class="stat-card stat-card-blue">
        <div class="stat-icon stat-icon-blue">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number">{{ number_format($stats['logins']) }}</div>
            <div class="stat-label">เข้าสู่ระบบ</div>
        </div>
    </div>
</div>

{{-- ─── Filter Section ─── --}}
<form method="GET" action="{{ route('admin.audit-logs.index') }}" class="audit-filter-card">
    <div class="audit-filter-header">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        ตัวกรองขั้นสูง
    </div>
    <div class="audit-filter-body">
        <div class="filter-grid">
            <div class="filter-field" style="flex:1.5;">
                <label>ค้นหาคำอธิบาย</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="พิมพ์คำค้นหา..." class="form-control">
            </div>
            <div class="filter-field">
                <label>ผู้ดำเนินการ</label>
                <select name="user_id" class="form-control">
                    <option value="">— ทั้งหมด —</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" {{ request('user_id') == $admin->id ? 'selected' : '' }}>{{ $admin->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label>การกระทำ</label>
                <select name="action" class="form-control">
                    <option value="">— ทั้งหมด —</option>
                    <option value="create" {{ request('action') === 'create' ? 'selected' : '' }}>สร้าง</option>
                    <option value="update" {{ request('action') === 'update' ? 'selected' : '' }}>แก้ไข</option>
                    <option value="delete" {{ request('action') === 'delete' ? 'selected' : '' }}>ลบ</option>
                    <option value="approve" {{ request('action') === 'approve' ? 'selected' : '' }}>อนุมัติ</option>
                    <option value="reject" {{ request('action') === 'reject' ? 'selected' : '' }}>ปฏิเสธ</option>
                    <option value="toggle" {{ request('action') === 'toggle' ? 'selected' : '' }}>สลับสถานะ</option>
                    <option value="login" {{ request('action') === 'login' ? 'selected' : '' }}>เข้าสู่ระบบ</option>
                </select>
            </div>
            <div class="filter-field">
                <label>จากวันที่</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="filter-field">
                <label>ถึงวันที่</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="filter-actions">
                <button type="submit" class="filter-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    กรอง
                </button>
                <a href="{{ route('admin.audit-logs.index') }}" class="filter-btn-clear">ล้าง</a>
            </div>
        </div>
    </div>
</form>

{{-- ─── Log Table ─── --}}
<div class="audit-table-card">
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th style="width:150px;">เวลา</th>
                    <th style="width:150px;">ผู้ดำเนินการ</th>
                    <th style="width:100px;">การกระทำ</th>
                    <th style="width:100px;">ประเภท</th>
                    <th>คำอธิบาย</th>
                    <th style="width:110px;">IP Address</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="td-time">
                        <div>{{ $log->created_at->format('d/m/Y') }}</div>
                        <div style="font-size:.7rem;color:#94a3b8;">{{ $log->created_at->format('H:i:s') }}</div>
                    </td>
                    <td>
                        <div class="td-user">
                            <div class="td-user-avatar">
                                {{ strtoupper(mb_substr($log->user->full_name ?? '?', 0, 1)) }}
                            </div>
                            <span class="td-user-name">{{ $log->user->full_name ?? '-' }}</span>
                        </div>
                    </td>
                    <td>
                        @php
                            $actionClass = match($log->action) {
                                'create' => 'action-badge-create',
                                'update' => 'action-badge-update',
                                'delete' => 'action-badge-delete',
                                'approve' => 'action-badge-approve',
                                'reject' => 'action-badge-reject',
                                'toggle' => 'action-badge-toggle',
                                'login' => 'action-badge-login',
                                'logout' => 'action-badge-logout',
                                default => 'action-badge-login',
                            };
                            $actionIcon = match($log->action) {
                                'create' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>',
                                'update' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
                                'delete' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>',
                                'approve' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
                                'reject' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
                                'login' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"/>',
                                'logout' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/>',
                                default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"/>',
                            };
                        @endphp
                        <span class="action-badge {{ $actionClass }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $actionIcon !!}</svg>
                            {{ $log->action_label }}
                        </span>
                    </td>
                    <td>
                        <span class="model-tag">{{ $log->model_label }}</span>
                    </td>
                    <td class="td-desc" title="{{ $log->description }}">{{ $log->description }}</td>
                    <td class="td-ip">{{ $log->ip_address ?? '-' }}</td>
                    <td>
                        <a href="{{ route('admin.audit-logs.show', $log->id) }}" class="td-action-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            ดู
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                            <p class="empty-state-title">ยังไม่มีประวัติการดำเนินงาน</p>
                            <p class="empty-state-desc">ประวัติจะปรากฏที่นี่เมื่อมีการดำเนินการใดๆ ในระบบ</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ─── Pagination ─── --}}
@if($logs->hasPages())
<div class="audit-pagination">
    {{ $logs->links() }}
</div>
@endif

@endsection
