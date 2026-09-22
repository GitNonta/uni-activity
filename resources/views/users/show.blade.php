{{-- หน้าโปรไฟล์สาธารณะของผู้ใช้: ข้อมูล + สถิติ + ผลงานล่าสุด + ปุ่มติดตาม --}}
@extends('layouts.app')
@section('title', 'โปรไฟล์ — ' . ($profileUser->full_name ?? 'ผู้ใช้'))

@section('content')

<style>
.pub-avatar-ring {
    position: relative;
    flex-shrink: 0;
}
.pub-stat-pill {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.65rem 1rem;
    text-align: center;
    flex: 1;
    min-width: 84px;
    cursor: default;
}
.pub-stat-pill.clickable {
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.pub-stat-pill.clickable:hover {
    background: #fff7ed;
    border-color: #fdba74;
}
html[data-theme="dark"] .pub-stat-pill,
html.dark .pub-stat-pill {
    background: #141416 !important;
    border-color: #27272a !important;
}
html[data-theme="dark"] .pub-stat-pill p,
html.dark .pub-stat-pill p {
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .pub-stat-pill.clickable:hover,
html.dark .pub-stat-pill.clickable:hover {
    background: #27272a !important;
    border-color: #ea580c !important;
}

.follow-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 1.4rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.9rem;
    border: 1px solid #ea580c;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1.5;
    min-width: 128px;
}
.follow-btn.is-following {
    background: #ffffff;
    color: #ea580c;
}
.follow-btn.is-following:hover {
    background: #fff7ed;
    border-color: #c2410c;
    color: #c2410c;
}
.follow-btn.not-following {
    background: #ea580c;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(234, 88, 12, 0.3);
}
.follow-btn.not-following:hover {
    background: #c2410c;
}
html[data-theme="dark"] .follow-btn.is-following,
html.dark .follow-btn.is-following {
    background: #1c1c1f !important;
    color: #fb923c !important;
    border-color: #3f3f46 !important;
}
html[data-theme="dark"] .follow-btn.is-following:hover,
html.dark .follow-btn.is-following:hover {
    background: #27272a !important;
    border-color: #ea580c !important;
}

.pub-post-item {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    background: #fafafa;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s;
}
.pub-post-item:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
}
html[data-theme="dark"] .pub-post-item,
html.dark .pub-post-item {
    background: #141416 !important;
    border-color: #27272a !important;
}
html[data-theme="dark"] .pub-post-item:hover,
html.dark .pub-post-item:hover {
    background: #1c1c1f !important;
}

.pub-type-chip {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    color: #ffffff;
    flex-shrink: 0;
}

.pub-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
}
.pub-info-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.7rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
}
html[data-theme="dark"] .pub-info-item,
html.dark .pub-info-item {
    background: #141416 !important;
    border-color: #27272a !important;
}

.pub-manage-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.55rem 1.4rem;
    background: #ffffff;
    color: #c2410c;
    border: 1px solid #fed7aa;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: background 0.2s, border-color 0.2s;
    cursor: pointer;
    line-height: 1.5;
}
.pub-manage-btn:hover {
    background: #fff7ed;
    border-color: #f97316;
}
html[data-theme="dark"] .pub-manage-btn,
html.dark .pub-manage-btn {
    background: #1c1c1f !important;
    border-color: #3f3f46 !important;
    color: #fb923c !important;
}
html[data-theme="dark"] .pub-manage-btn:hover,
html.dark .pub-manage-btn:hover {
    background: #27272a !important;
    border-color: #ea580c !important;
}

.follower-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 9998;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
}
.follower-modal-backdrop.open {
    display: flex;
}
.follower-modal {
    width: min(92vw, 380px);
    max-height: 70vh;
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
    animation: followerModalIn 0.22s ease-out;
}
@keyframes followerModalIn {
    from { opacity: 0; transform: translateY(14px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.follower-row {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.7rem 1rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
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

{{-- ── Hero Card: อวตาร + ชื่อ + ปุ่มติดตาม + สถิติ ── --}}
<div class="card mb-4" style="padding: 1.5rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: center; justify-content: space-between;">

        <div style="display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 250px;">
            <div class="pub-avatar-ring">
                <x-avatar :user="$profileUser" size="84" style="border: 3px solid #f8fafc; box-shadow: 0 2px 6px rgba(0,0,0,0.12);" />
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <h1 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main, #1e293b); margin: 0; line-height: 1.5;">{{ $profileUser->full_name }}</h1>
                    @if($profileUser->isStaffOrAdmin())
                        <span style="display: inline-flex; align-items: center; gap: 3px; background: #ffedd5; color: #c2410c; padding: 0.15rem 0.6rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="#06c755" aria-label="ผู้จัดกิจกรรมทางการ">
                                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79z"/>
                            </svg>
                            {{ $profileUser->isStaff() ? 'เจ้าหน้าที่' : 'ผู้ดูแลระบบ' }}
                        </span>
                    @endif
                </div>
                @if($profileUser->english_name)
                    <p style="color: #64748b; font-size: 0.9rem; font-weight: 600; margin: 0.1rem 0 0.3rem 0; line-height: 1.5;">{{ $profileUser->english_name }}</p>
                @endif
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    @if($profileUser->faculty)
                        <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">{{ $profileUser->faculty }}</span>
                    @endif
                    @if($profileUser->department)
                        <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">{{ $profileUser->department }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- ปุ่มติดตาม / แก้ไขโปรไฟล์ --}}
        <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: stretch;">
            @if($viewerIsSelf)
                <a href="{{ route('student.profile') }}" class="pub-manage-btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
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

    {{-- สถิติ: ผู้ติดตาม / กำลังติดตาม / โพสต์ --}}
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem;">
        <div class="pub-stat-pill clickable" onclick="openFollowerModal('followers')" title="ดูรายชื่อผู้ติดตาม">
            <p style="font-size: 1.25rem; font-weight: 700; color: #c2410c; line-height: 1.5; margin: 0;" id="followersCount">{{ number_format($followersCount) }}</p>
            <p style="font-size: 0.72rem; color: #475569; margin-top: 0.2rem; font-weight: 500; line-height: 1.5;">ผู้ติดตาม</p>
        </div>
        <div class="pub-stat-pill clickable" onclick="openFollowerModal('following')" title="ดูรายชื่อที่กำลังติดตาม">
            <p style="font-size: 1.25rem; font-weight: 700; color: var(--text-main, #1e293b); line-height: 1.5; margin: 0;">{{ number_format($followingCount) }}</p>
            <p style="font-size: 0.72rem; color: #475569; margin-top: 0.2rem; font-weight: 500; line-height: 1.5;">กำลังติดตาม</p>
        </div>
        <div class="pub-stat-pill">
            <p style="font-size: 1.25rem; font-weight: 700; color: var(--text-main, #1e293b); line-height: 1.5; margin: 0;">{{ number_format($stats['total']) }}</p>
            <p style="font-size: 0.72rem; color: #475569; margin-top: 0.2rem; font-weight: 500; line-height: 1.5;">ผลงานที่โพสต์</p>
        </div>
    </div>
</div>

{{-- ── ข้อมูลส่วนตัว (แสดงเฉพาะที่เป็นสาธารณะ) ── --}}
<div class="card mb-4">
    <div class="card-body" style="padding: 1.5rem;">
        <h2 class="font-bold mb-4" style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            ข้อมูลผู้ใช้
        </h2>
        <div class="pub-info-grid">
            <div class="pub-info-item">
                <svg style="flex-shrink:0;" width="18" height="18" fill="none" stroke="#64748b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <div style="min-width:0;">
                    <p style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; margin: 0; line-height: 1.5;">บทบาท</p>
                    <p style="font-size: 0.85rem; font-weight: 600; color: var(--text-main, #334155); margin: 0; line-height: 1.5;">{{ match($profileUser->role) { 'admin' => 'ผู้ดูแลระบบ', 'staff' => 'เจ้าหน้าที่', default => 'นักศึกษา' } }}</p>
                </div>
            </div>
            @if($profileUser->isStaffOrAdmin())
                <div class="pub-info-item">
                    <svg style="flex-shrink:0;" width="18" height="18" fill="none" stroke="#64748b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <div style="min-width:0;">
                        <p style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; margin: 0; line-height: 1.5;">ตำแหน่ง / หน่วยงาน</p>
                        <p style="font-size: 0.85rem; font-weight: 600; color: var(--text-main, #334155); margin: 0; line-height: 1.5;">{{ $profileUser->position ?? $profileUser->organization ?? '-' }}</p>
                    </div>
                </div>
            @else
                @if($profileUser->year)
                    <div class="pub-info-item">
                        <svg style="flex-shrink:0;" width="18" height="18" fill="none" stroke="#64748b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        <div style="min-width:0;">
                            <p style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; margin: 0; line-height: 1.5;">ชั้นปี</p>
                            <p style="font-size: 0.85rem; font-weight: 600; color: var(--text-main, #334155); margin: 0; line-height: 1.5;">ปี {{ $profileUser->year }}</p>
                        </div>
                    </div>
                @endif
                @if($profileUser->program)
                    <div class="pub-info-item">
                        <svg style="flex-shrink:0;" width="18" height="18" fill="none" stroke="#64748b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                        <div style="min-width:0;">
                            <p style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; margin: 0; line-height: 1.5;">หลักสูตร</p>
                            <p style="font-size: 0.85rem; font-weight: 600; color: var(--text-main, #334155); margin: 0; line-height: 1.5;">{{ $profileUser->program }}</p>
                        </div>
                    </div>
                @endif
            @endif
        </div>
        @if($viewerIsSelf && $profileUser->isStudent())
            <p style="font-size: 0.75rem; color: #94a3b8; margin: 0.75rem 0 0 0; line-height: 1.5;">
                <svg style="display:inline;vertical-align:-2px;" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ข้อมูลส่วนตัวที่ละเอียด (รหัสนักศึกษา, อีเมล, ชั่วโมงกิจกรรม) แสดงเฉพาะในหน้าโปรไฟล์ของคุณ
            </p>
        @endif
    </div>
</div>

{{-- ── ผลงานล่าสุด ── --}}
<div class="card mb-4">
    <div class="card-body" style="padding: 1.5rem;">
        <h2 class="font-bold mb-4" style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            ผลงานล่าสุดที่โพสต์
        </h2>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @forelse($posts as $post)
                <a href="{{ $post['url'] }}" class="pub-post-item">
                    <span class="pub-type-chip" style="background: {{ $post['color'] }};">{{ $post['typeLabel'] }}</span>
                    <span style="flex: 1; min-width: 0; font-size: 0.9rem; font-weight: 600; color: var(--text-main, #1e293b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.5;">{{ $post['title'] }}</span>
                    <span style="flex-shrink: 0; font-size: 0.72rem; color: #94a3b8; line-height: 1.5;">
                        @if($post['date'])
                            {{ \Illuminate\Support\Carbon::parse($post['date'])->format('d/m/Y') }}
                        @endif
                    </span>
                </a>
            @empty
                <x-empty-state
                    icon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    title="ยังไม่มีผลงานที่โพสต์"
                    description="{{ $profileUser->isStaffOrAdmin() ? 'ผู้ใช้นี้ยังไม่เคยโพสต์กิจกรรมหรือประกาศ' : 'กดติดตามเพื่อรับข่าวสารจากผู้ใช้นี้ในอนาคต' }}"
                    size="sm" />
            @endforelse
        </div>
    </div>
</div>

{{-- ── Modal: รายชื่อผู้ติดตาม / กำลังติดตาม ── --}}
<div id="followerModal" class="follower-modal-backdrop" onclick="if(event.target === this) closeFollowerModal()">
    <div class="follower-modal">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.9rem 1rem;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
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
