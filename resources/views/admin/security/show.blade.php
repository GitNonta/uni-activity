@extends('layouts.admin')

@section('title', 'Security Log #' . $securityLog->id)

@section('styles')
<style>
/* ═══════════════════════════════════════════════════════
   SECURITY LOG DETAIL — Premium & Dark Mode UI
   ═══════════════════════════════════════════════════════ */

.sec-detail-wrap {
    max-width: 1040px;
    margin: 0 auto;
}

/* Back Link */
.detail-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .82rem;
    font-weight: 600;
    color: #ea580c;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 10px;
    border: 1px solid #fed7aa;
    background: #fff;
    transition: all .2s ease;
}
.detail-back-link:hover {
    background: #ea580c;
    color: #fff;
    border-color: #ea580c;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);
}
.detail-back-link svg {
    width: 14px;
    height: 14px;
}

/* Header */
.sec-show-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
}
.sec-show-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.sec-show-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.25);
    flex-shrink: 0;
}
.sec-show-icon svg {
    width: 24px;
    height: 24px;
}
.sec-show-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    line-height: 1.3;
    margin: 0;
}
.sec-show-subtitle {
    font-size: .84rem;
    color: var(--text-muted, #64748b);
    margin: 4px 0 0;
}

/* Event Badges */
.sec-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: .8rem;
    font-weight: 700;
}
.sec-type-badge svg {
    width: 14px;
    height: 14px;
}
.sec-type-badge.badge-red {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
.sec-type-badge.badge-amber {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.sec-type-badge.badge-rose {
    background: #ffe4e6;
    color: #9f1239;
    border: 1px solid #fecdd3;
}
.sec-type-badge.badge-orange {
    background: #ffedd5;
    color: #9a3412;
    border: 1px solid #fed7aa;
}

/* Reviewed Pill / Quick Button */
.sec-reviewed-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
    border-radius: 9999px;
    font-size: .82rem;
    font-weight: 700;
}
.sec-reviewed-badge svg {
    width: 16px;
    height: 16px;
}
.btn-quick-review {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    background: #059669;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: .84rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s ease;
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
}
.btn-quick-review:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}
.btn-quick-review svg {
    width: 16px;
    height: 16px;
}

/* Cards & Layout */
.sec-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 840px) {
    .sec-grid-2 {
        grid-template-columns: 1fr;
    }
}

.sec-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    margin-bottom: 1.25rem;
}
.sec-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--border, #f1f5f9);
    font-size: .9rem;
    font-weight: 700;
    color: var(--text-main, #1e293b);
}
.sec-card-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
}
.sec-card-header svg {
    width: 18px;
    height: 18px;
    color: #ea580c;
}
.sec-card-body {
    padding: 1.25rem;
}

/* Rows & Labels */
.sec-info-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: .65rem 0;
    border-bottom: 1px solid var(--border, #f8fafc);
    font-size: .875rem;
    gap: 12px;
}
.sec-info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.sec-info-label {
    color: var(--text-muted, #64748b);
    font-weight: 500;
    min-width: 120px;
    flex-shrink: 0;
}
.sec-info-value {
    color: var(--text-main, #0f172a);
    font-weight: 600;
    text-align: right;
    word-break: break-word;
}
.sec-info-value.mono {
    font-family: 'SF Mono', Monaco, Inconsolata, monospace;
    font-size: .82rem;
    word-break: break-all;
}

/* User Card Specifics */
.user-hero {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px;
    background: var(--surface-hover, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    margin-bottom: 1rem;
}
.user-hero-details {
    flex: 1;
    min-width: 0;
}
.user-hero-name {
    font-size: .95rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.user-hero-sub {
    font-size: .78rem;
    color: var(--text-muted, #64748b);
    margin-top: 2px;
}
.user-role-badge {
    font-size: .7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-block;
}
.user-role-admin { background: #fee2e2; color: #991b1b; }
.user-role-staff { background: #ffedd5; color: #9a3412; }
.user-role-student { background: #e0f2fe; color: #075985; }

.btn-user-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .82rem;
    font-weight: 600;
    color: #ea580c;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid var(--border, #fed7aa);
    background: var(--surface, #ffffff);
    transition: all .2s;
}
.btn-user-action:hover {
    background: #ea580c;
    color: #fff;
    border-color: #ea580c;
    text-decoration: none;
}
.btn-user-action svg {
    width: 14px;
    height: 14px;
}

/* Copy Button */
.btn-copy-chip {
    background: transparent;
    border: none;
    padding: 2px 6px;
    border-radius: 6px;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .75rem;
    transition: all .15s;
    vertical-align: middle;
}
.btn-copy-chip:hover {
    background: var(--surface-hover, #e2e8f0);
    color: var(--text-main, #0f172a);
}
.btn-copy-chip svg {
    width: 13px;
    height: 13px;
}

/* Technical Details Grid */
.tech-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.tech-box {
    background: var(--surface-hover, #f8fafc);
    border: 1px solid var(--border, #f1f5f9);
    border-radius: 10px;
    padding: 10px 14px;
}
.tech-box-label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--text-muted, #64748b);
    margin-bottom: 4px;
}
.tech-box-value {
    font-size: .88rem;
    font-weight: 600;
    color: var(--text-main, #0f172a);
    word-break: break-all;
}

/* JSON Viewer */
.json-container {
    background: #18181b;
    border: 1px solid #27272a;
    border-radius: 12px;
    overflow: hidden;
}
.json-container-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background: #141416;
    border-bottom: 1px solid #27272a;
    font-size: .75rem;
    font-weight: 600;
    color: #a1a1aa;
}
.json-container-header svg {
    width: 14px;
    height: 14px;
}
.json-raw-content {
    margin: 0;
    padding: 14px;
    font-family: 'SF Mono', Monaco, Inconsolata, monospace;
    font-size: .8rem;
    color: #e4e4e7;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 280px;
    overflow-y: auto;
    line-height: 1.6;
}

/* Related Users */
.related-list {
    display: grid;
    gap: 8px;
}
.related-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: var(--surface-hover, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 10px;
    transition: background .15s;
}
.related-item:hover {
    background: var(--border, #f1f5f9);
}
.related-details {
    flex: 1;
    min-width: 0;
}
.related-name {
    font-size: .875rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
}
.related-meta {
    font-size: .76rem;
    color: var(--text-muted, #64748b);
}

/* Review Status Banner */
.review-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: 12px;
    flex-wrap: wrap;
}
.review-banner-pending {
    background: #fffbeb;
    border: 1px solid #fef3c7;
    color: #92400e;
}
.review-banner-resolved {
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    color: #166534;
}
.review-banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
}
.review-banner-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.review-banner-pending .review-banner-icon {
    background: #fde68a;
    color: #b45309;
}
.review-banner-resolved .review-banner-icon {
    background: #bbf7d0;
    color: #15803d;
}
.review-banner-icon svg {
    width: 20px;
    height: 20px;
}

/* ═══════════════════════════════════════════════════════
   DARK THEME OVERRIDES
   ═══════════════════════════════════════════════════════ */
html[data-theme="dark"] .sec-card,
html.dark .sec-card {
    background: #1c1c1f !important;
    border-color: #27272a !important;
}
html[data-theme="dark"] .sec-card-header,
html.dark .sec-card-header {
    border-bottom-color: #27272a !important;
    color: #f8fafc !important;
}
html[data-theme="dark"] .sec-show-title,
html.dark .sec-show-title {
    color: #f8fafc !important;
}
html[data-theme="dark"] .sec-show-subtitle,
html.dark .sec-show-subtitle {
    color: #94a3b8 !important;
}
html[data-theme="dark"] .sec-info-label,
html.dark .sec-info-label {
    color: #94a3b8 !important;
}
html[data-theme="dark"] .sec-info-value,
html.dark .sec-info-value {
    color: #f8fafc !important;
}
html[data-theme="dark"] .sec-info-row,
html.dark .sec-info-row {
    border-bottom-color: #27272a !important;
}
html[data-theme="dark"] .user-hero,
html.dark .user-hero {
    background: #27272a !important;
    border-color: #3f3f46 !important;
}
html[data-theme="dark"] .user-hero-name,
html.dark .user-hero-name {
    color: #f8fafc !important;
}
html[data-theme="dark"] .user-hero-sub,
html.dark .user-hero-sub {
    color: #cbd5e1 !important;
}
html[data-theme="dark"] .tech-box,
html.dark .tech-box {
    background: #27272a !important;
    border-color: #3f3f46 !important;
}
html[data-theme="dark"] .tech-box-label,
html.dark .tech-box-label {
    color: #94a3b8 !important;
}
html[data-theme="dark"] .tech-box-value,
html.dark .tech-box-value {
    color: #f8fafc !important;
}
html[data-theme="dark"] .related-item,
html.dark .related-item {
    background: #27272a !important;
    border-color: #3f3f46 !important;
}
html[data-theme="dark"] .related-item:hover,
html.dark .related-item:hover {
    background: #323236 !important;
}
html[data-theme="dark"] .related-name,
html.dark .related-name {
    color: #f8fafc !important;
}
html[data-theme="dark"] .detail-back-link,
html.dark .detail-back-link {
    background: #27272a !important;
    border-color: #3f3f46 !important;
    color: #fb923c !important;
}
html[data-theme="dark"] .btn-user-action,
html.dark .btn-user-action {
    background: #27272a !important;
    border-color: #3f3f46 !important;
    color: #fb923c !important;
}
html[data-theme="dark"] .review-banner-pending,
html.dark .review-banner-pending {
    background: rgba(245, 158, 11, 0.15) !important;
    border-color: rgba(245, 158, 11, 0.3) !important;
    color: #fde68a !important;
}
html[data-theme="dark"] .review-banner-pending .review-banner-icon,
html.dark .review-banner-pending .review-banner-icon {
    background: rgba(245, 158, 11, 0.25) !important;
    color: #fcd34d !important;
}
html[data-theme="dark"] .review-banner-resolved,
html.dark .review-banner-resolved {
    background: rgba(34, 197, 94, 0.15) !important;
    border-color: rgba(34, 197, 94, 0.3) !important;
    color: #86efac !important;
}
html[data-theme="dark"] .review-banner-resolved .review-banner-icon,
html.dark .review-banner-resolved .review-banner-icon {
    background: rgba(34, 197, 94, 0.25) !important;
    color: #4ade80 !important;
}
html[data-theme="dark"] .sec-type-badge.badge-red,
html.dark .sec-type-badge.badge-red {
    background: rgba(239, 68, 68, 0.2) !important;
    color: #fca5a5 !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
}
html[data-theme="dark"] .sec-type-badge.badge-amber,
html.dark .sec-type-badge.badge-amber {
    background: rgba(245, 158, 11, 0.2) !important;
    color: #fde68a !important;
    border-color: rgba(245, 158, 11, 0.3) !important;
}
html[data-theme="dark"] .sec-type-badge.badge-rose,
html.dark .sec-type-badge.badge-rose {
    background: rgba(244, 63, 94, 0.2) !important;
    color: #fda4af !important;
    border-color: rgba(244, 63, 94, 0.3) !important;
}
html[data-theme="dark"] .sec-type-badge.badge-orange,
html.dark .sec-type-badge.badge-orange {
    background: rgba(234, 88, 12, 0.2) !important;
    color: #fdba74 !important;
    border-color: rgba(234, 88, 12, 0.3) !important;
}
</style>
@endsection

@section('content')
<div class="sec-detail-wrap">

    {{-- Back Navigation --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.security-logs.index') }}" class="detail-back-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>กลับไปหน้ารายการ Security Logs</span>
        </a>
    </div>

    {{-- Header Section --}}
    <div class="sec-show-header">
        <div class="sec-show-header-left">
            <div class="sec-show-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                    <h1 class="sec-show-title">Security Log #{{ $securityLog->id }}</h1>
                    @php
                        $badgeCls = match($securityLog->event_type) {
                            'multi_account_login' => 'badge-red',
                            'suspicious_checkin'  => 'badge-amber',
                            'staff_login_failed'  => 'badge-rose',
                            'rate_limit_exceeded' => 'badge-orange',
                            default               => 'badge-orange',
                        };
                    @endphp
                    <span class="sec-type-badge {{ $badgeCls }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        {{ $securityLog->event_type_label }}
                    </span>
                </div>
                <p class="sec-show-subtitle">
                    บันทึกเมื่อ {{ $securityLog->created_at->format('d/m/Y H:i:s') }}
                    <span style="opacity:0.75;">({{ $securityLog->created_at->diffForHumans() }})</span>
                </p>
            </div>
        </div>

        <div class="sec-show-header-right">
            @if($securityLog->is_reviewed)
                <div class="sec-reviewed-badge">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span>ตรวจสอบแล้ว</span>
                </div>
            @else
                <button type="button" class="btn-quick-review" id="btnTopReview" onclick="markReviewed({{ $securityLog->id }})">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>ทำเครื่องหมายว่าตรวจสอบแล้ว</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Grid 1: Incident Info & User Involved --}}
    <div class="sec-grid-2">

        {{-- Card 1: Incident Info --}}
        <div class="sec-card" style="margin-bottom:0;">
            <div class="sec-card-header">
                <div class="sec-card-header-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>ข้อมูลเหตุการณ์</span>
                </div>
                <span style="font-size:.75rem;font-weight:600;color:var(--text-muted, #64748b);">ID: #{{ $securityLog->id }}</span>
            </div>
            <div class="sec-card-body">
                <div class="sec-info-row">
                    <span class="sec-info-label">ประเภทเหตุการณ์</span>
                    <span class="sec-info-value">{{ $securityLog->event_type_label }}</span>
                </div>
                <div class="sec-info-row">
                    <span class="sec-info-label">เวลาที่เกิดเหตุ</span>
                    <span class="sec-info-value">{{ $securityLog->created_at->format('d/m/Y H:i:s') }}</span>
                </div>
                <div class="sec-info-row">
                    <span class="sec-info-label">IP Address</span>
                    <span class="sec-info-value mono">
                        {{ $securityLog->ip_address ?? '—' }}
                        @if($securityLog->ip_address)
                            <button type="button" class="btn-copy-chip" onclick="copyText('{{ $securityLog->ip_address }}', this)" title="คัดลอก IP">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        @endif
                    </span>
                </div>
                <div class="sec-info-row">
                    <span class="sec-info-label">Device Hash</span>
                    <span class="sec-info-value mono" style="font-size:.76rem;">
                        {{ $securityLog->device_fingerprint ?? '—' }}
                        @if($securityLog->device_fingerprint)
                            <button type="button" class="btn-copy-chip" onclick="copyText('{{ $securityLog->device_fingerprint }}', this)" title="คัดลอก Fingerprint">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        @endif
                    </span>
                </div>
                <div class="sec-info-row">
                    <span class="sec-info-label">สถานะการตรวจสอบ</span>
                    <span class="sec-info-value">
                        @if($securityLog->is_reviewed)
                            <span style="color:#16a34a;font-weight:700;">ตรวจสอบแล้ว</span>
                        @else
                            <span style="color:#d97706;font-weight:700;">รอการตรวจสอบ</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Card 2: User Involved (Dynamic: Student vs Staff vs Admin vs None) --}}
        <div class="sec-card" style="margin-bottom:0;">
            <div class="sec-card-header">
                <div class="sec-card-header-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>ผู้ใช้ที่เกี่ยวข้อง</span>
                </div>
                @if($securityLog->user)
                    @php
                        $role = $securityLog->user->role;
                        $roleClass = match($role) {
                            'admin'   => 'user-role-admin',
                            'staff'   => 'user-role-staff',
                            default   => 'user-role-student',
                        };
                        $roleLabel = match($role) {
                            'admin'   => 'ผู้ดูแลระบบ',
                            'staff'   => 'เจ้าหน้าที่',
                            default   => 'นักศึกษา',
                        };
                    @endphp
                    <span class="user-role-badge {{ $roleClass }}">{{ $roleLabel }}</span>
                @endif
            </div>
            <div class="sec-card-body">
                @if($securityLog->user)
                    <div class="user-hero">
                        <x-avatar :user="$securityLog->user" size="42" />
                        <div class="user-hero-details">
                            <div class="user-hero-name">
                                <span>{{ $securityLog->user->full_name }}</span>
                            </div>
                            <div class="user-hero-sub">{{ $securityLog->user->email }}</div>
                        </div>
                    </div>

                    @if($securityLog->user->role === 'student')
                        <div class="sec-info-row">
                            <span class="sec-info-label">รหัสนักศึกษา</span>
                            <span class="sec-info-value mono">{{ $securityLog->user->student_id ?? '—' }}</span>
                        </div>
                        <div class="sec-info-row">
                            <span class="sec-info-label">คณะ</span>
                            <span class="sec-info-value">{{ $securityLog->user->faculty ?? '—' }}</span>
                        </div>
                        <div class="sec-info-row">
                            <span class="sec-info-label">สาขาวิชา</span>
                            <span class="sec-info-value">{{ $securityLog->user->department ?? '—' }}</span>
                        </div>
                        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                            <a href="{{ route('admin.students.show', $securityLog->user->id) }}" class="btn-user-action">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                <span>ดูประวัตินักศึกษา</span>
                            </a>
                        </div>
                    @else
                        <div class="sec-info-row">
                            <span class="sec-info-label">ตำแหน่ง</span>
                            <span class="sec-info-value">{{ $securityLog->user->position ?? '—' }}</span>
                        </div>
                        <div class="sec-info-row">
                            <span class="sec-info-label">สังกัด / หน่วยงาน</span>
                            <span class="sec-info-value">{{ $securityLog->user->organization ?? ($securityLog->user->faculty ?? '—') }}</span>
                        </div>
                        <div class="sec-info-row">
                            <span class="sec-info-label">สถานะบัญชี</span>
                            <span class="sec-info-value">
                                @if($securityLog->user->is_active)
                                    <span style="color:#16a34a;font-weight:700;">เปิดใช้งานปกติ</span>
                                @else
                                    <span style="color:#dc2626;font-weight:700;">ระงับการใช้งาน</span>
                                @endif
                            </span>
                        </div>
                        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                            <a href="{{ route('admin.users.edit', $securityLog->user->id) }}" class="btn-user-action">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span>จัดการบัญชีผู้ใช้</span>
                            </a>
                        </div>
                    @endif
                @else
                    <div style="text-align: center; padding: 1.5rem 1rem;">
                        <div style="width:48px;height:48px;border-radius:50%;background:var(--surface-hover, #f1f5f9);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--text-muted, #64748b);">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:24px;height:24px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <p style="font-weight: 700; color: var(--text-main, #1e293b); margin: 0 0 4px;">ไม่พบข้อมูลบัญชีผู้ใช้</p>
                        <p style="font-size: .8rem; color: var(--text-muted, #64748b); margin: 0;">อาจเป็นการพยายามเข้าสู่ระบบด้วยอีเมลที่ไม่มีในระบบ หรือเป็น Guest Request</p>
                        @if(!empty($securityLog->details['email']))
                            <div style="margin-top: 10px; font-size: .82rem; color: var(--text-main, #334155); font-weight: 600;">
                                อีเมลที่พยายามใช้งาน: <code class="mono" style="color:#ea580c;">{{ $securityLog->details['email'] }}</code>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Technical Details & Payload Summary --}}
    @if($securityLog->details)
    <div class="sec-card">
        <div class="sec-card-header">
            <div class="sec-card-header-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>รายละเอียดทางเทคนิค (Technical Details)</span>
            </div>
            <button type="button" class="btn-copy-chip" onclick="copyJsonPayload()" id="btnCopyJson">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span>คัดลอก JSON</span>
            </button>
        </div>
        <div class="sec-card-body">
            @php
                $details = $securityLog->details;
                $reasonMap = [
                    'wrong_password'   => 'รหัสผ่านไม่ถูกต้อง',
                    'user_not_found'   => 'ไม่พบบัญชีผู้ใช้ในระบบ',
                    'account_inactive' => 'บัญชีถูกระงับการใช้งาน',
                    'rate_limit'       => 'ส่งคำขอถี่เกินขีดจำกัด',
                ];
            @endphp

            {{-- Summary Chips for Known Attributes --}}
            <div class="tech-details-grid">
                @if(!empty($details['email']))
                    <div class="tech-box">
                        <div class="tech-box-label">Email Attempted</div>
                        <div class="tech-box-value">{{ $details['email'] }}</div>
                    </div>
                @endif
                @if(!empty($details['reason']))
                    <div class="tech-box">
                        <div class="tech-box-label">Reason / สาเหตุ</div>
                        <div class="tech-box-value">
                            <span style="color:#dc2626;">{{ $reasonMap[$details['reason']] ?? $details['reason'] }}</span>
                        </div>
                    </div>
                @endif
                @if(!empty($details['method']) || !empty($details['url']))
                    <div class="tech-box">
                        <div class="tech-box-label">HTTP Request</div>
                        <div class="tech-box-value" style="font-size:.82rem;">
                            @if(!empty($details['method']))
                                <span style="background:#ea580c;color:#fff;padding:1px 6px;border-radius:4px;font-size:.72rem;font-weight:700;margin-right:4px;">{{ $details['method'] }}</span>
                            @endif
                            {{ $details['url'] ?? '—' }}
                        </div>
                    </div>
                @endif
                @if(!empty($details['message']))
                    <div class="tech-box">
                        <div class="tech-box-label">System Message</div>
                        <div class="tech-box-value">{{ $details['message'] }}</div>
                    </div>
                @endif
            </div>

            @if(!empty($details['user_agent']))
                <div class="tech-box" style="margin-bottom:1.25rem;">
                    <div class="tech-box-label">User Agent</div>
                    <div class="tech-box-value mono" style="font-size:.76rem;color:var(--text-muted, #64748b);">
                        {{ $details['user_agent'] }}
                    </div>
                </div>
            @endif

            {{-- Formatted JSON Block --}}
            <div class="json-container">
                <div class="json-container-header">
                    <span style="display:inline-flex;align-items:center;gap:6px;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        Payload Data (JSON)
                    </span>
                    <span style="font-family:monospace;font-size:.7rem;opacity:.75;">raw</span>
                </div>
                <pre class="json-raw-content" id="jsonPayloadText">{{ json_encode($securityLog->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
    @endif

    {{-- Related Accounts --}}
    @if($relatedUsers->isNotEmpty())
    <div class="sec-card">
        <div class="sec-card-header">
            <div class="sec-card-header-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                <span>บัญชีอื่นที่เกี่ยวข้อง (จากอุปกรณ์หรือ IP เดียวกัน)</span>
            </div>
            <span style="font-size:.8rem;font-weight:700;color:var(--text-muted, #64748b);">{{ $relatedUsers->count() }} บัญชี</span>
        </div>
        <div class="sec-card-body">
            <div class="related-list">
                @foreach($relatedUsers as $ru)
                <div class="related-item">
                    <x-avatar :user="$ru" size="36" />
                    <div class="related-details">
                        <div class="related-name">{{ $ru->full_name }}</div>
                        <div class="related-meta">
                            @if($ru->role === 'student' && $ru->student_id)
                                {{ $ru->student_id }} · {{ $ru->faculty ?? 'นักศึกษา' }}
                            @else
                                {{ $ru->email }} · {{ $ru->role === 'admin' ? 'ผู้ดูแลระบบ' : 'เจ้าหน้าที่' }}
                            @endif
                        </div>
                    </div>
                    @if($ru->role === 'student')
                        <a href="{{ route('admin.students.show', $ru->id) }}" class="btn-user-action" style="padding:4px 10px;font-size:.78rem;">
                            <span>ดูโปรไฟล์</span>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <a href="{{ route('admin.users.edit', $ru->id) }}" class="btn-user-action" style="padding:4px 10px;font-size:.78rem;">
                            <span>จัดการ</span>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Review Status & Actions Card --}}
    <div class="sec-card">
        <div class="sec-card-header">
            <div class="sec-card-header-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>สถานะการตรวจสอบความปลอดภัย</span>
            </div>
        </div>
        <div class="sec-card-body">
            @if($securityLog->is_reviewed)
                <div class="review-banner review-banner-resolved">
                    <div class="review-banner-content">
                        <div class="review-banner-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">เหตุการณ์นี้ได้รับการตรวจสอบความปลอดภัยแล้ว</div>
                            <div style="font-size:.82rem;opacity:.9;margin-top:2px;">
                                ตรวจสอบเมื่อ {{ $securityLog->reviewed_at?->format('d/m/Y H:i:s') }}
                                @if($securityLog->reviewer)
                                    โดย <strong style="font-weight:700;">{{ $securityLog->reviewer->full_name }}</strong>
                                @endif
                            </div>
                        </div>
                    </div>
                    <span style="font-size:.78rem;font-weight:700;padding:4px 10px;background:rgba(22,101,52,0.12);border-radius:6px;">VERIFIED</span>
                </div>
            @else
                <div class="review-banner review-banner-pending">
                    <div class="review-banner-content">
                        <div class="review-banner-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">เหตุการณ์นี้ยังไม่ได้รับการตรวจสอบความปลอดภัย</div>
                            <div style="font-size:.82rem;opacity:.9;margin-top:2px;">
                                หากตรวจสอบพฤติกรรมแล้วพบว่าเป็นปกติหรือได้รับการแก้ไขแล้ว ให้กดปุ่มด้านล่างเพื่อยืนยัน
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-quick-review" id="btnBottomReview" onclick="markReviewed({{ $securityLog->id }})" style="padding:10px 20px;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>ทำเครื่องหมายว่าตรวจสอบแล้ว</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

async function markReviewed(id) {
    const btnTop = document.getElementById('btnTopReview');
    const btnBottom = document.getElementById('btnBottomReview');
    if (btnTop) { btnTop.disabled = true; btnTop.style.opacity = '0.6'; }
    if (btnBottom) { btnBottom.disabled = true; btnBottom.style.opacity = '0.6'; }

    try {
        const res = await fetch(`/admin/security-logs/${id}/review`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        if (res.ok) {
            location.reload();
        } else {
            alert('เกิดข้อผิดพลาด ไม่สามารถบันทึกสถานะได้');
            if (btnTop) { btnTop.disabled = false; btnTop.style.opacity = '1'; }
            if (btnBottom) { btnBottom.disabled = false; btnBottom.style.opacity = '1'; }
        }
    } catch (e) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        if (btnTop) { btnTop.disabled = false; btnTop.style.opacity = '1'; }
        if (btnBottom) { btnBottom.disabled = false; btnBottom.style.opacity = '1'; }
    }
}

function copyText(text, btn) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<svg fill="none" stroke="#16a34a" viewBox="0 0 24 24" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>`;
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 1500);
    });
}

function copyJsonPayload() {
    const text = document.getElementById('jsonPayloadText')?.innerText || '';
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btnCopyJson');
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = `<svg fill="none" stroke="#16a34a" viewBox="0 0 24 24" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg><span>คัดลอกแล้ว</span>`;
            setTimeout(() => {
                btn.innerHTML = original;
            }, 1800);
        }
    });
}
</script>
@endsection

