@extends('layouts.app')
@section('title', 'รายการกิจกรรม')

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
    <button type="button" class="btn btn-outline" onclick="openActivityMap()" style="white-space:nowrap;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
        แผนที่กิจกรรม
    </button>
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

{{-- Map Modal Overlay --}}
<div id="actMapModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);">
    <div style="position:absolute;inset:0;display:flex;flex-direction:column;background:#fff;">
        {{-- Map Top Bar --}}
        <div style="background:#fff;padding:.6rem 1rem;border-bottom:1px solid #e2e8f0;display:flex;flex-direction:column;gap:.4rem;z-index:10;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:30px;height:30px;border-radius:8px;background:rgba(234,88,12,0.1);color:#ea580c;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    </div>
                    <span style="font-weight:700;font-size:1rem;" id="mapTitle">แผนที่กิจกรรม</span>
                </div>

                {{-- Map Mode Switchers --}}
                <div style="display:flex;align-items:center;gap:4px;">
                    <button type="button" class="map-mode-btn active" id="btn-act-streets" onclick="switchActMapLayer('streets')" title="แผนที่ปกติ">
                        <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <span>ปกติ</span>
                    </button>
                    <button type="button" class="map-mode-btn" id="btn-act-satellite" onclick="switchActMapLayer('satellite')" title="ภาพถ่ายดาวเทียม">
                        <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>ดาวเทียม</span>
                    </button>
                    <button type="button" class="map-mode-btn" id="btn-act-heat" onclick="toggleActHeatmap()" title="แผนที่ความหนาแน่น">
                        <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                        <span>ความหนาแน่น</span>
                    </button>
                    <button id="btnClearRoute" onclick="clearRoute()" style="display:none;padding:4px 10px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:6px;font-size:.75rem;font-weight:600;cursor:pointer;">✕ ปิดนำทาง</button>
                    <button onclick="closeMapModal()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;padding:0 .4rem;line-height:1;color:#64748b;">&times;</button>
                </div>
            </div>

            {{-- Radius Radar Pills --}}
            <div class="sort-scroll-container">
                <span class="text-xs text-muted font-bold" style="display:inline-flex;align-items:center;gap:4px;white-space:nowrap;margin-right:2px;">
                    <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    รัศมี:
                </span>
                <button type="button" class="map-radius-pill active" data-radius="0" onclick="filterActRadius(0, this)">
                    <span>ไม่จำกัด</span>
                </button>
                <button type="button" class="map-radius-pill" data-radius="2" onclick="filterActRadius(2, this)">
                    <span>&lt; 2 กม.</span>
                </button>
                <button type="button" class="map-radius-pill" data-radius="5" onclick="filterActRadius(5, this)">
                    <span>&lt; 5 กม.</span>
                </button>
                <button type="button" class="map-radius-pill" data-radius="10" onclick="filterActRadius(10, this)">
                    <span>&lt; 10 กม.</span>
                </button>
                <button type="button" class="map-radius-pill" data-radius="25" onclick="filterActRadius(25, this)">
                    <span>&lt; 25 กม.</span>
                </button>
            </div>
        </div>

        {{-- Map Canvas & Floating Elements --}}
        <div style="flex:1;display:flex;position:relative;overflow:hidden;">
            <div id="actMapContainer" style="flex:1;width:100%;height:100%;"></div>

            {{-- Floating Locate Me Button --}}
            <button type="button" class="map-locate-btn" onclick="locateAndCenterActUser()" title="ระบุตำแหน่งของฉัน">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </button>

            {{-- BottomSheet Preview Card --}}
            <div id="actBottomSheet" class="map-bottom-sheet" style="display:none;">
                <div class="bs-handle"></div>
                <div class="bs-content">
                    <button type="button" class="bs-close-btn" onclick="closeActBottomSheet()">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <div class="flex gap-3 items-start">
                        <div class="bs-thumb">
                            <img id="act-bs-img" src="" alt="Poster" style="display:none;">
                            <div id="act-bs-fallback" class="bs-icon-fallback">
                                <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="flex items-center gap-2 mb-1">
                                <span id="act-bs-badge" class="badge badge-orange">กิจกรรม</span>
                                <span id="act-bs-distance" class="bs-distance-pill">-</span>
                            </div>
                            <h3 id="act-bs-title" class="bs-title" style="font-size:0.95rem;margin:0 0 2px;"></h3>
                            <p id="act-bs-location" class="bs-subtitle" style="font-size:0.78rem;color:#64748b;margin:0;"></p>
                        </div>
                    </div>

                    {{-- ETAs & Meta --}}
                    <div class="bs-meta-grid mt-2" style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;">
                        <div class="bs-meta-card" style="padding:4px 8px;">
                            <div class="bs-meta-label" style="font-size:0.68rem;color:#64748b;display:flex;align-items:center;gap:3px;">
                                <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <span>เดินเท้า</span>
                            </div>
                            <span id="act-bs-walk-eta" class="bs-meta-val" style="font-size:0.8rem;font-weight:700;">-</span>
                        </div>
                        <div class="bs-meta-card" style="padding:4px 8px;">
                            <div class="bs-meta-label" style="font-size:0.68rem;color:#64748b;display:flex;align-items:center;gap:3px;">
                                <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                                <span>ขับขี่</span>
                            </div>
                            <span id="act-bs-drive-eta" class="bs-meta-val" style="font-size:0.8rem;font-weight:700;">-</span>
                        </div>
                        <div class="bs-meta-card" style="grid-column:span 2;padding:4px 8px;">
                            <div class="bs-meta-label" style="font-size:0.68rem;color:#64748b;display:flex;align-items:center;gap:3px;">
                                <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>กำหนดการ</span>
                            </div>
                            <span id="act-bs-meta-info" class="bs-meta-val" style="font-size:0.8rem;font-weight:700;">-</span>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="bs-actions mt-2" style="display:flex;gap:6px;">
                        <a id="act-bs-detail-btn" href="#" class="btn btn-primary btn-sm flex-1" style="font-size:0.78rem;padding:.35rem .6rem;">
                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <span>ดูรายละเอียด</span>
                        </a>
                        <button type="button" id="act-bs-nav-btn" onclick="startNavFromBottomSheet()" class="btn btn-outline btn-sm flex-1" style="font-size:0.78rem;padding:.35rem .6rem;color:#ea580c;border-color:#ea580c;">
                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            <span>นำทางแบบเรียลไทม์</span>
                        </button>
                    </div>

                    {{-- External Apps --}}
                    <div style="display:flex;gap:6px;margin-top:6px;">
                        <a id="act-bs-gmaps" href="#" target="_blank" rel="noopener noreferrer" class="bs-app-btn flex-1" style="font-size:0.72rem;padding:4px 8px;">
                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            <span>Google Maps</span>
                        </a>
                        <a id="act-bs-applemaps" href="#" target="_blank" rel="noopener noreferrer" class="bs-app-btn flex-1" style="font-size:0.72rem;padding:4px 8px;">
                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            <span>Apple Maps</span>
                        </a>
                    </div>
                </div>
            </div>

            <div id="directionsPanel" style="display:none;width:320px;background:#fff;overflow-y:auto;border-left:1px solid #e2e8f0;flex-shrink:0;"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet-routing-machine/3.2.12/leaflet-routing-machine.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-routing-machine/3.2.12/leaflet-routing-machine.min.js"></script>
<style>
    .act-map-btn {
        display:inline-flex;align-items:center;justify-content:center;
        width:28px;height:28px;border-radius:50%;border:none;
        background:#e0f2fe;color:#0284c7;cursor:pointer;
        transition:background .2s,transform .15s;margin-left:auto;flex-shrink:0;
    }
    .act-map-btn:hover { background:#bae6fd;transform:scale(1.15); }
    .map-marker-img {
        width:44px;height:44px;border-radius:50%;object-fit:cover;
        border:3px solid #ea580c;box-shadow:0 2px 8px rgba(0,0,0,.3);
        background:#fff;
    }
    .map-marker-name {
        background:#ea580c;color:#fff;padding:3px 8px;border-radius:12px;
        font-size:.7rem;font-weight:600;white-space:nowrap;
        box-shadow:0 2px 6px rgba(0,0,0,.25);border:2px solid #fff;
        max-width:120px;overflow:hidden;text-overflow:ellipsis;
    }
    .map-marker-highlight .map-marker-img { border-color:#f59e0b;box-shadow:0 0 0 4px rgba(245,158,11,.4); }
    .map-marker-highlight .map-marker-name { background:#f59e0b; }
    .leaflet-popup-content { min-width:200px; }
    .map-popup-img { width:100%;height:100px;object-fit:cover;border-radius:8px;margin-bottom:8px; }
    .map-popup-title { font-weight:700;font-size:.9rem;margin-bottom:4px; }
    .map-popup-meta { font-size:.8rem;color:#64748b;margin-bottom:2px; }
    .map-popup-dist { font-size:.8rem;color:#ea580c;font-weight:600;margin-top:6px; }
    .map-popup-link { display:inline-block;margin-top:8px;padding:4px 12px;background:#ea580c;color:#fff;border-radius:6px;text-decoration:none;font-size:.8rem;font-weight:500; }
    .map-popup-link:hover { background:#c2410c; }
    .map-dist-label {
        background:rgba(234,88,12,.85);color:#fff;padding:2px 8px;border-radius:10px;
        font-size:.7rem;font-weight:600;white-space:nowrap;
        box-shadow:0 1px 4px rgba(0,0,0,.2);
    }
    .map-popup-dir { display:flex;gap:6px;margin-top:8px; }
    .map-dir-btn {
        flex:1;text-align:center;padding:5px 8px;border-radius:6px;
        font-size:.75rem;font-weight:600;text-decoration:none;color:#fff;
        transition:opacity .2s;
    }
    .map-dir-btn:hover { opacity:.85;text-decoration:none; }
    .map-dir-google { background:#4285f4; }
    .map-dir-apple { background:#333; }
    .map-nav-btn {
        display:block;width:100%;margin-top:8px;padding:6px 12px;background:#16a34a;color:#fff;
        border:none;border-radius:6px;font-size:.8rem;font-weight:600;cursor:pointer;text-align:center;
        transition:background .2s;
    }
    .map-nav-btn:hover { background:#15803d; }
    /* Directions panel */
    .dir-header { padding:1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0; }
    .dir-header h3 { font-size:.95rem;font-weight:700;margin:0 0 .5rem; }
    .dir-summary { display:flex;gap:1rem;margin-bottom:.5rem; }
    .dir-summary-item { text-align:center; }
    .dir-summary-value { font-size:1.1rem;font-weight:700;color:#ea580c; }
    .dir-summary-label { font-size:.7rem;color:#64748b; }
    .dir-steps { list-style:none;padding:0;margin:0; }
    .dir-step { padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;gap:.75rem;align-items:flex-start;font-size:.8rem; }
    .dir-step:hover { background:#f8fafc; }
    .dir-step-icon { width:28px;height:28px;background:#e0f2fe;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.75rem; }
    .dir-step-text { flex:1;line-height:1.4; }
    .dir-step-dist { color:#64748b;font-size:.75rem;margin-top:2px; }
    .dir-ext-links { padding:1rem;display:flex;gap:.5rem; }
    .dir-ext-links a { flex:1; }
    /* Hide default LRM container */
    .leaflet-routing-container { display:none !important; }
    @media (max-width:768px) {
        #directionsPanel { position:absolute;bottom:0;left:0;right:0;width:100% !important;max-height:45%;border-left:none !important;border-top:2px solid #e2e8f0;z-index:20;border-radius:12px 12px 0 0; }
    }
    /* ── Real-time Navigation ── */
    .nav-me-dot {
        width:20px;height:20px;position:relative;
    }
    .nav-me-dot-inner {
        width:20px;height:20px;background:#ea580c;border:3px solid #fff;border-radius:50%;
        box-shadow:0 0 0 3px rgba(59,130,246,.35);position:relative;z-index:2;
    }
    .nav-me-dot-pulse {
        position:absolute;top:50%;left:50%;width:40px;height:40px;margin:-20px 0 0 -20px;
        background:rgba(59,130,246,.2);border-radius:50%;
        animation:navPulse 2s ease-out infinite;
    }
    @keyframes navPulse { 0%{transform:scale(.5);opacity:1} 100%{transform:scale(2.5);opacity:0} }
    .nav-heading-arrow {
        position:absolute;top:-14px;left:50%;margin-left:-8px;width:0;height:0;
        border-left:8px solid transparent;border-right:8px solid transparent;border-bottom:14px solid #ea580c;
        filter:drop-shadow(0 1px 2px rgba(0,0,0,.3));z-index:3;transition:transform .3s ease;
    }
    .nav-accuracy-ring {
        border:2px solid rgba(59,130,246,.15);background:rgba(59,130,246,.05);border-radius:50%;
        position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;
    }
    /* Navigation arrow icon */
    .nav-arrow-icon {
        transition:transform .3s ease;
        filter:drop-shadow(0 2px 8px rgba(0,0,0,.3));
    }
    /* HUD overlay */
    .nav-hud {
        position:absolute;top:12px;left:50%;transform:translateX(-50%);z-index:1000;
        background:rgba(0,0,0,.85);color:#fff;border-radius:16px;padding:10px 20px;
        display:flex;gap:16px;align-items:center;box-shadow:0 4px 20px rgba(0,0,0,.3);
        backdrop-filter:blur(8px);min-width:280px;justify-content:center;
    }
    .nav-hud-item { text-align:center; }
    .nav-hud-value { font-size:1.2rem;font-weight:700;line-height:1.2; }
    .nav-hud-label { font-size:.65rem;opacity:.7; }
    .nav-hud-divider { width:1px;height:32px;background:rgba(255,255,255,.2); }
    .nav-hud-instruction {
        position:absolute;top:100px;left:50%;transform:translateX(-50%);z-index:1000;
        background:rgba(234,88,12,.95);color:#fff;border-radius:12px;padding:8px 18px;
        font-size:.85rem;font-weight:600;box-shadow:0 4px 16px rgba(234,88,12,.3);
        max-width:90%;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }
    .nav-tunnel-badge {
        position:absolute;top:12px;right:12px;z-index:1000;
        background:#fbbf24;color:#92400e;border-radius:8px;padding:4px 10px;
        font-size:.75rem;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,.15);
        display:none;
    }
    .nav-speed-badge {
        position:absolute;bottom:20px;left:12px;z-index:1000;
        background:rgba(0,0,0,.75);color:#fff;border-radius:50%;width:52px;height:52px;
        display:none;align-items:center;justify-content:center;flex-direction:column;
        box-shadow:0 2px 8px rgba(0,0,0,.3);
    }
    .nav-speed-value { font-size:1rem;font-weight:700;line-height:1; }
    .nav-speed-unit { font-size:.55rem;opacity:.7; }
    .nav-arrived {
        position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1100;
        background:#fff;border-radius:16px;padding:2rem;text-align:center;
        box-shadow:0 8px 40px rgba(0,0,0,.3);max-width:320px;
    }
    .nav-arrived h2 { font-size:1.2rem;margin-bottom:.5rem; }
    .dir-step-active { background:#eff6ff;border-left:3px solid #ea580c; }
</style>
<script>
var geoActivities = {!! json_encode($geoActivities->values()) !!};
var actMap = null;
var actMarkers = {};
var actLines = [];
var actDistLabels = [];
var userLat = null, userLng = null;
var highlightId = null;
var routingControl = null;
var meMarker = null;
var isRouting = false;

// ── Real-time Navigation State ──
var nav = {
    active: false,
    watchId: null,
    destLat: null, destLng: null,
    actData: null,
    routeCoords: [],       // decoded route polyline coordinates
    routeSteps: [],        // turn-by-turn instructions
    currentStepIdx: 0,
    routeLine: null,       // L.polyline on map
    traveledLine: null,    // gray line for traveled portion
    heading: 0,
    speed: 0,              // m/s
    lastGpsTime: 0,
    gpsLostSince: 0,       // timestamp when GPS signal lost
    offRouteCount: 0,
    rerouteThrottle: 0,
    startTime: 0,
    hudEl: null, instrEl: null, tunnelEl: null, speedEl: null,
    // Adaptive GPS frequency
    gpsInterval: 2000,     // ms — start at 2s
    gpsFast: 1000,         // when navigating fast (>30km/h)
    gpsSlow: 3000,         // when slow/stationary
    gpsTimeout: null
};

// ══════════════════════════════════════
//  Kalman Filter for GPS smoothing
// ══════════════════════════════════════
var kalman = {
    lat: null, lng: null,
    variance: 1,           // initial uncertainty
    processNoise: 0.00001, // increase = more responsive, decrease = smoother
    reset: function() { this.lat = null; this.lng = null; this.variance = 1; },
    filter: function(lat, lng, accuracy) {
        // accuracy in meters → convert to approximate degree variance
        var accDeg = accuracy / 111320;
        var measurement_variance = accDeg * accDeg;
        if (this.lat === null) {
            this.lat = lat; this.lng = lng;
            this.variance = measurement_variance;
            return { lat: lat, lng: lng };
        }
        // Prediction step
        this.variance += this.processNoise;
        // Update step (Kalman gain)
        var K = this.variance / (this.variance + measurement_variance);
        this.lat = this.lat + K * (lat - this.lat);
        this.lng = this.lng + K * (lng - this.lng);
        this.variance = (1 - K) * this.variance;
        return { lat: this.lat, lng: this.lng };
    }
};

// ══════════════════════════════
//  Map & Marker basics
// ══════════════════════════════
var actStreetTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' });
var actSatelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: '&copy; Esri' });
var actHeatLayer = null;
var actRadiusCircle = null;
var actCurrentRadius = 0;
var actActiveLoc = null;

function switchActMapLayer(type) {
    if (!actMap) return;
    document.getElementById('btn-act-streets').classList.toggle('active', type === 'streets');
    document.getElementById('btn-act-satellite').classList.toggle('active', type === 'satellite');
    if (type === 'satellite') {
        actMap.removeLayer(actStreetTile);
        actMap.addLayer(actSatelliteTile);
    } else {
        actMap.removeLayer(actSatelliteTile);
        actMap.addLayer(actStreetTile);
    }
}

function toggleActHeatmap() {
    if (!actMap) return;
    var btn = document.getElementById('btn-act-heat');
    var isActive = btn.classList.toggle('active');
    if (isActive) {
        var points = geoActivities.map(function(a) { return [a.lat, a.lng, 0.8]; });
        if (!actHeatLayer) actHeatLayer = L.heatLayer(points, { radius: 25, blur: 15 });
        actMap.addLayer(actHeatLayer);
        Object.values(actMarkers).forEach(function(m) { actMap.removeLayer(m); });
    } else {
        if (actHeatLayer) actMap.removeLayer(actHeatLayer);
        buildMarkers();
    }
}

function filterActRadius(km, btn) {
    actCurrentRadius = km;
    document.querySelectorAll('#actMapModal .map-radius-pill').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    if (km > 0) {
        if (userLat === null || userLng === null) {
            locateAndCenterActUser(function() { updateActRadiusCircle(km); });
        } else {
            updateActRadiusCircle(km);
        }
    } else {
        if (actRadiusCircle) actMap.removeLayer(actRadiusCircle);
        buildMarkers();
    }
}

function updateActRadiusCircle(km) {
    if (userLat === null || userLng === null || !actMap) return;
    if (actRadiusCircle) actMap.removeLayer(actRadiusCircle);
    actRadiusCircle = L.circle([userLat, userLng], {
        radius: km * 1000,
        color: '#ea580c',
        fillColor: '#ea580c',
        fillOpacity: 0.08,
        weight: 2,
        dashArray: '6, 6'
    }).addTo(actMap);
    actMap.fitBounds(actRadiusCircle.getBounds());
    buildMarkers();
}

function locateAndCenterActUser(cb) {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function(pos) {
        userLat = pos.coords.latitude;
        userLng = pos.coords.longitude;
        if (actMap) actMap.setView([userLat, userLng], 16, { animate: true });
        buildMarkers();
        if (cb) cb();
    }, function(err) {
        console.warn('GPS location error:', err);
    }, { enableHighAccuracy: true, timeout: 8000 });
}

function showActBottomSheet(a) {
    actActiveLoc = a;
    var sheet = document.getElementById('actBottomSheet');
    var img = document.getElementById('act-bs-img');
    var fallback = document.getElementById('act-bs-fallback');
    if (a.image) {
        img.src = a.image;
        img.style.display = 'block';
        fallback.style.display = 'none';
    } else {
        img.style.display = 'none';
        fallback.style.display = 'flex';
    }

    document.getElementById('act-bs-title').textContent = a.title;
    document.getElementById('act-bs-location').textContent = (a.location || 'มหาวิทยาลัย') + ' • ' + (a.category_name || 'กิจกรรม');

    var badge = document.getElementById('act-bs-badge');
    badge.textContent = a.is_mandatory ? 'กิจกรรมบังคับ' : 'กิจกรรม';
    badge.className = 'badge ' + (a.is_mandatory ? 'badge-red' : 'badge-orange');

    var distText = '-', walkText = '-', driveText = '-';
    if (userLat !== null && userLng !== null) {
        var d = haversine(userLat, userLng, a.lat, a.lng);
        distText = formatDist(d);
        var distKm = d / 1000;
        walkText = '~' + Math.round((distKm / 4.8) * 60) + ' นาที';
        driveText = '~' + Math.max(1, Math.round((distKm / 35) * 60)) + ' นาที';
    }
    document.getElementById('act-bs-distance').textContent = distText;
    document.getElementById('act-bs-walk-eta').textContent = walkText;
    document.getElementById('act-bs-drive-eta').textContent = driveText;
    document.getElementById('act-bs-meta-info').textContent = a.date + ' (' + a.hours + ' ชม.)';

    document.getElementById('act-bs-detail-btn').href = a.url;
    document.getElementById('act-bs-gmaps').href = 'https://www.google.com/maps/dir/?api=1&destination=' + a.lat + ',' + a.lng;
    document.getElementById('act-bs-applemaps').href = 'https://maps.apple.com/?daddr=' + a.lat + ',' + a.lng;

    sheet.style.display = 'block';
}

function closeActBottomSheet() {
    document.getElementById('actBottomSheet').style.display = 'none';
    actActiveLoc = null;
}

function startNavFromBottomSheet() {
    if (!actActiveLoc) return;
    closeActBottomSheet();
    startRealtimeNav(actActiveLoc.id, actActiveLoc.lat, actActiveLoc.lng);
}

function openActivityMap(focusId) {
    var modal = document.getElementById('actMapModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    highlightId = focusId || null;

    if (!actMap) {
        actMap = L.map('actMapContainer', { zoomControl: false });
        L.control.zoom({ position: 'topright' }).addTo(actMap);
        actStreetTile.addTo(actMap);

        actMap.on('click', function(e) {
            if (e.originalEvent.target.id === 'actMapContainer' || e.originalEvent.target.classList.contains('leaflet-container')) {
                closeActBottomSheet();
            }
        });
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            buildMarkers();
        }, function() { buildMarkers(); }, { timeout: 5000 });
    } else { buildMarkers(); }
}

function buildMarkers() {
    Object.values(actMarkers).forEach(function(m) { actMap.removeLayer(m); });
    actMarkers = {};
    actLines.forEach(function(l) { actMap.removeLayer(l); });
    actLines = [];
    actDistLabels.forEach(function(l) { actMap.removeLayer(l); });
    actDistLabels = [];
    if (meMarker && !nav.active) { actMap.removeLayer(meMarker); meMarker = null; }

    var bounds = [];
    var filtered = geoActivities.filter(function(a) {
        if (actCurrentRadius > 0 && userLat !== null && userLng !== null) {
            var d = haversine(userLat, userLng, a.lat, a.lng);
            if (d > actCurrentRadius * 1000) return false;
        }
        return true;
    });

    filtered.forEach(function(a) {
        var isHL = (highlightId && a.id === highlightId);
        var icon = createCustomIcon(a, isHL);
        var marker = L.marker([a.lat, a.lng], { icon: icon }).addTo(actMap);

        marker.on('click', function() {
            showActBottomSheet(a);
        });

        if (isHL) {
            showActBottomSheet(a);
        }

        actMarkers[a.id] = marker;
        bounds.push([a.lat, a.lng]);
    });

    if (userLat !== null && userLng !== null && !nav.active) {
        var meIcon = L.divIcon({ className: '', html: '<div class="user-gps-dot"></div>', iconSize: [16, 16], iconAnchor: [8, 8] });
        meMarker = L.marker([userLat, userLng], { icon: meIcon, zIndexOffset: 1000 }).addTo(actMap).bindPopup('<b>ตำแหน่งของคุณ</b>');
        bounds.push([userLat, userLng]);
    }

    if (highlightId && actMarkers[highlightId]) {
        actMap.setView(actMarkers[highlightId].getLatLng(), 16);
    } else if (bounds.length > 1 && actCurrentRadius === 0) {
        actMap.fitBounds(bounds, { padding: [40, 40] });
    } else if (bounds.length === 1 && actCurrentRadius === 0) {
        actMap.setView(bounds[0], 15);
    }
    setTimeout(function() { actMap.invalidateSize(); }, 200);
}

// ══════════════════════════════════════════
//  START Real-time Navigation
// ══════════════════════════════════════════
function startRealtimeNav(actId, destLat, destLng) {
    if (userLat === null || userLng === null) return;
    var act = geoActivities.find(function(a) { return a.id === actId; });
    if (!act) return;

    // ── ล้าง state การนำทางเก่า (กรณีเปลี่ยนกิจกรรมระหว่างนำทาง) ──
    stopGpsWatch();
    removeNavHUD();
    if (nav.routeLine) { actMap.removeLayer(nav.routeLine); nav.routeLine = null; }
    if (nav.traveledLine) { actMap.removeLayer(nav.traveledLine); nav.traveledLine = null; }
    if (routingControl) { actMap.removeControl(routingControl); routingControl = null; }
    var arrivedEl = document.getElementById('navArrived');
    if (arrivedEl) arrivedEl.remove();
    matchBuffer = [];

    actMap.closePopup();
    nav.active = true;
    nav.destLat = destLat;
    nav.destLng = destLng;
    nav.actData = act;
    nav.currentStepIdx = 0;
    nav.offRouteCount = 0;
    nav.startTime = Date.now();
    nav.gpsLostSince = 0;
    nav.rerouteThrottle = 0;
    kalman.reset();

    // ซ่อนเส้นตรง
    actLines.forEach(function(l) { l.setStyle({ opacity: 0 }); });
    actDistLabels.forEach(function(l) { actMap.removeLayer(l); });

    // เปลี่ยน UI
    document.getElementById('btnClearRoute').style.display = '';
    document.getElementById('btnClearRoute').textContent = '✕ หยุดนำทาง';
    document.getElementById('mapTitle').textContent = 'กำลังนำทาง...';

    // สร้าง user marker แบบใช้รูป navigation arrow
    if (meMarker) { actMap.removeLayer(meMarker); meMarker = null; }
    var navIcon = L.icon({
        iconUrl: '/images/nav-arrow.png',
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        className: 'nav-arrow-icon'
    });
    meMarker = L.marker([userLat, userLng], { icon: navIcon, zIndexOffset: 2000, rotationAngle: 0 }).addTo(actMap);

    // สร้าง HUD overlays
    createNavHUD();

    // Zoom เข้าไปตำแหน่งผู้ใช้
    actMap.setView([userLat, userLng], 17, { animate: true, duration: 1 });

    // คำนวณเส้นทางแรก แล้วเริ่ม watchPosition
    fetchRoute(userLat, userLng, destLat, destLng, function() {
        startGpsWatch();
    });
}

// ══════════════════════════════════════
//  Fetch Route from OSRM
// ══════════════════════════════════════
function fetchRoute(fromLat, fromLng, toLat, toLng, cb) {
    var url = 'https://router.project-osrm.org/route/v1/driving/'
        + fromLng + ',' + fromLat + ';' + toLng + ',' + toLat
        + '?overview=full&geometries=geojson&steps=true';

    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (data.code !== 'Ok' || !data.routes || !data.routes.length) {
            showNavError();
            return;
        }
        var route = data.routes[0];
        nav.routeCoords = route.geometry.coordinates.map(function(c) { return [c[1], c[0]]; });

        // Parse steps
        nav.routeSteps = [];
        route.legs[0].steps.forEach(function(step) {
            nav.routeSteps.push({
                text: step.maneuver.type.replace(/_/g, ' ') + (step.name ? ' — ' + step.name : ''),
                type: maneuverToIcon(step.maneuver.type, step.maneuver.modifier),
                distance: step.distance,
                duration: step.duration,
                coord: [step.maneuver.location[1], step.maneuver.location[0]]
            });
        });

        // วาดเส้นทาง
        if (nav.routeLine) actMap.removeLayer(nav.routeLine);
        if (nav.traveledLine) actMap.removeLayer(nav.traveledLine);
        nav.routeLine = L.polyline(nav.routeCoords, {
            color: '#ea580c', weight: 6, opacity: 0.8
        }).addTo(actMap);
        nav.traveledLine = L.polyline([], {
            color: '#94a3b8', weight: 6, opacity: 0.5
        }).addTo(actMap);

        // แสดง directions panel
        var totalDist = route.legs[0].distance;
        var totalTime = route.legs[0].duration;
        showNavDirectionsPanel(totalDist, totalTime);

        actMap.fitBounds(nav.routeLine.getBounds(), { padding: [60, 60] });
        setTimeout(function() { actMap.invalidateSize(); }, 200);

        if (cb) cb();
    }).catch(function() {
        showNavError();
    });
}

// ══════════════════════════════════════
//  GPS Watch — adaptive frequency
// ══════════════════════════════════════
function startGpsWatch() {
    if (!navigator.geolocation || nav.watchId) return;
    nav.watchId = navigator.geolocation.watchPosition(
        onGpsUpdate, onGpsError,
        { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
    );
}

function stopGpsWatch() {
    if (nav.watchId !== null) {
        navigator.geolocation.clearWatch(nav.watchId);
        nav.watchId = null;
    }
}

function onGpsUpdate(pos) {
    var rawLat = pos.coords.latitude;
    var rawLng = pos.coords.longitude;
    var accuracy = pos.coords.accuracy || 20;
    var heading = pos.coords.heading;
    var speed = pos.coords.speed || 0;

    nav.lastGpsTime = Date.now();
    nav.gpsLostSince = 0;
    nav.speed = speed;

    // ซ่อน tunnel badge
    if (nav.tunnelEl) nav.tunnelEl.style.display = 'none';

    // Kalman filter
    var filtered = kalman.filter(rawLat, rawLng, accuracy);
    userLat = filtered.lat;
    userLng = filtered.lng;

    // Heading
    if (heading !== null && !isNaN(heading)) {
        nav.heading = heading;
    } else if (speed > 1) {
        // คำนวณ heading จาก 2 จุดล่าสุด
        var prev = meMarker ? meMarker.getLatLng() : null;
        if (prev) {
            nav.heading = calcBearing(prev.lat, prev.lng, userLat, userLng);
        }
    }

    // อัปเดต speed badge
    updateSpeedBadge(speed);

    // Adaptive GPS frequency
    adjustGpsFrequency(speed);

    // อัปเดต marker position (smooth animation)
    smoothMoveMarker(userLat, userLng, nav.heading);

    // ── Core navigation logic ──
    if (nav.active && nav.routeCoords.length > 0) {
        // หาจุดที่ใกล้ที่สุดบนเส้นทาง
        var snap = snapToRoute(userLat, userLng);

        // Off-route detection (>50m จากเส้นทาง)
        if (snap.dist > 50) {
            nav.offRouteCount++;
            if (nav.offRouteCount >= 3 && Date.now() - nav.rerouteThrottle > 5000) {
                // Re-route!
                nav.rerouteThrottle = Date.now();
                nav.offRouteCount = 0;
                reroute();
                return;
            }
        } else {
            nav.offRouteCount = 0;
        }

        // อัปเดต traveled line
        if (snap.idx >= 0) {
            var traveled = nav.routeCoords.slice(0, snap.idx + 1);
            traveled.push([userLat, userLng]);
            nav.traveledLine.setLatLngs(traveled);

            // อัปเดตเส้นที่เหลือ
            var remaining = [[userLat, userLng]].concat(nav.routeCoords.slice(snap.idx + 1));
            nav.routeLine.setLatLngs(remaining);
        }

        // อัปเดต current step
        updateCurrentStep(userLat, userLng);

        // อัปเดต HUD
        var remainDist = calcRemainingDist(snap.idx, userLat, userLng);
        var remainTime = estimateTime(remainDist, speed);
        updateHUD(remainDist, remainTime);

        // ตรวจสอบถึงจุดหมาย (<30m)
        var distToDest = haversine(userLat, userLng, nav.destLat, nav.destLng) * 1000;
        if (distToDest < 30) {
            onArrived();
        }
    }
}

function onGpsError(err) {
    // GPS lost — tunnel handling
    if (!nav.gpsLostSince) nav.gpsLostSince = Date.now();

    var lostMs = Date.now() - nav.gpsLostSince;
    if (nav.tunnelEl && lostMs > 3000) {
        nav.tunnelEl.style.display = '';
    }

    // Dead reckoning: estimate position from last known speed & heading
    if (nav.active && nav.speed > 0.5 && lostMs < 30000) {
        var dt = Math.min(lostMs / 1000, 5); // max 5s prediction
        var distM = nav.speed * dt;
        var newPos = destPoint(userLat, userLng, nav.heading, distM);
        userLat = newPos.lat;
        userLng = newPos.lng;
        smoothMoveMarker(userLat, userLng, nav.heading);
    }
}

// ══════════════════════════════════════
//  Map Matching via OSRM /match
// ══════════════════════════════════════
var matchBuffer = [];
var matchThrottle = 0;

function tryMapMatch(lat, lng, timestamp) {
    matchBuffer.push({ lat: lat, lng: lng, t: timestamp });
    if (matchBuffer.length < 3) return; // ต้องมีอย่างน้อย 3 จุด
    if (Date.now() - matchThrottle < 3000) return; // throttle 3 วิ
    matchThrottle = Date.now();

    var coords = matchBuffer.slice(-5).map(function(p) { return p.lng + ',' + p.lat; }).join(';');
    var timestamps = matchBuffer.slice(-5).map(function(p) { return Math.round(p.t / 1000); }).join(';');

    fetch('https://router.project-osrm.org/match/v1/driving/' + coords + '?timestamps=' + timestamps + '&geometries=geojson&overview=false')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.code === 'Ok' && data.matchings && data.matchings.length > 0) {
                var lastTracepoint = data.tracepoints.filter(function(t) { return t !== null; }).pop();
                if (lastTracepoint) {
                    var matched = lastTracepoint.location;
                    userLat = matched[1];
                    userLng = matched[0];
                    smoothMoveMarker(userLat, userLng, nav.heading);
                }
            }
        }).catch(function() {});
}

// ══════════════════════════════════════
//  Re-routing
// ══════════════════════════════════════
function reroute() {
    if (!nav.active) return;
    document.getElementById('mapTitle').textContent = 'กำลังคำนวณเส้นทางใหม่...';
    fetchRoute(userLat, userLng, nav.destLat, nav.destLng, function() {
        document.getElementById('mapTitle').textContent = 'กำลังนำทาง...';
        nav.currentStepIdx = 0;
    });
}

// ══════════════════════════════════════
//  Snap to route & off-route detection
// ══════════════════════════════════════
function snapToRoute(lat, lng) {
    var minDist = Infinity, bestIdx = 0;
    for (var i = 0; i < nav.routeCoords.length; i++) {
        var d = haversine(lat, lng, nav.routeCoords[i][0], nav.routeCoords[i][1]) * 1000;
        if (d < minDist) { minDist = d; bestIdx = i; }
    }
    return { dist: minDist, idx: bestIdx };
}

// ══════════════════════════════════════
//  Turn-by-turn updates
// ══════════════════════════════════════
function updateCurrentStep(lat, lng) {
    if (!nav.routeSteps.length) return;
    // หา step ที่ใกล้ที่สุด
    for (var i = nav.currentStepIdx; i < nav.routeSteps.length; i++) {
        var step = nav.routeSteps[i];
        var d = haversine(lat, lng, step.coord[0], step.coord[1]) * 1000;
        if (d < 30 && i > nav.currentStepIdx) {
            nav.currentStepIdx = i;
            break;
        }
    }

    // อัปเดต instruction
    var nextIdx = Math.min(nav.currentStepIdx + 1, nav.routeSteps.length - 1);
    var nextStep = nav.routeSteps[nextIdx];
    if (nextStep && nav.instrEl) {
        var distToNext = haversine(lat, lng, nextStep.coord[0], nextStep.coord[1]) * 1000;
        var distStr = distToNext >= 1000 ? (distToNext/1000).toFixed(1) + ' กม.' : Math.round(distToNext) + ' ม.';
        nav.instrEl.innerHTML = nextStep.type + ' อีก ' + distStr + ' — ' + escHtml(nextStep.text);
    }

    // Highlight active step in panel
    var steps = document.querySelectorAll('.dir-step');
    steps.forEach(function(el, idx) {
        el.classList.toggle('dir-step-active', idx === nav.currentStepIdx);
    });
    // Auto-scroll
    var activeEl = document.querySelector('.dir-step-active');
    if (activeEl) activeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ══════════════════════════════════════
//  Smooth marker animation
// ══════════════════════════════════════
function smoothMoveMarker(lat, lng, heading) {
    if (!meMarker) return;
    var cur = meMarker.getLatLng();
    var frames = 10;
    var dLat = (lat - cur.lat) / frames;
    var dLng = (lng - cur.lng) / frames;
    var frame = 0;
    function step() {
        frame++;
        meMarker.setLatLng([cur.lat + dLat * frame, cur.lng + dLng * frame]);
        if (frame < frames) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);

    // Heading - หมุนรูป arrow icon
    if (heading !== null && !isNaN(heading) && meMarker.setRotationAngle) {
        meMarker.setRotationAngle(heading);
    } else if (heading !== null && !isNaN(heading)) {
        // Fallback: ใช้ CSS transform
        var icon = meMarker.getElement();
        if (icon) {
            icon.style.transform = 'rotate(' + heading + 'deg)';
            icon.style.transformOrigin = 'center center';
        }
    }

    // Follow user (map pan)
    if (nav.active) {
        actMap.panTo([lat, lng], { animate: true, duration: 0.5 });
    }
}

// ══════════════════════════════════════
//  HUD
// ══════════════════════════════════════
function createNavHUD() {
    var container = document.getElementById('actMapContainer');

    // HUD bar
    nav.hudEl = document.createElement('div');
    nav.hudEl.className = 'nav-hud';
    nav.hudEl.id = 'navHud';
    nav.hudEl.innerHTML = '<div class="nav-hud-item"><div class="nav-hud-value" id="hudDist">-</div><div class="nav-hud-label">ระยะทาง</div></div>'
        + '<div class="nav-hud-divider"></div>'
        + '<div class="nav-hud-item"><div class="nav-hud-value" id="hudEta">-</div><div class="nav-hud-label">ETA</div></div>'
        + '<div class="nav-hud-divider"></div>'
        + '<div class="nav-hud-item"><div class="nav-hud-value" id="hudTime">-</div><div class="nav-hud-label">ถึงโดยประมาณ</div></div>';
    container.appendChild(nav.hudEl);

    // Instruction bar
    nav.instrEl = document.createElement('div');
    nav.instrEl.className = 'nav-hud-instruction';
    nav.instrEl.id = 'navInstr';
    nav.instrEl.textContent = 'กำลังคำนวณ...';
    container.appendChild(nav.instrEl);

    // Tunnel badge
    nav.tunnelEl = document.createElement('div');
    nav.tunnelEl.className = 'nav-tunnel-badge';
    nav.tunnelEl.id = 'navTunnel';
    nav.tunnelEl.innerHTML = '<svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>สัญญาณ GPS อ่อน';
    container.appendChild(nav.tunnelEl);

    // Speed badge
    nav.speedEl = document.createElement('div');
    nav.speedEl.className = 'nav-speed-badge';
    nav.speedEl.id = 'navSpeed';
    nav.speedEl.innerHTML = '<div class="nav-speed-value">0</div><div class="nav-speed-unit">km/h</div>';
    container.appendChild(nav.speedEl);
    nav.speedEl.style.display = 'flex';
}

function removeNavHUD() {
    ['navHud', 'navInstr', 'navTunnel', 'navSpeed'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.remove();
    });
    nav.hudEl = null; nav.instrEl = null; nav.tunnelEl = null; nav.speedEl = null;
}

function updateHUD(remainDistM, remainTimeSec) {
    var distEl = document.getElementById('hudDist');
    var etaEl = document.getElementById('hudEta');
    var timeEl = document.getElementById('hudTime');
    if (!distEl) return;

    distEl.textContent = remainDistM >= 1000 ? (remainDistM/1000).toFixed(1) + ' กม.' : Math.round(remainDistM) + ' ม.';
    etaEl.textContent = formatTime(remainTimeSec);

    var arrival = new Date(Date.now() + remainTimeSec * 1000);
    timeEl.textContent = arrival.getHours().toString().padStart(2,'0') + ':' + arrival.getMinutes().toString().padStart(2,'0');

    // อัปเดต panel summary ด้วย
    var panelDist = document.querySelector('.dir-summary-value');
    if (panelDist) panelDist.textContent = distEl.textContent;
}

function updateSpeedBadge(speedMs) {
    var el = document.getElementById('navSpeed');
    if (!el) return;
    var kmh = Math.round(speedMs * 3.6);
    el.querySelector('.nav-speed-value').textContent = kmh;
}

// ══════════════════════════════════════
//  Adaptive GPS frequency (battery)
// ══════════════════════════════════════
function adjustGpsFrequency(speedMs) {
    // ความเร็วสูง → poll บ่อยขึ้น, ความเร็วต่ำ → poll น้อยลง
    // watchPosition ไม่ control ได้โดยตรง แต่เราใช้ processNoise ของ Kalman
    var kmh = speedMs * 3.6;
    if (kmh > 30) {
        kalman.processNoise = 0.00003; // responsive มากขึ้น
    } else if (kmh < 5) {
        kalman.processNoise = 0.000005; // smooth มากขึ้น ประหยัด battery
    } else {
        kalman.processNoise = 0.00001;
    }
}

// ══════════════════════════════════════
//  Directions Panel (for nav mode)
// ══════════════════════════════════════
function showNavDirectionsPanel(totalDist, totalTime) {
    var panel = document.getElementById('directionsPanel');
    panel.style.display = '';

    var distStr = totalDist >= 1000 ? (totalDist / 1000).toFixed(1) + ' กม.' : Math.round(totalDist) + ' ม.';
    var timeStr = formatTime(totalTime);
    var act = nav.actData;

    var gUrl = 'https://www.google.com/maps/dir/?api=1&origin=' + userLat + ',' + userLng + '&destination=' + nav.destLat + ',' + nav.destLng;
    var aUrl = 'https://maps.apple.com/?saddr=' + userLat + ',' + userLng + '&daddr=' + nav.destLat + ',' + nav.destLng;

    var html = '<div class="dir-header">'
        + '<h3><svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>' + escHtml(act.title) + '</h3>'
        + '<div class="dir-summary">'
        + '<div class="dir-summary-item"><div class="dir-summary-value">' + distStr + '</div><div class="dir-summary-label">ระยะทาง</div></div>'
        + '<div class="dir-summary-item"><div class="dir-summary-value">' + timeStr + '</div><div class="dir-summary-label">เวลาเดินทาง</div></div>'
        + '</div>'
        + '<div class="dir-ext-links">'
        + '<a href="' + gUrl + '" target="_blank" class="map-dir-btn map-dir-google">เปิด Google Maps</a>'
        + '<a href="' + aUrl + '" target="_blank" class="map-dir-btn map-dir-apple">เปิด Apple Maps</a>'
        + '</div></div>';

    if (nav.routeSteps.length > 0) {
        html += '<ul class="dir-steps">';
        nav.routeSteps.forEach(function(step, i) {
            var stepDist = step.distance >= 1000 ? (step.distance / 1000).toFixed(1) + ' กม.' : Math.round(step.distance) + ' ม.';
            html += '<li class="dir-step' + (i === 0 ? ' dir-step-active' : '') + '">'
                + '<span class="dir-step-icon">' + step.type + '</span>'
                + '<div class="dir-step-text">' + escHtml(step.text) + '<div class="dir-step-dist">' + stepDist + '</div></div>'
                + '</li>';
        });
        html += '</ul>';
    }
    panel.innerHTML = html;
    setTimeout(function() { actMap.invalidateSize(); }, 200);
}

function showNavError() {
    if (nav.instrEl) nav.instrEl.textContent = 'ไม่สามารถคำนวณเส้นทาง — ลองใช้ Google Maps';
}

// ══════════════════════════════════════
//  Arrived
// ══════════════════════════════════════
function onArrived() {
    stopGpsWatch();
    removeNavHUD();
    var container = document.getElementById('actMapContainer');
    var el = document.createElement('div');
    el.className = 'nav-arrived';
    el.id = 'navArrived';
    el.innerHTML = '<h2><svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>ถึงจุดหมายแล้ว!</h2>'
        + '<p style="color:#64748b;font-size:.85rem;margin-bottom:1rem;">' + escHtml(nav.actData.title) + '</p>'
        + '<a href="' + nav.actData.url + '" class="map-popup-link" style="font-size:.9rem;padding:8px 20px;">ดูรายละเอียดกิจกรรม</a>'
        + '<br><button onclick="clearRoute()" style="margin-top:.75rem;padding:6px 16px;border:1px solid #e2e8f0;background:#fff;border-radius:8px;cursor:pointer;font-size:.85rem;">กลับแผนที่</button>';
    container.appendChild(el);
}

// ══════════════════════════════════════
//  Clear / Stop Navigation
// ══════════════════════════════════════
function clearRoute() {
    stopGpsWatch();
    nav.active = false;
    isRouting = false;
    kalman.reset();
    matchBuffer = [];

    if (routingControl) { actMap.removeControl(routingControl); routingControl = null; }
    if (nav.routeLine) { actMap.removeLayer(nav.routeLine); nav.routeLine = null; }
    if (nav.traveledLine) { actMap.removeLayer(nav.traveledLine); nav.traveledLine = null; }

    removeNavHUD();
    var arrived = document.getElementById('navArrived');
    if (arrived) arrived.remove();

    document.getElementById('directionsPanel').style.display = 'none';
    document.getElementById('directionsPanel').innerHTML = '';
    document.getElementById('btnClearRoute').style.display = 'none';
    document.getElementById('btnClearRoute').textContent = '✕ หยุดนำทาง';
    document.getElementById('mapTitle').textContent = 'แผนที่กิจกรรม';

    buildMarkers();
}

// ══════════════════════════════════════
//  Helper functions
// ══════════════════════════════════════
function calcRemainingDist(snapIdx, lat, lng) {
    var total = 0;
    if (nav.routeCoords.length === 0) return 0;
    // distance from current pos to snap point
    total += haversine(lat, lng, nav.routeCoords[snapIdx][0], nav.routeCoords[snapIdx][1]) * 1000;
    // distance along remaining route
    for (var i = snapIdx; i < nav.routeCoords.length - 1; i++) {
        total += haversine(nav.routeCoords[i][0], nav.routeCoords[i][1], nav.routeCoords[i+1][0], nav.routeCoords[i+1][1]) * 1000;
    }
    return total;
}

function estimateTime(distM, speedMs) {
    if (speedMs > 1) return distM / speedMs;
    // default ~30km/h
    return distM / 8.33;
}

function calcBearing(lat1, lng1, lat2, lng2) {
    var dLng = (lng2 - lng1) * Math.PI / 180;
    var y = Math.sin(dLng) * Math.cos(lat2 * Math.PI / 180);
    var x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180)
          - Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos(dLng);
    return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
}

function destPoint(lat, lng, bearing, distM) {
    var R = 6371000;
    var d = distM / R;
    var br = bearing * Math.PI / 180;
    var lat1 = lat * Math.PI / 180;
    var lng1 = lng * Math.PI / 180;
    var lat2 = Math.asin(Math.sin(lat1) * Math.cos(d) + Math.cos(lat1) * Math.sin(d) * Math.cos(br));
    var lng2 = lng1 + Math.atan2(Math.sin(br) * Math.sin(d) * Math.cos(lat1), Math.cos(d) - Math.sin(lat1) * Math.sin(lat2));
    return { lat: lat2 * 180 / Math.PI, lng: lng2 * 180 / Math.PI };
}

function maneuverToIcon(type, modifier) {
    // SVG icons for navigation directions
    var icons = {
        'straight': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>',
        'left': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
        'slight left': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>',
        'sharp left': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>',
        'right': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>',
        'slight right': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>',
        'sharp right': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"/></svg>',
        'uturn': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>',
        'depart': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
        'arrive': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'merge': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
        'fork': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
        'roundabout': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
        'default': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>'
    };
    
    // Check modifier first
    if (modifier && icons[modifier]) return icons[modifier];
    // Check type
    if (icons[type]) return icons[type];
    // Default
    return icons['default'];
}

function createCustomIcon(a, isHighlight) {
    var hlClass = isHighlight ? ' map-marker-highlight' : '';
    var html;
    if (a.image) {
        html = '<div class="' + hlClass + '"><img src="' + a.image + '" class="map-marker-img"></div>';
    } else {
        html = '<div class="' + hlClass + '"><span class="map-marker-name">' + escHtml(a.title) + '</span></div>';
    }
    return L.divIcon({
        className: '', html: html,
        iconSize: a.image ? [44, 44] : [100, 24],
        iconAnchor: a.image ? [22, 22] : [50, 12],
        popupAnchor: [0, a.image ? -26 : -16]
    });
}

function closeMapModal() {
    clearRoute();
    document.getElementById('actMapModal').style.display = 'none';
    document.body.style.overflow = '';
}

function haversine(lat1, lon1, lat2, lon2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat/2) * Math.sin(dLat/2)
          + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
          * Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatDist(km) {
    if (km < 1) return Math.round(km * 1000) + ' เมตร';
    return km.toFixed(1) + ' กม.';
}

function formatTime(seconds) {
    if (seconds < 60) return '< 1 นาที';
    var mins = Math.round(seconds / 60);
    if (mins < 60) return mins + ' นาที';
    var hrs = Math.floor(mins / 60);
    var rm = mins % 60;
    return hrs + ' ชม.' + (rm > 0 ? ' ' + rm + ' น.' : '');
}

function escHtml(t) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(t || ''));
    return d.innerHTML;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMapModal();
});

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
        // Fallback for browsers without IntersectionObserver
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
