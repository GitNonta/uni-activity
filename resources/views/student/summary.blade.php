{{-- หน้าสรุปชั่วโมงกิจกรรม: ชั่วโมงรวม + แยกตามหมวดหมู่แบบไร้ Box/Card สะอาดตา --}}
@extends('layouts.app')
@section('title', 'สรุปกิจกรรม')

@section('content')
<style>
/* ── ชั่วโมงแยกตามหมวดหมู่ (แบบไร้ Card / Box สะอาดตา) ── */
.cat-summary-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}
.cat-summary-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}
.cat-summary-list {
    display: flex;
    flex-direction: column;
    width: 100%;
}
.cat-summary-item {
    padding: 0.95rem 0.25rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    transition: background-color 0.15s ease;
}
.cat-summary-item:last-child {
    border-bottom: none;
}
.cat-summary-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.45rem;
}
.cat-summary-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-main, #1e293b);
}
.cat-summary-pct {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    letter-spacing: 0.02em;
}
.cat-summary-pct.is-complete {
    background: #dcfce7;
    color: #15803d;
}
.cat-progress-track {
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    position: relative;
    margin-bottom: 0.45rem;
}
.cat-progress-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.5s ease;
}
.cat-progress-bar.primary {
    background: linear-gradient(90deg, #ea580c, #f97316);
}
.cat-progress-bar.yellow {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}
.cat-progress-bar.green {
    background: linear-gradient(90deg, #10b981, #34d399);
}
.cat-summary-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
}
.cat-summary-status {
    color: var(--text-muted, #64748b);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.cat-summary-status.is-pass {
    color: #16a34a;
    font-weight: 600;
}
.cat-summary-hours {
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
}
.cat-summary-hours-val {
    font-weight: 700;
}

/* ── Dark Mode ── */
html[data-theme="dark"] .cat-summary-title,
html.dark .cat-summary-title {
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .cat-summary-item,
html.dark .cat-summary-item {
    border-bottom-color: #27272a !important;
}
html[data-theme="dark"] .cat-summary-name,
html.dark .cat-summary-name {
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .cat-summary-pct,
html.dark .cat-summary-pct {
    background: #27272a !important;
    color: #a1a1aa !important;
}
html[data-theme="dark"] .cat-summary-pct.is-complete,
html.dark .cat-summary-pct.is-complete {
    background: rgba(16, 185, 129, 0.2) !important;
    color: #34d399 !important;
}
html[data-theme="dark"] .cat-progress-track,
html.dark .cat-progress-track {
    background: #27272a !important;
}
html[data-theme="dark"] .cat-summary-status,
html.dark .cat-summary-status {
    color: #a1a1aa !important;
}
html[data-theme="dark"] .cat-summary-status.is-pass,
html.dark .cat-summary-status.is-pass {
    color: #4ade80 !important;
}
html[data-theme="dark"] .cat-summary-hours,
html.dark .cat-summary-hours {
    color: #71717a !important;
}
</style>

{{-- การ์ดสรุปชั่วโมงรวม --}}
<div class="hero-card">
    <div class="flex justify-between items-center" style="margin-bottom:.5rem;">
        <div>
            <p class="hero-label">ชั่วโมงกิจกรรมรวม</p>
            <p class="hero-value">{{ number_format($totalHours, 1) }}</p>
            <p class="hero-sub">จากทั้งหมด {{ $totalRequired }} ชั่วโมงที่กำหนด</p>
        </div>
        <a href="{{ route('student.summary.pdf') }}" class="btn" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);gap:.375rem;">
            <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            ดาวน์โหลด PDF
        </a>
    </div>
</div>

{{-- ชั่วโมงแยกตามหมวดหมู่ (แบบไร้ Card / Box สะอาดตา) --}}
<div class="cat-summary-header">
    <h2 class="cat-summary-title">
        <svg width="20" height="20" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        <span>ชั่วโมงแยกตามหมวดหมู่</span>
    </h2>
</div>

<div class="cat-summary-list">
@forelse($byCategory as $cat)
    @php
        $p = $cat['required'] > 0 ? min(100, ($cat['hours']/$cat['required'])*100) : 0;
        $isComplete = $p >= 100;
    @endphp
    <div class="cat-summary-item">
        <div class="cat-summary-head">
            <span class="cat-summary-name">{{ $cat['name'] }}</span>
            <span class="cat-summary-pct {{ $isComplete ? 'is-complete' : '' }}">
                {{ number_format($p, 0) }}%
            </span>
        </div>
        <div class="cat-progress-track">
            <div class="cat-progress-bar {{ $isComplete ? 'green' : ($p >= 50 ? 'yellow' : 'primary') }}" style="width: {{ $p }}%;"></div>
        </div>
        <div class="cat-summary-foot">
            @if($isComplete)
                <span class="cat-summary-status is-pass">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ผ่านเกณฑ์ขั้นต่ำ
                </span>
            @else
                <span class="cat-summary-status">
                    ต้องการอีก <strong style="color: #ef4444; font-weight: 600;">{{ number_format($cat['required'] - $cat['hours'], 1) }}</strong> ชม.
                </span>
            @endif
            <span class="cat-summary-hours">
                <span class="cat-summary-hours-val" style="color: {{ $isComplete ? '#16a34a' : '#ea580c' }};">{{ number_format($cat['hours'], 1) }}</span>
                <span>/ {{ number_format($cat['required'], 0) }} ชม.</span>
            </span>
        </div>
    </div>
@empty
    <x-empty-state
        icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
        title="ยังไม่มีข้อมูลหมวดหมู่"
        description="เริ่มเข้าร่วมกิจกรรมเพื่อสะสมชั่วโมงในแต่ละหมวดหมู่"
        actionLabel="ดูกิจกรรมทั้งหมด"
        actionUrl="{{ route('activities.index') }}"
        size="md"
    />
@endforelse
</div>
@endsection
