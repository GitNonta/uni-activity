@extends('layouts.admin')

@section('title', 'Security Log #' . $securityLog->id)

@section('styles')
<style>
.detail-wrap { max-width: 860px; }
.detail-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
}
.detail-back { text-decoration: none; color: #6366f1; font-size: .875rem; display: inline-flex; align-items: center; gap: 4px; }
.detail-back:hover { text-decoration: underline; }
.event-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 999px;
    font-size: .9rem;
    font-weight: 700;
    margin-left: 8px;
}
.event-multi    { background: #fee2e2; color: #991b1b; }
.event-checkin  { background: #fef3c7; color: #92400e; }
.event-mismatch { background: #ffedd5; color: #9a3412; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
.detail-card {
    background: #fff;
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 1px 8px rgba(0,0,0,.07);
}
.detail-card h3 { font-size: .9rem; font-weight: 700; color: #374151; margin: 0 0 16px; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6; }
.detail-row { display: flex; gap: 8px; margin-bottom: 10px; font-size: .875rem; }
.detail-label { color: #9ca3af; min-width: 130px; flex-shrink: 0; }
.detail-value { color: #111; font-weight: 500; word-break: break-all; }
.detail-value.mono { font-family: monospace; font-size: .82rem; }

.related-card { background: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 1px 8px rgba(0,0,0,.07); margin-bottom: 20px; }
.related-card h3 { font-size: .9rem; font-weight: 700; color: #374151; margin: 0 0 14px; }
.related-user { display: flex; align-items: center; gap: 12px; padding: 10px; background: #f8fafc; border-radius: 9px; margin-bottom: 8px; }
.related-user-avatar { width: 36px; height: 36px; background: #6366f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: .85rem; }
.related-user-name { font-weight: 600; font-size: .875rem; }
.related-user-sub { font-size: .78rem; color: #6b7280; }

.json-wrap { background: #1e1e2e; border-radius: 10px; padding: 16px; color: #cdd6f4; font-family: monospace; font-size: .8rem; overflow-x: auto; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; }

.review-section { background: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 1px 8px rgba(0,0,0,.07); }
.review-section h3 { font-size: .9rem; font-weight: 700; color: #374151; margin: 0 0 14px; }
.btn-mark-reviewed { padding: 10px 24px; background: #10b981; color: #fff; border: none; border-radius: 9px; font-weight: 700; font-size: .9rem; cursor: pointer; }
.reviewed-indicator { display: flex; align-items: center; gap: 8px; color: #166534; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="detail-wrap">
    <div class="detail-header">
        <a href="{{ route('admin.security-logs.index') }}" class="detail-back">
            ← กลับรายการ
        </a>
        <h2 style="margin:0; font-size:1.2rem;">
            Security Log #{{ $securityLog->id }}
            @php
                $cls = match($securityLog->event_type) {
                    'multi_account_login' => 'event-multi',
                    'suspicious_checkin'  => 'event-checkin',
                    default               => 'event-mismatch',
                };
            @endphp
            <span class="event-badge {{ $cls }}">{{ $securityLog->event_type_label }}</span>
        </h2>
    </div>

    <div class="detail-grid">
        {{-- Event Info --}}
        <div class="detail-card">
            <h3>🔍 ข้อมูลเหตุการณ์</h3>
            <div class="detail-row">
                <span class="detail-label">ประเภท</span>
                <span class="detail-value">{{ $securityLog->event_type_label }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">เวลาที่เกิด</span>
                <span class="detail-value">{{ $securityLog->created_at->format('d/m/Y H:i:s') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">IP Address</span>
                <span class="detail-value mono">{{ $securityLog->ip_address ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Device Hash</span>
                <span class="detail-value mono" style="font-size:.72rem;">{{ $securityLog->device_fingerprint ?? '—' }}</span>
            </div>
        </div>

        {{-- User Info --}}
        <div class="detail-card">
            <h3>👤 นักศึกษาที่เกี่ยวข้อง</h3>
            @if($securityLog->user)
            <div class="detail-row">
                <span class="detail-label">ชื่อ</span>
                <span class="detail-value">{{ $securityLog->user->full_name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">รหัสนักศึกษา</span>
                <span class="detail-value mono">{{ $securityLog->user->student_id }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">คณะ</span>
                <span class="detail-value">{{ $securityLog->user->faculty ?? '—' }}</span>
            </div>
            <div style="margin-top:12px;">
                <a href="{{ route('admin.students.show', $securityLog->user->id) }}" style="color:#6366f1; font-size:.875rem; text-decoration:none; font-weight:600;">
                    → ดูโปรไฟล์นักศึกษา
                </a>
            </div>
            @else
                <p style="color:#9ca3af;">ไม่พบข้อมูลผู้ใช้</p>
            @endif
        </div>
    </div>

    {{-- Related Users --}}
    @if($relatedUsers->isNotEmpty())
    <div class="related-card">
        <h3>🔗 Accounts อื่นที่เกี่ยวข้อง (จาก Device/IP เดียวกัน)</h3>
        @foreach($relatedUsers as $ru)
        <div class="related-user">
            <div class="related-user-avatar">{{ mb_substr($ru->full_name, 0, 1) }}</div>
            <div>
                <div class="related-user-name">{{ $ru->full_name }}</div>
                <div class="related-user-sub">{{ $ru->student_id }} · {{ $ru->faculty ?? '—' }}</div>
            </div>
            <a href="{{ route('admin.students.show', $ru->id) }}" style="margin-left:auto; color:#6366f1; font-size:.8rem; text-decoration:none;">ดูโปรไฟล์ →</a>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Details JSON --}}
    @if($securityLog->details)
    <div class="detail-card" style="margin-bottom:20px;">
        <h3>📋 ข้อมูลเพิ่มเติม (Details)</h3>
        <div class="json-wrap">{{ json_encode($securityLog->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
    </div>
    @endif

    {{-- Review Section --}}
    <div class="review-section">
        <h3>✅ สถานะการตรวจสอบ</h3>
        @if($securityLog->is_reviewed)
            <div class="reviewed-indicator">
                <svg style="width:20px" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ตรวจสอบแล้ว เมื่อ {{ $securityLog->reviewed_at?->format('d/m/Y H:i') }}
                @if($securityLog->reviewer) โดย {{ $securityLog->reviewer->full_name }} @endif
            </div>
        @else
            <p style="color:#6b7280; font-size:.875rem; margin:0 0 14px;">เหตุการณ์นี้ยังไม่ได้รับการตรวจสอบ</p>
            <button class="btn-mark-reviewed" onclick="markReviewed({{ $securityLog->id }})">
                ✅ ทำเครื่องหมายว่าตรวจสอบแล้ว
            </button>
        @endif
    </div>
</div>

<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
async function markReviewed(id) {
    const res = await fetch(`/admin/security-logs/${id}/review`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    });
    if (res.ok) location.reload();
}
</script>
@endsection
