@props(['creator' => null, 'defaultRole' => 'ผู้จัดกิจกรรม'])

@php
    $creatorName = $creator ? ($creator->full_name ?? $creator->name ?? 'ผู้จัดกิจกรรม') : 'ผู้จัดกิจกรรม';
    $creatorRole = $creator ? ($creator->faculty ?? $creator->department ?? $defaultRole) : $defaultRole;
    $creatorPhoto = $creator && $creator->profile_photo ? asset('storage/' . $creator->profile_photo) : null;
    // ลิงก์ไปหน้าโปรไฟล์สาธารณะของผู้โพสต์ (คลิกดูโปรไฟล์ + กดติดตามได้)
    $creatorUrl = $creator ? route('users.show', $creator) : null;
@endphp

<style>
.creator-youtube-badge {
    margin: 0.85rem 0;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.2s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    text-decoration: none;
    color: inherit;
}
.creator-youtube-badge.is-link {
    cursor: pointer;
}
.creator-youtube-badge.is-link:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.06);
}
html[data-theme="dark"] .creator-youtube-badge,
html.dark .creator-youtube-badge {
    background: #18181b !important;
    border-color: #27272a !important;
}
html[data-theme="dark"] .creator-youtube-badge.is-link:hover,
html.dark .creator-youtube-badge.is-link:hover {
    background: #27272a !important;
    border-color: #3f3f46 !important;
}
html[data-theme="dark"] .creator-youtube-badge .creator-name,
html.dark .creator-youtube-badge .creator-name {
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .creator-youtube-badge .creator-role,
html.dark .creator-youtube-badge .creator-role {
    color: #94a3b8 !important;
}
</style>

@if($creatorUrl)
<a href="{{ $creatorUrl }}" class="creator-youtube-badge is-link" title="ดูโปรไฟล์ของ {{ $creatorName }}">
    <x-avatar :user="$creator" size="38" style="border: 1.5px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.08);" />
    <div style="display: flex; flex-direction: column; justify-content: center;">
        <div style="display: flex; align-items: center; gap: 0.35rem;">
            <span class="creator-name" style="font-weight: 700; font-size: 0.875rem; color: #1e293b; line-height: 1.5;">{{ $creatorName }}</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="#06c755" style="flex-shrink: 0;" title="ผู้จัดกิจกรรมทางการ">
                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
            </svg>
            <svg width="13" height="13" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style="flex-shrink: 0;" title="ดูโปรไฟล์">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
        <span class="creator-role" style="font-size: 0.75rem; color: #475569; font-weight: 500; margin-top: 0.1rem; line-height: 1.5;">{{ $creatorRole }}</span>
    </div>
</a>
@else
<div class="creator-youtube-badge">
    <x-avatar :user="$creator" size="38" style="border: 1.5px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.08);" />
    <div style="display: flex; flex-direction: column; justify-content: center;">
        <div style="display: flex; align-items: center; gap: 0.35rem;">
            <span class="creator-name" style="font-weight: 700; font-size: 0.875rem; color: #1e293b; line-height: 1.5;">{{ $creatorName }}</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="#06c755" style="flex-shrink: 0;" title="ผู้จัดกิจกรรมทางการ">
                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
            </svg>
        </div>
        <span class="creator-role" style="font-size: 0.75rem; color: #475569; font-weight: 500; margin-top: 0.1rem; line-height: 1.5;">{{ $creatorRole }}</span>
    </div>
</div>
@endif
