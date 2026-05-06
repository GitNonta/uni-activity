{{-- การ์ดแสดงงาน: badge ประเภท, สถานะ, ข้อมูลย่อ, progress --}}
<a href="{{ route('jobs.show', $job->id) }}" class="card act-card job-card">
    {{-- รูปภาพ / gradient --}}
    @if($job->image_path)
        <div class="act-card-img">
            <img data-src="{{ Storage::url($job->image_path) }}" alt="{{ $job->title }}" class="lazy-img">
        </div>
    @else
        <div class="act-card-img" style="background:linear-gradient(135deg,{{ $job->job_type === 'parttime' ? '#f97316,#fb923c' : '#3b82f6,#60a5fa' }});height:100px;">
            <svg class="icon-xl" style="color:rgba(255,255,255,.3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
    @endif

    {{-- Badge สถานะผู้สมัคร --}}
    @if(isset($isApplied) && $isApplied)
        <div class="act-card-user-badge act-badge-registered">
            <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            สมัครแล้ว
        </div>
    @endif

    <div class="card-body" style="padding:1rem;">
        {{-- Badges --}}
        <div class="flex items-center gap-2 mb-2" style="flex-wrap:wrap;">
            <span class="badge {{ $job->job_type === 'parttime' ? 'job-badge-parttime' : 'job-badge-general' }}">
                {{ $job->job_type === 'parttime' ? 'Part-time' : 'งานทั่วไป' }}
            </span>
            @if($job->status === 'open')
                <span class="badge badge-green">🟢 เปิดรับสมัคร</span>
            @elseif($job->status === 'closed')
                <span class="badge badge-red">🔴 ปิดรับสมัคร</span>
            @else
                <span class="badge badge-gray">⚫ เสร็จสิ้น</span>
            @endif
        </div>

        {{-- ชื่องาน --}}
        <h3 class="font-semi line-clamp-1" style="font-size:.95rem;">{{ $job->title }}</h3>

        {{-- ข้อมูลย่อ --}}
        <div class="act-card-meta">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            {{ $job->position }}
        </div>
        @if($job->compensation)
        <div class="act-card-meta">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $job->compensation }}
        </div>
        @endif
        <div class="act-card-meta">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="line-clamp-1">{{ $job->location }}</span>
        </div>

        {{-- Progress bar --}}
        <div style="margin-top:.5rem;">
            <div class="flex justify-between text-xs text-muted">
                <span>ยืนยันแล้ว</span>
                <span class="font-semi">{{ $job->confirmed_applicants }} / {{ $job->quota }} คน</span>
            </div>
            <div class="progress" style="margin-top:.25rem;">
                @php $pct = $job->progress_percent; @endphp
                <div class="progress-bar {{ $pct >= 100 ? 'red' : ($pct >= 70 ? 'yellow' : 'green') }}" style="width:{{ $pct }}%"></div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="act-card-footer">
            <span>
                <svg class="icon-sm" style="display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $job->start_date->format('d/m/Y') }}{{ $job->end_date ? ' – ' . $job->end_date->format('d/m/Y') : '' }}
            </span>
            @if($job->hasGeolocation())
            <button type="button" class="act-map-btn" onclick="event.preventDefault();event.stopPropagation();openJobMap({{ $job->id }})" title="ดูแผนที่">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </button>
            @endif
        </div>
    </div>
</a>
