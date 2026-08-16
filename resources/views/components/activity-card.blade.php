{{-- คอมโพเนนต์การ์ดกิจกรรม: แสดงรูป, สถานะ, ชื่อ, วันที่, สถานที่, ชั่วโมง, จำนวนคน --}}
@props(['activity', 'isRegistered' => false, 'isAttended' => false])
<a href="{{ route('activities.show', $activity->id) }}" class="card act-card no-linkify">
    {{-- รูปภาพกิจกรรม (ถ้าไม่มีแสดงไอคอนแทน) --}}
    <div class="act-card-img">
        @if($activity->image_path)
            <img data-src="{{ Storage::url($activity->image_path) }}" alt="{{ $activity->title }}" class="lazy-img" loading="lazy">
        @else
            <svg class="icon-xl" style="color:rgba(255,255,255,.3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        @endif
        {{-- แสดงสถานะลงทะเบียน/เข้าร่วมบนรูปโปสเตอร์ --}}
        @if($isAttended)
            <span class="act-card-user-badge act-badge-completed">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                สำเร็จ
            </span>
        @elseif($isRegistered)
            <span class="act-card-user-badge act-badge-registered">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                ลงทะเบียนแล้ว
            </span>
        @endif
    </div>
    <div class="card-body">
        <div class="act-card-top">
            <div class="flex items-center justify-between mb-2" style="flex-wrap:wrap;gap:4px;min-height:24px;">
                <div class="flex items-center gap-1" style="flex-wrap:wrap;">
                    @include('components.status-badge', ['status' => $activity->computed_status])
                    @if($activity->is_mandatory)<span class="badge badge-red">บังคับ</span>@endif
                </div>
                @if($activity->scope === 'faculty')
                    <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.65rem;">{{ $activity->faculty }}</span>
                @elseif($activity->scope === 'department')
                    <span class="badge" style="background:#ffedd5;color:#5b21b6;font-size:.65rem;">{{ $activity->department }}</span>
                @endif
            </div>
            <h3 class="font-semi line-clamp-2" style="font-size:.95rem;line-height:1.35;min-height:2.7em;margin-bottom:0.4rem;">{{ $activity->title }}</h3>
            <div class="act-card-meta">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span class="line-clamp-1">{{ $activity->activity_date->format('d/m/Y') }}</span>
            </div>
            <div class="act-card-meta">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="line-clamp-1">{{ $activity->location ?? '-' }}</span>
            </div>
        </div>
        <div class="act-card-footer">
            <span>{{ $activity->activity_hours }} ชม.</span>
            <span>{{ $activity->getRegisteredCount() }}/{{ $activity->max_participants }} คน</span>
            @if($activity->hasGeolocation())
                <a href="{{ route('map.index', ['type' => 'activity', 'id' => $activity->id]) }}" class="act-map-btn" onclick="event.stopPropagation();" title="ดูตำแหน่งบนแผนที่">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </a>
            @endif
        </div>
    </div>
</a>
