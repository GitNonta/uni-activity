@extends('layouts.app')
@section('title', 'รายการกิจกรรม')
@section('description', 'ค้นหาและลงทะเบียนเข้าร่วมกิจกรรมของมหาวิทยาลัย - ดูกิจกรรมที่กำลังเปิดรับสมัครและที่จัดขึ้นแล้ว')

@section('content')
<div class="flex items-center gap-3 mb-4">
    <h1 class="font-bold" style="font-size:1.5rem; margin:0;">รายการกิจกรรม</h1>
    <a href="{{ route('jobs.index') }}" class="btn btn-outline" style="border-radius:20px; padding: 0.25rem 0.75rem; font-size:0.85rem; display:inline-flex; align-items:center; gap:4px; color:#f59e0b; border-color:#fcd34d; background:#fffbf1;">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        แนะนำ หางาน / Part-time
    </a>
</div>

{{-- ฟอร์มค้นหาและกรองหมวดหมู่ --}}
<form method="GET" action="{{ route('activities.index') }}" class="flex gap-2 mb-4" style="flex-wrap:wrap;">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="ค้นหากิจกรรม..." class="form-control flex-1" style="min-width:200px;">
    <select name="category" class="form-control" style="width:auto;">
        <option value="">ทุกหมวดหมู่</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
        @endforeach
    </select>
    <select name="scope" class="form-control" style="width:auto;">
        <option value="">ทุกระดับ</option>
        <option value="university" {{ request('scope') == 'university' ? 'selected' : '' }}>มหาวิทยาลัย</option>
        <option value="faculty" {{ request('scope') == 'faculty' ? 'selected' : '' }}>คณะ</option>
        <option value="department" {{ request('scope') == 'department' ? 'selected' : '' }}>สาขา</option>
    </select>
    <button type="submit" class="btn btn-primary">ค้นหา</button>
    @if($geoActivities->count())
    <a href="{{ route('map.index', ['type' => 'activity']) }}" class="btn btn-outline" style="white-space:nowrap;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
        แผนที่กิจกรรม
    </a>
    @endif
</form>

{{-- แถบจัดเรียงลำดับอัจฉริยะ (Smart Sorting Tabs) --}}
@php
    $currentSort = request('sort', 'recommended');
@endphp
<div class="sort-scroll-container mb-4">
    <span class="text-xs text-muted font-bold" style="white-space:nowrap;margin-right:2px;display:inline-flex;align-items:center;gap:4px;">
        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
        เรียงตาม:
    </span>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'recommended']) }}" 
       class="sort-pill {{ $currentSort === 'recommended' ? 'active' : '' }}"
       style="white-space:nowrap;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.15s ease;display:inline-flex;align-items:center;gap:5px;{{ $currentSort === 'recommended' ? 'background:#ea580c;color:#fff;box-shadow:0 2px 6px rgba(234,88,12,0.3);' : 'background:#fff;color:#475569;border:1px solid #e2e8f0;' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
        <span>แนะนำสำหรับคุณ</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'closing_soon']) }}" 
       class="sort-pill {{ $currentSort === 'closing_soon' ? 'active' : '' }}"
       style="white-space:nowrap;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.15s ease;display:inline-flex;align-items:center;gap:5px;{{ $currentSort === 'closing_soon' ? 'background:#ea580c;color:#fff;box-shadow:0 2px 6px rgba(234,88,12,0.3);' : 'background:#fff;color:#475569;border:1px solid #e2e8f0;' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span>ใกล้ปิดรับ</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'popular']) }}" 
       class="sort-pill {{ $currentSort === 'popular' ? 'active' : '' }}"
       style="white-space:nowrap;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.15s ease;display:inline-flex;align-items:center;gap:5px;{{ $currentSort === 'popular' ? 'background:#ea580c;color:#fff;box-shadow:0 2px 6px rgba(234,88,12,0.3);' : 'background:#fff;color:#475569;border:1px solid #e2e8f0;' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        <span>ยอดนิยม</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'upcoming']) }}" 
       class="sort-pill {{ $currentSort === 'upcoming' ? 'active' : '' }}"
       style="white-space:nowrap;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.15s ease;display:inline-flex;align-items:center;gap:5px;{{ $currentSort === 'upcoming' ? 'background:#ea580c;color:#fff;box-shadow:0 2px 6px rgba(234,88,12,0.3);' : 'background:#fff;color:#475569;border:1px solid #e2e8f0;' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <span>จัดเร็วๆ นี้</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'latest']) }}" 
       class="sort-pill {{ $currentSort === 'latest' ? 'active' : '' }}"
       style="white-space:nowrap;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.15s ease;display:inline-flex;align-items:center;gap:5px;{{ $currentSort === 'latest' ? 'background:#ea580c;color:#fff;box-shadow:0 2px 6px rgba(234,88,12,0.3);' : 'background:#fff;color:#475569;border:1px solid #e2e8f0;' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>มาใหม่</span>
    </a>
    <a href="{{ request()->fullUrlWithQuery(['sort' => 'completed']) }}"
       class="sort-pill {{ $currentSort === 'completed' ? 'active' : '' }}"
       style="white-space:nowrap;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.15s ease;display:inline-flex;align-items:center;gap:5px;{{ $currentSort === 'completed' ? 'background:#ea580c;color:#fff;box-shadow:0 2px 6px rgba(234,88,12,0.3);' : 'background:#fff;color:#475569;border:1px solid #e2e8f0;' }}">
        <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>เสร็จสิ้นแล้ว</span>
    </a>
</div>

{{-- แสดงการ์ดกิจกรรม หรือข้อความว่างถ้าไม่พบ --}}
<div class="grid-5">
    @forelse($activities as $activity)
        @include('components.activity-card', [
            'activity' => $activity,
            'isRegistered' => in_array($activity->id, $registeredActivityIds ?? []),
            'isAttended' => in_array($activity->id, $attendedActivityIds ?? []),
        ])
    @empty
        <div class="empty-state" style="grid-column:1/-1;">
            <svg class="icon-xl" style="margin:0 auto 1rem;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p>ไม่พบกิจกรรม</p>
        </div>
    @endforelse
</div>

{{-- ลิงก์แบ่งหน้า --}}
<div class="mt-4">{{ $activities->links() }}</div>

{{-- ══════════════════════════════════════════════════════ --}}
{{-- ส่วนกิจกรรมที่เสร็จสิ้นแล้ว (เกิน 7 วัน)           --}}
{{-- ══════════════════════════════════════════════════════ --}}
@if($completedActivities->count() && $currentSort !== 'completed')
<div style="margin-top:3rem; padding-top:2rem; border-top:1px solid #e2e8f0;">
    <h2 style="font-size:1.1rem; font-weight:600; color:#64748b; padding:0.5rem 0; display:flex; align-items:center; gap:8px;">
        <svg style="width:20px; height:20px; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        กิจกรรมที่เสร็จสิ้นแล้ว <span style="font-weight:400; font-size:0.85rem;">({{ $completedActivities->total() }} รายการ)</span>
    </h2>
    <div class="mt-3" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem;">
        @forelse($completedActivities as $act)
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; opacity:0.85;">
                @if($act->image_path)
                    <img src="{{ Storage::url($act->image_path) }}" alt="{{ $act->title }}" style="width:100%; height:140px; object-fit:cover; background:#f1f5f9;">
                @endif
                <div style="padding:0.75rem;">
                    <a href="{{ route('activities.show', $act->id) }}" style="font-weight:600; color:#1e293b; text-decoration:none;">{{ $act->title }}</a>
                    <p style="margin:4px 0 0; font-size:0.8rem; color:#94a3b8;">{{ $act->activity_date->format('d/m/Y') }} · {{ $act->category->name ?? '' }}</p>
                </div>
            </div>
        @empty
        @endforelse
    </div>
    <div class="mt-3">{{ $completedActivities->links() }}</div>
</div>
@endif
@endsection

@section('scripts')
<script>
// ══════════════════════════════════════
//  Lazy Loading Images
// ══════════════════════════════════════
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
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });
        
        lazyImages.forEach(function(img) {
            imageObserver.observe(img);
        });
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
