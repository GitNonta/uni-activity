{{-- หน้ารายการประกาศงานทั้งหมด: ค้นหา + กรอง + Grid Card --}}
@extends('layouts.app')
@section('title', 'หางาน / Part-time')

@section('content')
<div class="act-header-row">
    <h1 class="act-header-title">
        <x-icon name="job" size="20" style="display:inline;vertical-align:-3px;margin-right:.25rem;" />
        ประกาศรับสมัครงาน
    </h1>
</div>

@php
    $hasSearched = request()->filled('search') || request()->filled('job_type') || request()->filled('status') || request()->filled('gender');
@endphp

{{-- ฟอร์มค้นหาและกรอง (แสดงตัวกรองหลังการค้นหา หรือเมื่อกดปุ่มตัวกรอง) --}}
<form method="GET" action="{{ route('jobs.index') }}" class="act-search-bar" id="jobSearchForm">
    <div class="act-search-main-row">
        <div class="act-search-input-wrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ค้นหาชื่องาน / ตำแหน่ง / สถานที่..." class="act-search-input">
            <button type="submit" class="act-search-submit-icon-btn" title="ค้นหา" aria-label="ค้นหา">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
        </div>
        <div class="act-search-btn-group">
            <button type="button" id="toggleJobFilterBtn" class="act-filter-toggle-btn {{ $hasSearched ? 'active' : '' }}" onclick="toggleJobFilters()" title="แสดง/ซ่อนตัวกรอง">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <span>ตัวกรอง</span>
                @if($hasSearched)
                    <span class="act-filter-count-dot"></span>
                @endif
            </button>
            @if($geoJobs->count())
            <a href="{{ route('map.index', ['type' => 'job']) }}" class="page-filter-btn-outline" title="ดูบนแผนที่">
                <x-icon name="map" size="14" />
                <span>แผนที่</span>
            </a>
            @endif
        </div>
    </div>

    {{-- ตัวกรอง: แสดงหลังการค้นหา หรือเมื่อกดปุ่มตัวกรอง --}}
    <div id="jobFilterPanel" class="act-filter-panel" style="{{ $hasSearched ? '' : 'display:none;' }}">
        <div class="act-filter-grid">
            <div class="act-filter-select-wrap">
                <select name="job_type" class="page-filter-select" aria-label="เลือกประเภทงาน" style="width:100%;" onchange="document.getElementById('jobSearchForm').submit()">
                    <option value="">ทุกประเภท</option>
                    <option value="general" {{ request('job_type') == 'general' ? 'selected' : '' }}>งานทั่วไป</option>
                    <option value="parttime" {{ request('job_type') == 'parttime' ? 'selected' : '' }}>Part-time</option>
                </select>
            </div>
            <div class="act-filter-select-wrap">
                <select name="status" class="page-filter-select" aria-label="เลือกสถานะ" style="width:100%;" onchange="document.getElementById('jobSearchForm').submit()">
                    <option value="">ทุกสถานะ</option>
                    <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>เปิดรับสมัคร</option>
                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>ปิดรับสมัคร</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>เสร็จสิ้น</option>
                </select>
            </div>
            <div class="act-filter-select-wrap">
                <select name="gender" class="page-filter-select" aria-label="เลือกเพศ" style="width:100%;" onchange="document.getElementById('jobSearchForm').submit()">
                    <option value="">ทุกเพศ</option>
                    <option value="male" {{ request('gender') == 'male' ? 'selected' : '' }}>ชาย</option>
                    <option value="female" {{ request('gender') == 'female' ? 'selected' : '' }}>หญิง</option>
                </select>
            </div>
            @if($hasSearched)
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <a href="{{ route('jobs.index') }}" class="act-filter-clear-btn" title="ล้างการค้นหาและตัวกรองทั้งหมด">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>ล้างค่า</span>
                </a>
            </div>
            @endif
        </div>
    </div>
</form>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
function toggleJobFilters() {
    var p = document.getElementById('jobFilterPanel');
    var btn = document.getElementById('toggleJobFilterBtn');
    if (!p) return;
    var isHidden = (p.style.display === 'none' || p.style.display === '');
    if (isHidden) {
        p.style.display = 'block';
        if (btn) btn.classList.add('active');
    } else {
        p.style.display = 'none';
        if (btn && !{{ $hasSearched ? 'true' : 'false' }}) btn.classList.remove('active');
    }
}
</script>

{{-- แถบจัดเรียงลำดับอัจฉริยะ (Smart Sorting Tabs) --}}
@php
    $currentSort = request('sort', 'recommended');
@endphp
<div class="sort-scroll-container mb-4">
    <span class="text-xs text-muted font-bold" style="white-space:nowrap;margin-right:2px;display:inline-flex;align-items:center;gap:4px;line-height:1.5;">
        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
        เรียงตาม:
    </span>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'recommended']) }}" 
       class="sort-pill {{ $currentSort === 'recommended' ? 'active' : '' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
        <span>แนะนำสำหรับคุณ</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'compensation']) }}" 
       class="sort-pill {{ $currentSort === 'compensation' ? 'active' : '' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>ค่าตอบแทนสูงสุด</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'starting_soon']) }}" 
       class="sort-pill {{ $currentSort === 'starting_soon' ? 'active' : '' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span>ใกล้เริ่มงาน</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'popular']) }}" 
       class="sort-pill {{ $currentSort === 'popular' ? 'active' : '' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span>คนสมัครเยอะ</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'latest']) }}" 
       class="sort-pill {{ $currentSort === 'latest' ? 'active' : '' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>ประกาศล่าสุด</span>
    </a>
</div>

{{-- แสดงการ์ดงาน --}}
<div class="grid-3">
    @forelse($jobs as $job)
        @include('components.job-card', [
            'job' => $job,
            'isApplied' => in_array($job->id, $appliedJobIds ?? []),
        ])
    @empty
        <div style="grid-column:1/-1;">
            <x-empty-state
                icon="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                title="ไม่พบประกาศงาน"
                description="ลองเปลี่ยนเงื่อนไขการค้นหา หรือเลือกสถานะงาน และประเภทงานอื่น"
                actionLabel="ดูทุกประกาศงาน"
                actionUrl="{{ route('jobs.index') }}"
                size="lg"
            />
        </div>
    @endforelse
</div>

{{-- Pagination --}}
<div class="mt-4">{{ $jobs->links() }}</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var lazyImages = document.querySelectorAll('img.lazy-img');
    if ('IntersectionObserver' in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    var src = img.getAttribute('data-src');
                    if (src) {
                        img.src = src;
                        img.classList.add('loaded');
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        }, { rootMargin: '50px 0px', threshold: 0.01 });
        lazyImages.forEach(function(img) { imageObserver.observe(img); });
    } else {
        lazyImages.forEach(function(img) {
            var src = img.getAttribute('data-src');
            if (src) {
                img.src = src;
                img.removeAttribute('data-src');
            }
        });
    }
});
</script>
@endsection
