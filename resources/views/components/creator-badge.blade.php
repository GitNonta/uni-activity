@props(['creator' => null, 'defaultRole' => 'ผู้จัดกิจกรรม'])

@php
    $creatorName = $creator ? ($creator->full_name ?? $creator->name ?? 'ผู้จัดกิจกรรม') : 'ผู้จัดกิจกรรม';
    $creatorRole = $creator ? ($creator->faculty ?? $creator->department ?? $defaultRole) : $defaultRole;
    $creatorPhoto = $creator && $creator->profile_photo ? asset('storage/' . $creator->profile_photo) : null;
    // ลิงก์ไปหน้าโปรไฟล์สาธารณะของผู้โพสต์ (คลิกดูโปรไฟล์ + กดติดตามได้)
    $creatorUrl = $creator ? route('users.show', $creator) : null;
@endphp

<style>
.creator-row-badge {
    margin: 0.65rem 0;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.25rem 0;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    text-decoration: none;
    color: inherit;
    transition: opacity 0.15s ease;
}
.creator-row-badge.is-link {
    cursor: pointer;
}
.creator-row-badge .creator-avatar-wrap {
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.creator-row-badge .creator-name {
    font-weight: 700;
    font-size: 0.9rem;
    color: #1e293b;
    line-height: 1.4;
    transition: color 0.15s ease;
}
.creator-row-badge .creator-chevron {
    flex-shrink: 0;
    color: #94a3b8;
    transition: transform 0.2s ease, color 0.15s ease;
}
.creator-row-badge .creator-role {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
    margin-top: 0.1rem;
    line-height: 1.4;
}

/* Hover effects */
.creator-row-badge.is-link:hover .creator-avatar-wrap {
    transform: scale(1.05);
}
.creator-row-badge.is-link:hover .creator-name {
    color: #ea580c;
}
.creator-row-badge.is-link:hover .creator-chevron {
    color: #ea580c;
    transform: translateX(3px);
}

/* Dark mode */
html[data-theme="dark"] .creator-row-badge .creator-name,
html.dark .creator-row-badge .creator-name {
    color: #f1f5f9;
}
html[data-theme="dark"] .creator-row-badge.is-link:hover .creator-name,
html.dark .creator-row-badge.is-link:hover .creator-name {
    color: #fb923c;
}
html[data-theme="dark"] .creator-row-badge .creator-role,
html.dark .creator-row-badge .creator-role {
    color: #94a3b8;
}
html[data-theme="dark"] .creator-row-badge .creator-chevron,
html.dark .creator-row-badge .creator-chevron {
    color: #64748b;
}
html[data-theme="dark"] .creator-row-badge.is-link:hover .creator-chevron,
html.dark .creator-row-badge.is-link:hover .creator-chevron {
    color: #fb923c;
}
</style>

@if($creatorUrl)
<a href="{{ $creatorUrl }}" class="creator-row-badge is-link" title="ดูโปรไฟล์ของ {{ $creatorName }}">
    <div class="creator-avatar-wrap">
        <x-avatar :user="$creator" size="40" style="border: 1.5px solid rgba(148, 163, 184, 0.25); box-shadow: 0 1px 3px rgba(0,0,0,0.06);" />
    </div>
    <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0;">
        <div style="display: flex; align-items: center; gap: 0.35rem;">
            <span class="creator-name">{{ $creatorName }}</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="#06c755" style="flex-shrink: 0;" aria-label="ผู้จัดกิจกรรมทางการ" title="ผู้จัดกิจกรรมทางการ">
                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
            </svg>
            <svg class="creator-chevron" width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
        <span class="creator-role">{{ $creatorRole }}</span>
    </div>
</a>
@else
<div class="creator-row-badge">
    <div class="creator-avatar-wrap">
        <x-avatar :user="$creator" size="40" style="border: 1.5px solid rgba(148, 163, 184, 0.25); box-shadow: 0 1px 3px rgba(0,0,0,0.06);" />
    </div>
    <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0;">
        <div style="display: flex; align-items: center; gap: 0.35rem;">
            <span class="creator-name">{{ $creatorName }}</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="#06c755" style="flex-shrink: 0;" aria-label="ผู้จัดกิจกรรมทางการ" title="ผู้จัดกิจกรรมทางการ">
                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
            </svg>
        </div>
        <span class="creator-role">{{ $creatorRole }}</span>
    </div>
</div>
@endif
