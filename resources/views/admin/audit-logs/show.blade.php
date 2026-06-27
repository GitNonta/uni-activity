{{-- หน้ารายละเอียด Audit Log — Premium UI --}}
@extends('layouts.admin')
@section('title', 'รายละเอียด Log #' . $log->id)

@section('styles')
<style>
/* ═══════════════════════════════
   AUDIT LOG DETAIL — Premium Styles
   ═══════════════════════════════ */

.detail-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .82rem; font-weight: 600; color: #6366f1;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 10px;
    border: 1px solid #e0e7ff;
    background: #fafbff;
    transition: all .2s;
}
.detail-back-link:hover {
    background: #6366f1; color: #fff; border-color: #6366f1;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(99,102,241,.2);
}
.detail-back-link svg { width: 14px; height: 14px; }

.detail-header {
    display: flex; align-items: center; gap: .75rem;
    margin-bottom: 1.5rem; flex-wrap: wrap;
}
.detail-header-icon {
    width: 44px; height: 44px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 14px rgba(0,0,0,.1);
}
.detail-header-icon svg { width: 22px; height: 22px; color: #fff; }
.detail-header-icon-create { background: linear-gradient(135deg, #22c55e, #4ade80); }
.detail-header-icon-update { background: linear-gradient(135deg, #eab308, #facc15); }
.detail-header-icon-delete { background: linear-gradient(135deg, #ef4444, #f87171); }
.detail-header-icon-approve { background: linear-gradient(135deg, #22c55e, #4ade80); }
.detail-header-icon-reject { background: linear-gradient(135deg, #ef4444, #f87171); }
.detail-header-icon-toggle { background: linear-gradient(135deg, #eab308, #facc15); }
.detail-header-icon-login { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
.detail-header-icon-logout { background: linear-gradient(135deg, #6b7280, #9ca3af); }

.detail-title { font-size: 1.2rem; font-weight: 800; color: #0f172a; }
.detail-id { font-size: .8rem; color: #94a3b8; margin-top: 2px; }

/* Info Grid Card */
.detail-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.detail-card-header {
    display: flex; align-items: center; gap: .5rem;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: .85rem; font-weight: 700; color: #1e293b;
}
.detail-card-header svg { width: 18px; height: 18px; color: #6366f1; }
.detail-card-body { padding: 1.25rem; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
}
.info-item {}
.info-label {
    font-size: .68rem; font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 4px;
}
.info-value {
    font-size: .88rem; font-weight: 600; color: #1e293b;
    word-break: break-word;
}
.info-value-muted {
    font-size: .78rem; color: #64748b;
    word-break: break-all;
}

/* Description Block */
.desc-block {
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f1f5f9;
}
.desc-text {
    font-size: .95rem; font-weight: 600; color: #1e293b;
    line-height: 1.6;
}

/* Action Badge (detail) */
.detail-action-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 8px;
    font-size: .78rem; font-weight: 700;
}
.detail-action-badge svg { width: 14px; height: 14px; }

/* Data Table (Before/After/Diff) */
.data-table-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.data-table-header {
    display: flex; align-items: center; gap: .5rem;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: .88rem; font-weight: 700;
}
.data-table-header svg { width: 18px; height: 18px; }
.data-table-header-red { color: #dc2626; }
.data-table-header-green { color: #16a34a; }
.data-table-header-blue { color: #2563eb; }

.data-table {
    width: 100%;
    font-size: .82rem;
}
.data-table thead th {
    background: #fafbfc;
    padding: .625rem 1rem;
    font-size: .7rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1px solid #e2e8f0;
}
.data-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #fafbfe; }
.data-table tbody td {
    padding: .625rem 1rem;
    vertical-align: top;
    color: #334155;
}
.data-table .field-name {
    font-weight: 700;
    color: #475569;
    font-size: .78rem;
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
    white-space: nowrap;
}
.data-table .field-value {
    word-break: break-all;
    font-size: .8rem;
}

/* Diff highlight */
.diff-old { background: #fef2f2; color: #991b1b; }
.diff-new { background: #f0fdf4; color: #166534; }

/* Responsive */
@media (max-width: 768px) {
    .info-grid { grid-template-columns: 1fr; }
    .detail-header { flex-direction: column; align-items: flex-start; }
}
</style>
@endsection

@section('content')

{{-- ─── Back Button + Title ─── --}}
<div style="margin-bottom: .75rem;">
    <a href="{{ route('admin.audit-logs.index') }}" class="detail-back-link">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        กลับไปหน้ารายการ
    </a>
</div>

<div class="detail-header">
    @php
        $iconClass = 'detail-header-icon-' . $log->action;
        $actionBadgeClass = match($log->action) {
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
    @endphp
    <div class="detail-header-icon {{ $iconClass }}">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
    </div>
    <div>
        <h1 class="detail-title">รายละเอียดบันทึกการดำเนินงาน</h1>
        <p class="detail-id">Log ID: #{{ $log->id }} · {{ $log->created_at->format('d/m/Y H:i:s') }}</p>
    </div>
</div>

{{-- ─── Overview Card ─── --}}
<div class="detail-card">
    <div class="detail-card-header">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        ข้อมูลทั่วไป
    </div>
    <div class="detail-card-body">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">ผู้ดำเนินการ</div>
                <div class="info-value">{{ $log->user->full_name ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">การกระทำ</div>
                <div>
                    <span class="detail-action-badge {{ $actionBadgeClass }}">
                        {{ $log->action_label }}
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">ประเภท</div>
                <div class="info-value">{{ $log->model_label }} @if($log->model_id) <span style="color:#94a3b8;font-size:.78rem;">#{{ $log->model_id }}</span> @endif</div>
            </div>
            <div class="info-item">
                <div class="info-label">เวลา</div>
                <div class="info-value">{{ $log->created_at->format('d/m/Y H:i:s') }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">IP Address</div>
                <div class="info-value" style="font-family:'SF Mono','Monaco','Inconsolata',monospace;font-size:.82rem;color:#64748b;">{{ $log->ip_address ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">User Agent</div>
                <div class="info-value-muted" style="font-size:.72rem;line-height:1.4;">{{ $log->user_agent ?? '-' }}</div>
            </div>
        </div>

        <div class="desc-block">
            <div class="info-label" style="margin-bottom:6px;">คำอธิบาย</div>
            <p class="desc-text">{{ $log->description }}</p>
        </div>
    </div>
</div>

{{-- ─── Before Values ─── --}}
@if($log->old_values)
<div class="data-table-card">
    <div class="data-table-header data-table-header-red">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        ค่าเดิม (Before)
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:200px;">ฟิลด์</th>
                    <th>ค่า</th>
                </tr>
            </thead>
            <tbody>
                @foreach($log->old_values as $key => $value)
                <tr>
                    <td class="field-name">{{ $key }}</td>
                    <td class="field-value">
                        @if(is_array($value)) {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}
                        @else {{ $value ?? '-' }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ─── After Values ─── --}}
@if($log->new_values)
<div class="data-table-card">
    <div class="data-table-header data-table-header-green">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        ค่าใหม่ (After)
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:200px;">ฟิลด์</th>
                    <th>ค่า</th>
                </tr>
            </thead>
            <tbody>
                @foreach($log->new_values as $key => $value)
                <tr>
                    <td class="field-name">{{ $key }}</td>
                    <td class="field-value">
                        @if(is_array($value)) {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}
                        @else {{ $value ?? '-' }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ─── Diff Comparison ─── --}}
@if($log->old_values && $log->new_values)
<div class="data-table-card">
    <div class="data-table-header data-table-header-blue">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        ความเปลี่ยนแปลง (Diff)
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:180px;">ฟิลด์</th>
                    <th style="background:#fef2f2;">ค่าเดิม</th>
                    <th style="background:#f0fdf4;">ค่าใหม่</th>
                </tr>
            </thead>
            <tbody>
                @foreach($log->new_values as $key => $newVal)
                    @php $oldVal = $log->old_values[$key] ?? null; @endphp
                    @if($oldVal != $newVal)
                    <tr>
                        <td class="field-name">{{ $key }}</td>
                        <td class="diff-old field-value">{{ is_array($oldVal) ? json_encode($oldVal, JSON_UNESCAPED_UNICODE) : ($oldVal ?? '-') }}</td>
                        <td class="diff-new field-value">{{ is_array($newVal) ? json_encode($newVal, JSON_UNESCAPED_UNICODE) : ($newVal ?? '-') }}</td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
