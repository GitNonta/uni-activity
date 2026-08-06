@props(['creator' => null, 'defaultRole' => 'ผู้จัดกิจกรรม'])

@php
    $creatorName = $creator ? ($creator->full_name ?? $creator->name ?? 'ผู้จัดกิจกรรม') : 'ผู้จัดกิจกรรม';
    $creatorRole = $creator ? ($creator->faculty ?? $creator->department ?? $defaultRole) : $defaultRole;
    $creatorPhoto = $creator && $creator->profile_photo ? asset('storage/' . $creator->profile_photo) : null;
@endphp

<div class="creator-youtube-badge" style="margin: 0.85rem 0; display: inline-flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
    @if($creatorPhoto)
        <img src="{{ $creatorPhoto }}" alt="{{ $creatorName }}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
    @else
        <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #ea580c, #f97316); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);">
            {{ mb_substr($creatorName, 0, 1, 'UTF-8') }}
        </div>
    @endif
    <div style="display: flex; flex-direction: column; justify-content: center;">
        <div style="display: flex; align-items: center; gap: 0.35rem;">
            <span style="font-weight: 700; font-size: 0.875rem; color: #1e293b; line-height: 1.2;">{{ $creatorName }}</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="#06c755" style="flex-shrink: 0;" title="ผู้จัดกิจกรรมทางการ">
                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
            </svg>
        </div>
        <span style="font-size: 0.75rem; color: #64748b; font-weight: 500; margin-top: 0.1rem;">{{ $creatorRole }}</span>
    </div>
</div>
