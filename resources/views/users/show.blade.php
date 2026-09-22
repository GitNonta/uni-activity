{{-- หน้าโปรไฟล์สาธารณะของผู้ใช้: ข้อมูล + สถิติ + ผลงานล่าสุด + ปุ่มติดตาม (UI แบบไร้ Box เรียบร้อย มินิมอล) --}}
@extends('layouts.app')
@section('title', 'โปรไฟล์ — ' . ($profileUser->full_name ?? 'ผู้ใช้'))

@section('content')

<style>
.pub-profile-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 1.25rem 0.75rem 4rem 0.75rem;
}

/* ── Hero Profile Header (ไร้ Box คลีน เรียบหรู) ── */
.pub-hero-wrap {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    padding-bottom: 1.75rem;
}
@media (max-width: 640px) {
    .pub-hero-wrap {
        flex-direction: column;
        align-items: stretch;
        gap: 1.25rem;
    }
}

.pub-hero-main {
    display: flex;
    align-items: center;
    gap: 1.35rem;
    flex: 1;
    min-width: 0;
}
@media (max-width: 480px) {
    .pub-hero-main {
        align-items: flex-start;
        gap: 1rem;
    }
}

.pub-avatar-wrap {
    flex-shrink: 0;
}

.pub-user-meta {
    flex: 1;
    min-width: 0;
}

.pub-name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pub-name {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.02em;
}
html[data-theme="dark"] .pub-name,
html.dark .pub-name {
    color: #f8fafc;
}

.pub-role-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(234, 88, 12, 0.1);
    color: #ea580c;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}
html[data-theme="dark"] .pub-role-tag,
html.dark .pub-role-tag {
    background: rgba(234, 88, 12, 0.2);
    color: #fb923c;
}

.pub-english-name {
    color: #64748b;
    font-size: 0.95rem;
    font-weight: 500;
    margin: 0.25rem 0 0.5rem 0;
    line-height: 1.3;
}
html[data-theme="dark"] .pub-english-name,
html.dark .pub-english-name {
    color: #94a3b8;
}

.pub-meta-line {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
    margin-top: 0.35rem;
}
html[data-theme="dark"] .pub-meta-line,
html.dark .pub-meta-line {
    color: #94a3b8;
}

.pub-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.65rem;
    background: rgba(148, 163, 184, 0.12);
    border-radius: 999px;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 600;
}
html[data-theme="dark"] .pub-meta-pill,
html.dark .pub-meta-pill {
    background: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
}

/* ── ปุ่มติดตาม / จัดการโปรไฟล์ ── */
.pub-action-wrap {
    flex-shrink: 0;
}
@media (max-width: 640px) {
    .pub-action-wrap {
        width: 100%;
    }
    .pub-action-wrap .follow-btn,
    .pub-action-wrap .pub-manage-btn {
        width: 100%;
    }
}

.follow-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.55rem 1.4rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.88rem;
    border: 1.5px solid #ea580c;
    cursor: pointer;
    transition: all 0.2s ease;
    line-height: 1.5;
    min-width: 126px;
    text-decoration: none;
}
.follow-btn.is-following {
    background: transparent;
    color: #ea580c;
    border-color: rgba(234, 88, 12, 0.4);
}
.follow-btn.is-following:hover {
    background: rgba(234, 88, 12, 0.08);
    border-color: #c2410c;
    color: #c2410c;
}
.follow-btn.not-following {
    background: #ea580c;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);
}
.follow-btn.not-following:hover {
    background: #c2410c;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.35);
}
html[data-theme="dark"] .follow-btn.is-following,
html.dark .follow-btn.is-following {
    color: #fb923c !important;
    border-color: rgba(251, 146, 60, 0.4) !important;
}
html[data-theme="dark"] .follow-btn.is-following:hover,
html.dark .follow-btn.is-following:hover {
    background: rgba(251, 146, 60, 0.12) !important;
    border-color: #ea580c !important;
}

.pub-manage-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.55rem 1.35rem;
    background: transparent;
    color: #ea580c;
    border: 1.5px solid rgba(234, 88, 12, 0.4);
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.88rem;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}
.pub-manage-btn:hover {
    background: rgba(234, 88, 12, 0.08);
    border-color: #ea580c;
}
html[data-theme="dark"] .pub-manage-btn,
html.dark .pub-manage-btn {
    color: #fb923c !important;
    border-color: rgba(251, 146, 60, 0.4) !important;
}
html[data-theme="dark"] .pub-manage-btn:hover,
html.dark .pub-manage-btn:hover {
    background: rgba(251, 146, 60, 0.12) !important;
}

/* ── Stats Bar (Minimalist Row) ── */
.pub-stats-bar {
    display: flex;
    align-items: center;
    justify-content: space-evenly;
    padding: 1.15rem 0;
    border-top: 1px solid rgba(226, 232, 240, 0.6);
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
}
@media (min-width: 640px) {
    .pub-stats-bar {
        justify-content: center;
        gap: 4.5rem;
    }
}
html[data-theme="dark"] .pub-stats-bar,
html.dark .pub-stats-bar {
    border-top-color: rgba(39, 39, 42, 0.7);
    border-bottom-color: rgba(39, 39, 42, 0.7);
}

.pub-stat-unit {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.2rem;
    cursor: default;
    transition: transform 0.15s ease, opacity 0.15s ease;
    min-width: 72px;
}
.pub-stat-unit.clickable {
    cursor: pointer;
}
.pub-stat-unit.clickable:hover {
    transform: translateY(-1px);
}
.pub-stat-unit.clickable:hover .pub-stat-num,
.pub-stat-unit.clickable:hover .pub-stat-label {
    color: #ea580c;
}
.pub-stat-num {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    line-height: 1.1;
    letter-spacing: -0.02em;
    transition: color 0.15s ease;
}
html[data-theme="dark"] .pub-stat-num,
html.dark .pub-stat-num {
    color: #f8fafc;
}
html[data-theme="dark"] .pub-stat-unit.clickable:hover .pub-stat-num,
html.dark .pub-stat-unit.clickable:hover .pub-stat-num,
html[data-theme="dark"] .pub-stat-unit.clickable:hover .pub-stat-label,
html.dark .pub-stat-unit.clickable:hover .pub-stat-label {
    color: #fb923c;
}
.pub-stat-label {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
    line-height: 1.2;
    transition: color 0.15s ease;
}
html[data-theme="dark"] .pub-stat-label,
html.dark .pub-stat-label {
    color: #94a3b8;
}

/* ── Posts Section (Clean Feed List) ── */
.pub-section {
    padding-top: 1.75rem;
}

.pub-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}

.pub-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0;
    letter-spacing: -0.01em;
}
html[data-theme="dark"] .pub-section-title,
html.dark .pub-section-title {
    color: #f8fafc;
}

.pub-posts-feed {
    display: block;
}

/* ── แท็บไอคอนสไตล์ Instagram: กิจกรรม / ข่าวประกาศ / ประกาศงาน ── */
.pub-feed-tabs {
    display: flex;
    border-bottom: 1px solid rgba(148, 163, 184, 0.25);
    margin-bottom: 0.35rem;
}
html[data-theme="dark"] .pub-feed-tabs,
html.dark .pub-feed-tabs {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.pub-feed-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.6rem 0.25rem 0.55rem;
    margin-bottom: -1px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-family: inherit;
    color: #94a3b8;
    transition: color 0.15s ease, border-color 0.15s ease;
}
html[data-theme="dark"] .pub-feed-tab,
html.dark .pub-feed-tab {
    color: #64748b;
}
.pub-feed-tab:hover {
    color: #64748b;
}
html[data-theme="dark"] .pub-feed-tab:hover,
html.dark .pub-feed-tab:hover {
    color: #94a3b8;
}
.pub-feed-tab.is-active {
    color: var(--tab-color, #1e293b);
    border-bottom-color: var(--tab-color, #1e293b);
}

.pub-feed-tab-icon {
    display: inline-flex;
}
.pub-feed-tab-icon svg {
    width: 20px;
    height: 20px;
}

.pub-feed-tab-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    line-height: 1.3;
}
.pub-feed-tab.is-active .pub-feed-tab-label {
    font-weight: 700;
}

.pub-feed-tab-count {
    font-size: 0.62rem;
    font-weight: 700;
    color: #94a3b8;
    background: rgba(148, 163, 184, 0.14);
    border-radius: 999px;
    padding: 0 0.4rem;
    line-height: 1.5;
}

.pub-feed-panel {
    display: none;
    flex-direction: column;
    gap: 0.15rem;
}
.pub-feed-panel.is-active {
    display: flex;
}

.pub-feed-panel-empty {
    padding: 1.4rem 0.5rem;
    text-align: center;
    font-size: 0.8rem;
    color: #94a3b8;
    line-height: 1.5;
}

.pub-post-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.55rem;
    border-radius: 10px;
    text-decoration: none;
    color: inherit;
    transition: all 0.18s ease;
}
.pub-post-row:hover {
    background: rgba(148, 163, 184, 0.08);
    transform: translateX(4px);
}
html[data-theme="dark"] .pub-post-row:hover,
html.dark .pub-post-row:hover {
    background: rgba(255, 255, 255, 0.05);
}



.pub-post-title {
    flex: 1;
    min-width: 0;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-main, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
    transition: color 0.15s ease;
}
html[data-theme="dark"] .pub-post-title,
html.dark .pub-post-title {
    color: #f1f5f9;
}
.pub-post-row:hover .pub-post-title {
    color: #ea580c;
}
html[data-theme="dark"] .pub-post-row:hover .pub-post-title,
html.dark .pub-post-row:hover .pub-post-title {
    color: #fb923c;
}

.pub-post-date {
    flex-shrink: 0;
    font-size: 0.78rem;
    color: #94a3b8;
    line-height: 1.4;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.pub-post-arrow {
    flex-shrink: 0;
    color: #cbd5e1;
    transition: transform 0.15s ease, color 0.15s ease;
}
.pub-post-row:hover .pub-post-arrow {
    color: #ea580c;
    transform: translateX(2px);
}
html[data-theme="dark"] .pub-post-arrow,
html.dark .pub-post-arrow {
    color: #4b5563;
}
html[data-theme="dark"] .pub-post-row:hover .pub-post-arrow,
html.dark .pub-post-row:hover .pub-post-arrow {
    color: #fb923c;
}

/* ── Modal รายชื่อผู้ติดตาม ── */
.follower-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 9998;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
.follower-modal-backdrop.open {
    display: flex;
}
.follower-modal {
    width: min(92vw, 390px);
    max-height: 70vh;
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
    animation: followerModalIn 0.2s ease-out;
}
@keyframes followerModalIn {
    from { opacity: 0; transform: translateY(12px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.follower-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.1rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid rgba(241, 245, 249, 0.8);
    transition: background 0.15s ease;
}
.follower-row:last-child {
    border-bottom: none;
}
.follower-row:hover {
    background: #f8fafc;
}
html[data-theme="dark"] .follower-modal,
html.dark .follower-modal {
    background: #18181b !important;
}
html[data-theme="dark"] .follower-row,
html.dark .follower-row {
    border-bottom-color: #27272a !important;
}
html[data-theme="dark"] .follower-row:hover,
html.dark .follower-row:hover {
    background: #27272a !important;
}
</style>

<div class="pub-profile-container">

    {{-- ── Hero Section (ไร้ Box คลีน เรียบหรู จัดเรียงครบจบในที่เดียว) ── --}}
    <div class="pub-hero-wrap">
        <div class="pub-hero-main">
            <div class="pub-avatar-wrap">
                <x-avatar :user="$profileUser" size="88" style="border: 2px solid rgba(148, 163, 184, 0.25); box-shadow: 0 2px 8px rgba(0,0,0,0.08);" />
            </div>
            <div class="pub-user-meta">
                <div class="pub-name-row">
                    <h1 class="pub-name">{{ $profileUser->full_name }}</h1>
                    @if($profileUser->isStaffOrAdmin())
                        <span class="pub-role-tag">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="#06c755" aria-label="ผู้จัดกิจกรรมทางการ" title="ผู้จัดกิจกรรมทางการ">
                                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
                            </svg>
                            {{ $profileUser->isStaff() ? 'เจ้าหน้าที่' : 'ผู้ดูแลระบบ' }}
                        </span>
                    @endif
                </div>

                @if($profileUser->english_name)
                    <p class="pub-english-name">{{ $profileUser->english_name }}</p>
                @endif

                {{-- รายละเอียดสังกัด / ตำแหน่ง / คณะ / สาขา / ชั้นปี --}}
                <div class="pub-meta-line">
                    @if($profileUser->isStaffOrAdmin())
                        @if($profileUser->position || $profileUser->organization)
                            <span class="pub-meta-pill">
                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                {{ $profileUser->position ?? $profileUser->organization }}
                            </span>
                        @endif
                    @else
                        @if($profileUser->year)
                            <span class="pub-meta-pill">
                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                ปี {{ $profileUser->year }}
                            </span>
                        @endif
                        @if($profileUser->program)
                            <span class="pub-meta-pill">
                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                                {{ $profileUser->program }}
                            </span>
                        @endif
                    @endif

                    @if($profileUser->faculty)
                        <span class="pub-meta-pill">{{ $profileUser->faculty }}</span>
                    @endif
                    @if($profileUser->department)
                        <span class="pub-meta-pill">{{ $profileUser->department }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- ปุ่มติดตาม / จัดการโปรไฟล์ --}}
        <div class="pub-action-wrap">
            @if($viewerIsSelf)
                <a href="{{ route('student.profile') }}" class="pub-manage-btn">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    จัดการโปรไฟล์ของฉัน
                </a>
            @elseif($viewer !== null)
                <button id="followBtn"
                    class="follow-btn {{ $isFollowing ? 'is-following' : 'not-following' }}"
                    data-user-id="{{ $profileUser->id }}"
                    data-is-following="{{ $isFollowing ? '1' : '0' }}"
                    data-follow-url="{{ route('users.follow', $profileUser) }}"
                    data-unfollow-url="{{ route('users.unfollow', $profileUser) }}">
                    <svg id="followBtnIcon" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($isFollowing)
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        @endif
                    </svg>
                    <span id="followBtnText">{{ $isFollowing ? 'ติดตามอยู่' : 'ติดตาม' }}</span>
                </button>
            @else
                <a href="{{ route('login') }}" class="follow-btn not-following" style="text-decoration: none;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    ติดตาม
                </a>
            @endif
        </div>
    </div>

    {{-- ── แถบสถิติ (Social Style Minimalist Counters) ── --}}
    <div class="pub-stats-bar">
        <div class="pub-stat-unit clickable" onclick="openFollowerModal('followers')" title="ดูรายชื่อผู้ติดตาม">
            <span class="pub-stat-num" id="followersCount">{{ number_format($followersCount) }}</span>
            <span class="pub-stat-label">ผู้ติดตาม</span>
        </div>
        <div class="pub-stat-unit clickable" onclick="openFollowerModal('following')" title="ดูรายชื่อที่กำลังติดตาม">
            <span class="pub-stat-num">{{ number_format($followingCount) }}</span>
            <span class="pub-stat-label">กำลังติดตาม</span>
        </div>
        <div class="pub-stat-unit">
            <span class="pub-stat-num">{{ number_format($stats['total']) }}</span>
            <span class="pub-stat-label">ผลงานที่โพสต์</span>
        </div>
    </div>

    {{-- ── ผลงานและกิจกรรมที่เผยแพร่ (ไร้ Card Box สะอาดตา) ── --}}
    <div class="pub-section">
        <div class="pub-section-header">
            <h2 class="pub-section-title">
                <svg width="19" height="19" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                ผลงานและกิจกรรมที่เผยแพร่
            </h2>
        </div>
        <div class="pub-posts-feed">
            @php
                $postsByType = $posts->groupBy('type');
                $defaultFeedType = collect(['activity', 'announcement', 'job'])->first(fn ($t) => $postsByType->get($t, collect())->isNotEmpty()) ?? 'activity';
                $feedGroups = [
                    'activity' => [
                        'label' => 'กิจกรรม',
                        'color' => '#2563eb',
                        'icon'  => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                    ],
                    'announcement' => [
                        'label' => 'ข่าวประกาศ',
                        'color' => '#7c3aed',
                        'icon'  => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>',
                    ],
                    'job' => [
                        'label' => 'ประกาศงาน',
                        'color' => '#ea580c',
                        'icon'  => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                    ],
                ];
            @endphp
            @if ($posts->isNotEmpty())
            <div class="pub-feed-tabs" role="tablist">
                @foreach ($feedGroups as $type => $group)
                    <button type="button" class="pub-feed-tab {{ $type === $defaultFeedType ? 'is-active' : '' }}"
                            data-feed-tab="{{ $type }}" role="tab"
                            aria-selected="{{ $type === $defaultFeedType ? 'true' : 'false' }}"
                            style="--tab-color: {{ $group['color'] }};"
                            title="{{ $group['label'] }}">
                        <span class="pub-feed-tab-icon">{!! $group['icon'] !!}</span>
                        <span class="pub-feed-tab-label">{{ $group['label'] }} <span class="pub-feed-tab-count">{{ $postsByType->get($type, collect())->count() }}</span></span>
                    </button>
                @endforeach
            </div>
            @foreach ($feedGroups as $type => $group)
                @php $groupPosts = $postsByType->get($type, collect()); @endphp
                <div class="pub-feed-panel {{ $type === $defaultFeedType ? 'is-active' : '' }}" data-feed-panel="{{ $type }}" role="tabpanel">
                    @if ($groupPosts->isEmpty())
                        <div class="pub-feed-panel-empty">ยังไม่มี{{ $group['label'] }}</div>
                    @else
                        @foreach ($groupPosts as $post)
                            <a href="{{ $post['url'] }}" class="pub-post-row">
                                <span class="pub-post-title">{{ $post['title'] }}</span>
                                <span class="pub-post-date">
                                    @if($post['date'])
                                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        {{ \Illuminate\Support\Carbon::parse($post['date'])->format('d/m/Y') }}
                                    @endif
                                </span>
                                <svg class="pub-post-arrow" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    @endif
                </div>
            @endforeach
            @endif
            @if ($posts->isEmpty())
                <x-empty-state
                    icon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    title="ยังไม่มีผลงานที่เผยแพร่"
                    description="{{ $profileUser->isStaffOrAdmin() ? 'ผู้ใช้นี้ยังไม่เคยเผยแพร่กิจกรรมหรือประกาศงาน' : 'กดติดตามเพื่อรับข่าวสารจากผู้ใช้นี้ในอนาคต' }}"
                    size="sm" />
            @endif
        </div>
    </div>

</div>

{{-- ── Modal: รายชื่อผู้ติดตาม / กำลังติดตาม ── --}}
<div id="followerModal" class="follower-modal-backdrop" onclick="if(event.target === this) closeFollowerModal()">
    <div class="follower-modal">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.9rem 1.15rem;border-bottom:1px solid rgba(226, 232, 240, 0.8);flex-shrink:0;">
            <h3 id="followerModalTitle" style="margin:0;font-size:1rem;font-weight:700;color:var(--text-main,#1e293b);line-height:1.5;">รายชื่อ</h3>
            <button onclick="closeFollowerModal()" style="background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;color:#64748b;" aria-label="ปิด">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="followerModalBody" style="overflow-y:auto;flex:1;">
            <div style="padding:1.5rem;text-align:center;font-size:0.83rem;color:#64748b;line-height:1.5;">กำลังโหลด...</div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var followBtn = document.getElementById('followBtn');

    // ── แท็บโพสต์สไตล์ Instagram: สลับแผงตามประเภท (กิจกรรม/ข่าวประกาศ/ประกาศงาน) ──
    document.querySelectorAll('.pub-feed-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var type = tab.getAttribute('data-feed-tab');
            document.querySelectorAll('.pub-feed-tab').forEach(function (t) {
                var on = t === tab;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            document.querySelectorAll('.pub-feed-panel').forEach(function (p) {
                p.classList.toggle('is-active', p.getAttribute('data-feed-panel') === type);
            });
        });
    });

    // ── Escape ข้อความก่อนใส่ลง innerHTML ทุกครั้ง (ป้องกัน XSS จากชื่อผู้ใช้) ──
    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ── ปุ่มติดตาม / เลิกติดตาม (fetch + optimistic UI) ──
    if (followBtn) {
        followBtn.addEventListener('click', function () {
            var isFollowing = followBtn.dataset.isFollowing === '1';
            var url = isFollowing ? followBtn.dataset.unfollowUrl : followBtn.dataset.followUrl;
            var icon = document.getElementById('followBtnIcon');
            var text = document.getElementById('followBtnText');

            followBtn.disabled = true;

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'เกิดข้อผิดพลาด');
                followBtn.dataset.isFollowing = data.is_following ? '1' : '0';
                followBtn.classList.toggle('is-following', !!data.is_following);
                followBtn.classList.toggle('not-following', !data.is_following);
                if (text) text.textContent = data.is_following ? 'ติดตามอยู่' : 'ติดตาม';
                if (icon) {
                    icon.innerHTML = data.is_following
                        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
                        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>';
                }
                var countEl = document.getElementById('followersCount');
                if (countEl && typeof data.followers_count === 'number') {
                    countEl.textContent = data.followers_count.toLocaleString();
                }
            })
            .catch(function (err) {
                alert(err.message || 'ไม่สามารถติดตามได้ในขณะนี้');
            })
            .finally(function () {
                followBtn.disabled = false;
            });
        });
    }

    // ── Modal รายชื่อผู้ติดตาม / กำลังติดตาม ──
    window.openFollowerModal = function (kind) {
        var modal = document.getElementById('followerModal');
        var title = document.getElementById('followerModalTitle');
        var body = document.getElementById('followerModalBody');
        var url = kind === 'following'
            ? '{{ route('users.following', $profileUser) }}'
            : '{{ route('users.followers', $profileUser) }}';

        if (title) title.textContent = kind === 'following' ? 'กำลังติดตาม' : 'ผู้ติดตาม';
        body.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:0.83rem;color:#64748b;line-height:1.5;">กำลังโหลด...</div>';
        modal.classList.add('open');

        fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var list = (kind === 'following' ? data.following : data.followers) || [];
                if (!list.length) {
                    body.innerHTML = '<div style="padding:2rem 1rem;text-align:center;font-size:0.83rem;color:#64748b;line-height:1.5;">ยังไม่มีรายชื่อ</div>';
                    return;
                }
                body.innerHTML = list.map(function (u) {
                    var name = escHtml(u.name);
                    var roleLabel = escHtml(u.role === 'admin' ? 'ผู้ดูแลระบบ' : (u.role === 'staff' ? 'เจ้าหน้าที่' : 'นักศึกษา'));
                    var safeUrl = escHtml(u.url || '#');
                    var avatar = u.photo
                        ? '<img src="' + escHtml(u.photo) + '" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;">'
                        : '<div style="width:38px;height:38px;border-radius:50%;background:#ffedd5;color:#c2410c;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">' + escHtml((u.name || '?').charAt(0)) + '</div>';
                    return '<a href="' + safeUrl + '" class="follower-row">' + avatar
                        + '<div style="flex:1;min-width:0;"><div style="font-size:0.87rem;font-weight:600;color:var(--text-main,#1e293b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.5;">' + name + '</div>'
                        + '<div style="font-size:0.7rem;color:#94a3b8;line-height:1.5;">' + roleLabel + '</div></div>'
                        + '<svg width="14" height="14" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>'
                        + '</a>';
                }).join('');
            })
            .catch(function () {
                body.innerHTML = '<div style="padding:2rem 1rem;text-align:center;font-size:0.83rem;color:#b91c1c;line-height:1.5;">ไม่สามารถโหลดรายชื่อได้</div>';
            });
    };

    window.closeFollowerModal = function () {
        var modal = document.getElementById('followerModal');
        if (modal) modal.classList.remove('open');
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeFollowerModal();
    });
})();
</script>
@endpush
