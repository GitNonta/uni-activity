{{-- หน้ารายละเอียดงาน: ข้อมูลครบ + แผนที่ + สมัคร + คอมเมนต์ + สอบถาม --}}
@extends('layouts.app')
@section('title', $job->title)

@section('content')
<style>
    /* ── Navigation HUD Styles ── */
    .nav-relative { position: relative; overflow: hidden; border-radius: 12px; }
    .nav-hud {
        position: absolute; top: 12px; left: 50%; transform: translateX(-50%); z-index: 1000;
        background: rgba(0,0,0,0.85); color: #fff; border-radius: 16px; padding: 8px 16px;
        display: flex; gap: 12px; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        backdrop-filter: blur(8px); min-width: 240px; justify-content: center; visibility: hidden; opacity: 0; transition: all 0.3s ease;
    }
    .nav-hud.active { visibility: visible; opacity: 1; }
    .nav-hud-item { text-align: center; }
    .nav-hud-value { font-size: 1.1rem; font-weight: 700; line-height: 1.2; }
    .nav-hud-label { font-size: 0.6rem; opacity: 0.7; text-transform: uppercase; }
    .nav-hud-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.2); }
    .nav-me-dot {
        position: relative; width: 18px; height: 18px; background: #3b82f6; border: 3px solid #fff;
        border-radius: 50%; box-shadow: 0 0 10px rgba(59,130,246,0.5); z-index: 10;
    }
    .nav-me-dot-pulse {
        position: absolute; top: 50%; left: 50%; width: 36px; height: 36px; margin: -18px 0 0 -18px;
        background: rgba(59,130,246,0.2); border-radius: 50%; animation: navPulse 2s ease-out infinite;
    }
    @keyframes navPulse { 0% { transform: scale(0.5); opacity: 1; } 100% { transform: scale(2.5); opacity: 0; } }
    .nav-speed-badge {
        position: absolute; bottom: 60px; left: 12px; z-index: 1000;
        background: rgba(0,0,0,0.75); color: #fff; border-radius: 50%; width: 48px; height: 48px;
        display: none; align-items: center; justify-content: center; flex-direction: column; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    .nav-hud-instruction {
        position: absolute; top: 76px; left: 50%; transform: translateX(-50%); z-index: 1000;
        background: rgba(79,70,229,0.95); color: #fff; border-radius: 12px; padding: 6px 14px;
        font-size: 0.85rem; font-weight: 600; box-shadow: 0 4px 16px rgba(79,70,229,0.3);
        max-width: 90%; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: none;
    }
    .nav-tunnel-badge {
        position: absolute; top: 12px; right: 12px; z-index: 1000;
        background: #fbbf24; color: #92400e; border-radius: 8px; padding: 4px 10px;
        font-size: 0.75rem; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.15); display: none;
    }
    .nav-arrived {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); z-index: 1100;
        background: #fff; border-radius: 16px; padding: 1.5rem; text-align: center;
        box-shadow: 0 8px 40px rgba(0,0,0,0.3); max-width: 280px; display: none;
    }
    .dir-panel { margin-top: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; display: none; }
    .dir-header { padding: 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; }
    .dir-steps { list-style: none; padding: 0; margin: 0; max-height: 250px; overflow-y: auto; }
    .dir-step { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; display: flex; gap: 0.75rem; font-size: 0.8rem; }
    .dir-step-active { background: #eff6ff; border-left: 3px solid #4f46e5; }
    .dir-step-icon { width: 28px; height: 28px; background: #e0f2fe; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .dir-step-text { flex: 1; line-height: 1.4; }
    .dir-step-dist { color: #64748b; font-size: 0.75rem; margin-top: 2px; }
</style>

<a href="{{ route('jobs.index') }}" class="text-sm text-primary">&larr; กลับรายการงาน</a>

<div class="card mt-2">
    {{-- รูปภาพ --}}
    @if($job->image_path)
        <img src="{{ Storage::url($job->image_path) }}" alt="{{ $job->title }}" class="activity-hero-image">
    @else
        <div class="act-card-img" style="background:linear-gradient(135deg,{{ $job->job_type === 'parttime' ? '#f97316,#fb923c' : '#3b82f6,#60a5fa' }});height:120px;">
            <svg class="icon-xl" style="color:rgba(255,255,255,.3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
    @endif

    <div class="card-body">
        {{-- Badges --}}
        <div class="flex items-center gap-2 mb-2" style="flex-wrap:wrap;">
            <span class="badge {{ $job->job_type === 'parttime' ? 'job-badge-parttime' : 'job-badge-general' }}" style="font-size:.8rem;">
                {{ $job->job_type === 'parttime' ? 'Part-time' : 'งานทั่วไป' }}
            </span>
            @if($job->status === 'open')
                <span class="badge badge-green flex items-center gap-1"><svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg> เปิดรับสมัคร</span>
            @elseif($job->status === 'closed')
                <span class="badge badge-red flex items-center gap-1"><svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg> ปิดรับสมัคร</span>
            @else
                <span class="badge badge-gray flex items-center gap-1"><svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg> เสร็จสิ้น</span>
            @endif
        </div>

        <h1 class="font-bold" style="font-size:1.25rem;">{{ $job->title }}</h1>

        @if($job->description)
            <p class="text-muted text-sm mt-2">{{ $job->description }}</p>
        @endif

        <hr class="divider">

        {{-- ข้อมูลงาน --}}
        <div class="grid-2" style="font-size:.875rem;">
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">ตำแหน่ง</span>
                    <p class="font-semi">{{ $job->position }}</p>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">จำนวนรับ</span>
                    <p class="font-semi">{{ $job->quota }} คน</p>
                </div>
            </div>
            @if($job->work_period)
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">ช่วงเวลางาน</span>
                    <p class="font-semi">{{ $job->work_period }}</p>
                </div>
            </div>
            @endif
            @if($job->compensation)
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">ค่าตอบแทน</span>
                    <p class="font-semi">{{ $job->compensation }}</p>
                </div>
            </div>
            @endif
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">สถานที่</span>
                    <p class="font-semi">{{ $job->location }}</p>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">ระยะเวลางาน</span>
                    <p class="font-semi">{{ $job->start_date->format('d/m/Y') }}{{ $job->end_date ? ' – ' . $job->end_date->format('d/m/Y') : '' }}</p>
                </div>
            </div>
            @if($job->dresscode)
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">การแต่งกาย</span>
                    <p class="font-semi">{{ $job->dresscode }}</p>
                </div>
            </div>
            @endif
            <div class="flex items-start gap-2">
                <div class="bg-blue-50 p-2 rounded-lg" style="background:#eff6ff;color:#2563eb;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <div>
                    <span class="text-muted block" style="font-size:.75rem;">เพศที่รับ</span>
                    <p class="font-semi">{{ $job->gender === 'male' ? 'ชาย' : ($job->gender === 'female' ? 'หญิง' : 'ไม่จำกับ') }}</p>
                </div>
            </div>
        </div>

        @if($job->note)
        <div class="alert alert-info text-sm mt-4" style="background:#fef3c7;color:#92400e;border-color:#fde68a;">
            <strong>* หมายเหตุ:</strong> {{ $job->note }}
        </div>
        @endif

        <hr class="divider">

        {{-- แผนที่ --}}
        @if($job->hasGeolocation())
        <div style="margin-bottom:1rem;">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semi text-sm flex items-center gap-1">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    แผนที่สถานที่งาน
                </p>
                <button type="button" onclick="openDirections({{ $job->latitude }}, {{ $job->longitude }})" class="btn btn-outline btn-sm" style="padding: 2px 10px; font-size: 11px;">
                    เปิดแอปแผนที่
                </button>
            </div>
            
                <div id="jobDetailMap" style="height:200px;border-radius:8px;border:1px solid #e2e8f0;margin-top:8px;"></div>
            </div>

            <a href="{{ route('jobs.index', ['showMap' => $job->id, 'autoNav' => 1]) }}" class="btn btn-primary btn-block btn-sm mt-2 text-center" style="text-decoration:none;">
                <svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                ดูแผนที่และเริ่มบอกเส้นทาง
            </a>

        </div>
        <hr class="divider">
        @endif

        {{-- Progress Bar --}}
        <div class="mb-4">
            <div class="flex justify-between text-sm mb-1">
                <span class="text-muted">ผู้ได้รับการยืนยัน</span>
                <span class="font-semi">{{ $job->confirmed_applicants }} / {{ $job->quota }} คน</span>
            </div>
            <div class="progress">
                @php $pct = $job->progress_percent; @endphp
                <div class="progress-bar {{ $pct >= 100 ? 'red' : ($pct >= 70 ? 'yellow' : 'green') }}" style="width:{{ $pct }}%"></div>
            </div>
            <p class="text-xs text-muted mt-1 flex items-center gap-1">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                ผู้สมัครทั้งหมด: {{ $job->total_applicants }} คน
            </p>
        </div>

        {{-- ปุ่มสมัคร / สถานะ --}}
        @auth
            @if($userApplication)
                <div class="alert {{ $userApplication->status === 'confirmed' ? 'alert-success' : ($userApplication->status === 'rejected' ? 'alert-error' : 'alert-info') }} text-sm" style="{{ $userApplication->status === 'pending' ? 'background:#fef3c7;color:#92400e;border-color:#fde68a;' : '' }}">
                    @if($userApplication->status === 'pending')
                        <svg style="width:16px;height:16px;display:inline;margin-right:4px;vertical-align:-3px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> คุณได้สมัครงานนี้แล้ว — <strong>รอการพิจารณา</strong>
                    @elseif($userApplication->status === 'confirmed')
                        <svg style="width:16px;height:16px;display:inline;margin-right:4px;color:#16a34a;vertical-align:-3px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> <strong>ได้รับการยืนยัน</strong>แล้ว
                    @else
                        <svg style="width:16px;height:16px;display:inline;margin-right:4px;color:#dc2626;vertical-align:-3px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> <strong>ไม่ผ่านการพิจารณา</strong>
                    @endif
                </div>
            @elseif($job->isOpen() && $job->hasAvailableSlots())
                <form method="POST" action="{{ route('jobs.apply', $job->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block btn-lg" onclick="return confirm('ยืนยันสมัครงานนี้?')">
                        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        สมัครงาน
                    </button>
                </form>
            @else
                <button disabled class="btn btn-outline btn-block">ไม่สามารถสมัครได้ในขณะนี้</button>
            @endif
        @endauth
    </div>
</div>

{{-- ปุ่มคอมเมนต์ + สอบถาม --}}
<div class="flex gap-2 mt-4 mb-2" style="flex-wrap:wrap;">
    <button class="btn btn-outline flex-1" onclick="document.getElementById('commentSection').scrollIntoView({behavior:'smooth'})">
        <svg style="width:14px;height:14px;display:inline;margin-right:2px;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> คอมเมนต์ ({{ $comments->count() }})
    </button>
    @auth
    <button class="btn btn-primary flex-1" id="openChatBtn" onclick="if(window.openChatWidget){ window.openChatWidget(); window.showChatView({{ $job->id }}, '{{ addslashes($job->title) }}'); }">
        <svg style="width:14px;height:14px;display:inline;margin-right:2px;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> สอบถามเพิ่มเติม
    </button>
    @endauth
</div>

@auth
{{-- ── Chat Popup Modal ── --}}
<div id="chatPopup" style="display:none;position:fixed;inset:0;z-index:9000;align-items:center;justify-content:center;background:rgba(0,0,0,.45);padding:1rem;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:480px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">

        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.1rem;background:#4f46e5;color:#fff;">
            <div>
                <p style="margin:0;font-weight:700;font-size:.95rem;display:flex;align-items:center;gap:4px;">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    สอบถามเพิ่มเติม
                </p>
                <p style="margin:0;font-size:.75rem;opacity:.8;">{{ $job->title }}</p>
            </div>
            <button onclick="closeChatPopup()" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;line-height:1;padding:.2rem .4rem;">✕</button>
        </div>

        {{-- Message list (populated dynamically via AJAX on open) --}}
        <div id="popupChatWindow" style="flex:1;overflow-y:auto;padding:.85rem;display:flex;flex-direction:column;gap:.5rem;background:#f8fafc;"></div>

        {{-- Input --}}
        <div style="border-top:1px solid #e2e8f0;padding:.65rem .85rem;background:#fff;">
            <div id="popupTypingLabel" style="display:none;align-items:center;font-size:.72rem;color:#6366f1;margin-bottom:.3rem;">
                <svg style="width:12px;height:12px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                ผู้ดูแลกำลังพิมพ์...
            </div>
            <form id="popupChatForm" enctype="multipart/form-data">
                @csrf
                <div id="popupAttachPreview" style="display:none;gap:.4rem;flex-wrap:wrap;margin-bottom:.4rem;"></div>
                <div style="display:flex;gap:.4rem;align-items:flex-end;">
                    <label style="cursor:pointer;padding:.48rem .6rem;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:1rem;line-height:1;flex-shrink:0;" title="แนบไฟล์">
                        <svg style="width:16px;height:16px;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        <input type="file" id="popupFileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
                    </label>
                    <textarea id="popupMsgInput" name="message" rows="1" placeholder="พิมพ์คำถามหรือข้อความ..." style="flex:1;resize:none;border:1px solid #e2e8f0;border-radius:8px;padding:.5rem .7rem;font-size:.875rem;line-height:1.4;outline:none;font-family:inherit;max-height:100px;overflow-y:auto;"></textarea>
                    <button type="submit" id="popupSendBtn" style="padding:.48rem 1rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:.875rem;cursor:pointer;font-weight:500;flex-shrink:0;">ส่ง</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endauth

{{-- ═══ คอมเมนต์ Section ═══ --}}
<div id="commentSection" class="card mt-2">
    <div class="card-header flex items-center gap-2">
        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        ความคิดเห็น ({{ $comments->count() }})
    </div>
    <div class="card-body">
        {{-- ฟอร์มเพิ่มคอมเมนต์ --}}
        @auth
        <form method="POST" action="{{ route('jobs.comment', $job->id) }}" class="mb-4">
            @csrf
            <textarea name="body" rows="2" class="form-control" placeholder="แสดงความคิดเห็น..." required maxlength="1000"></textarea>
            <button type="submit" class="btn btn-primary btn-sm mt-2">ส่งความคิดเห็น</button>
        </form>
        @endauth

        {{-- รายการคอมเมนต์ --}}
        @forelse($comments as $comment)
        <div class="job-comment" style="border-bottom:1px solid #f1f5f9;padding:.75rem 0;">
            <div class="flex items-center gap-2 mb-1">
                <span class="font-semi text-sm">{{ $comment->user->full_name ?? 'ผู้ใช้' }}</span>
                @if($comment->user->isStaffOrAdmin())
                    <span class="badge badge-purple" style="font-size:.6rem;">ผู้ดูแล</span>
                @endif
                <span class="text-xs text-muted">{{ $comment->created_at->diffForHumans() }}</span>
                @if(auth()->check() && auth()->id() === $comment->user_id)
                    <form method="POST" action="{{ route('jobs.comment.delete', $comment->id) }}" style="margin-left:auto;">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-danger" style="background:none;border:none;cursor:pointer;" onclick="return confirm('ลบคอมเมนต์?')">ลบ</button>
                    </form>
                @endif
            </div>
            <p class="text-sm">{{ $comment->body }}</p>

            {{-- Replies --}}
            @foreach($comment->replies as $reply)
            <div style="margin-left:1.5rem;padding:.5rem 0;border-left:2px solid #e2e8f0;padding-left:.75rem;margin-top:.5rem;">
                <div class="flex items-center gap-2 mb-1">
                    <span class="font-semi text-sm">{{ $reply->user->full_name ?? 'ผู้ใช้' }}</span>
                    @if($reply->user->isStaffOrAdmin())
                        <span class="badge badge-purple" style="font-size:.6rem;">ผู้ดูแล</span>
                    @endif
                    <span class="text-xs text-muted">{{ $reply->created_at->diffForHumans() }}</span>
                </div>
                <p class="text-sm">{{ $reply->body }}</p>
            </div>
            @endforeach

            {{-- ปุ่ม Reply --}}
            @auth
            <button class="text-xs text-primary mt-1" style="background:none;border:none;cursor:pointer;" onclick="toggleReply({{ $comment->id }})">↩ ตอบกลับ</button>
            <form method="POST" action="{{ route('jobs.comment', $job->id) }}" id="replyForm{{ $comment->id }}" style="display:none;margin-top:.5rem;margin-left:1.5rem;">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <textarea name="body" rows="2" class="form-control" placeholder="ตอบกลับ..." required maxlength="1000" style="font-size:.85rem;"></textarea>
                <button type="submit" class="btn btn-primary btn-sm mt-1">ส่ง</button>
            </form>
            @endauth
        </div>
        @empty
            <p class="text-sm text-muted">ยังไม่มีความคิดเห็น</p>
        @endforelse
    </div>
</div>


@endsection

@section('scripts')
@auth
<script>
(function() {
    const JOB_ID   = {{ $job->id }};
    const USER_ID  = {{ auth()->id() }};
    const studentRoom = 'chat:student:' + USER_ID;
    const studentToken = '{{ \App\Services\SocketService::roomToken("chat:student:" . auth()->id()) }}';
    const threadRoom = 'chat:thread:' + JOB_ID + ':' + USER_ID;
    const threadToken = '{{ \App\Services\SocketService::roomToken("chat:thread:{$job->id}:" . auth()->id()) }}';
    const typingRoom = 'chat:admin:' + JOB_ID;
    const typingToken = '{{ \App\Services\SocketService::roomToken("chat:admin:{$job->id}") }}';
    const sendUrl  = '{{ route('chat.send', $job->id) }}';
    const readUrl  = '{{ route('chat.read', $job->id) }}';
    const msgsUrl  = '{{ route('chat.messages', $job->id) }}';
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content;

    const popup       = document.getElementById('chatPopup');
    const chatWindow  = document.getElementById('popupChatWindow');
    const chatForm    = document.getElementById('popupChatForm');
    const msgInput    = document.getElementById('popupMsgInput');
    const fileInput   = document.getElementById('popupFileInput');
    const attachPrev  = document.getElementById('popupAttachPreview');
    const sendBtn     = document.getElementById('popupSendBtn');
    const typingLabel = document.getElementById('popupTypingLabel');

    function loadHistory() {
        chatWindow.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.85rem;color:#94a3b8;">กำลังโหลด...</div>';
        fetch(msgsUrl, { headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                chatWindow.innerHTML = '';
                var msgs = data.messages || [];
                if (!Array.isArray(msgs)) msgs = Object.values(msgs);
                
                if (!msgs.length) {
                    chatWindow.innerHTML = '<p id="popupNoMsg" style="margin:auto;font-size:.85rem;color:#94a3b8;text-align:center;">ยังไม่มีข้อความ เริ่มสอบถามได้เลย</p>';
                    return;
                }
                msgs.forEach(function(m) { renderBubble(m, m.sender_id == USER_ID); });
                chatWindow.scrollTop = chatWindow.scrollHeight;
            })
            .catch(function() {
                chatWindow.innerHTML = '<p style="margin:auto;font-size:.85rem;color:#94a3b8;text-align:center;">โหลดข้อความไม่สำเร็จ</p>';
            });
    }

    window.openChatPopup = function() {
        popup.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        loadHistory();
        fetch(readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });
    };
    window.closeChatPopup = function() {
        popup.style.display = 'none';
        document.body.style.overflow = '';
    };

    popup.addEventListener('click', function(e) {
        if (e.target === popup) closeChatPopup();
    });

    msgInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });

    fileInput.addEventListener('change', function() {
        attachPrev.innerHTML = '';
        if (!fileInput.files.length) { attachPrev.style.display = 'none'; return; }
        attachPrev.style.display = 'flex';
        Array.from(fileInput.files).forEach(function(f) {
            var chip = document.createElement('span');
            chip.style.cssText = 'padding:.2rem .5rem;background:#e0e7ff;border-radius:20px;font-size:.72rem;color:#4f46e5;';
            chip.textContent = f.name.length > 22 ? f.name.slice(0, 22) + '...' : f.name;
            attachPrev.appendChild(chip);
        });
    });

    function renderBubble(msg, mine) {
        var existing = document.getElementById('pcm-' + msg.id);
        if (existing) return;

        var noMsg = document.getElementById('popupNoMsg');
        if (noMsg) noMsg.remove();

        var isMine = mine || (msg.sender_id == USER_ID);
        var label = isMine ? 'คุณ' : (msg.sender_name || 'ผู้ดูแล');
        var photo = msg.sender_photo || null;

        var wrap = document.createElement('div');
        wrap.id = 'pcm-' + msg.id;
        wrap.style.cssText = 'display:flex;flex-direction:' + (isMine ? 'row-reverse' : 'row') + ';align-items:flex-end;gap:.35rem;';

        var avatarWrap = document.createElement('div');
        avatarWrap.style.cssText = 'position:relative;flex-shrink:0;';
        
        var avatar = document.createElement(photo ? 'img' : 'div');
        if (photo) {
            avatar.src = photo; avatar.alt = label;
            avatar.style.cssText = 'width:26px;height:26px;border-radius:50%;object-fit:cover;';
        } else {
            avatar.textContent = label.charAt(0).toUpperCase();
            avatar.style.cssText = 'width:26px;height:26px;border-radius:50%;background:' + (isMine ? '#4f46e5' : '#64748b') + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;';
        }
        avatarWrap.appendChild(avatar);
        wrap.appendChild(avatarWrap);

        var col = document.createElement('div');
        col.style.cssText = 'display:flex;flex-direction:column;align-items:' + (isMine ? 'flex-end' : 'flex-start') + ';max-width:78%;';

        var nameEl = document.createElement('span');
        nameEl.style.cssText = 'font-size:.68rem;color:#94a3b8;margin-bottom:.15rem;';
        nameEl.textContent = label;
        col.appendChild(nameEl);

        var bubble = document.createElement('div');
        bubble.style.cssText = 'padding:.5rem .8rem;border-radius:' +
            (isMine ? '14px 4px 14px 14px' : '4px 14px 14px 14px') +
            ';background:' + (isMine ? '#4f46e5' : '#fff') +
            ';color:' + (isMine ? '#fff' : '#1e293b') +
            ';font-size:.85rem;box-shadow:0 1px 3px rgba(0,0,0,.08);word-break:break-word;';

        if (msg.message) {
            var p = document.createElement('p');
            p.style.margin = '0';
            p.textContent = msg.message;
            bubble.appendChild(p);
        }
        if (msg.attachments && msg.attachments.length) {
            msg.attachments.forEach(function(att) {
                var isImg = att.mime_type && att.mime_type.startsWith('image/');
                if (isImg) {
                    var img = document.createElement('img');
                    img.src = att.url; img.alt = att.original_name;
                    img.style.cssText = 'max-width:100%;border-radius:6px;margin-top:.3rem;display:block;cursor:pointer;';
                    img.onclick = function() { window.open(att.url, '_blank'); };
                    bubble.appendChild(img);
                } else {
                    var a = document.createElement('a');
                    a.href = att.url; a.target = '_blank'; a.download = att.original_name;
                    a.style.cssText = 'display:flex;align-items:center;gap:.35rem;margin-top:.3rem;color:' + (isMine ? '#c7d2fe' : '#4f46e5') + ';font-size:.78rem;text-decoration:none;';
                    a.innerHTML = '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ' + att.original_name;
                    bubble.appendChild(a);
                }
            });
        }
        col.appendChild(bubble);

        if (isMine && msg.read_at) {
            var st = document.createElement('span');
            st.style.cssText = 'font-size:.6rem;color:#6366f1;margin-top:.08rem;';
            var dt = new Date(msg.read_at);
            st.textContent = '✓✓ เห็นเมื่อ ' + dt.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});
            col.appendChild(st);
        } else {
            var tm = document.createElement('span');
            tm.style.cssText = 'font-size:.6rem;color:#94a3b8;margin-top:.08rem;';
            tm.textContent = msg.created_at ? new Date(msg.created_at).toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'}) : '';
            col.appendChild(tm);
        }

        wrap.appendChild(col);
        chatWindow.appendChild(wrap);
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        var text = msgInput.value.trim();
        if (!text && !fileInput.files.length) return;
        sendBtn.disabled = true; sendBtn.textContent = '...';
        var fd = new FormData(chatForm);
        try {
            var res = await fetch(sendUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd
            });
            var data = await res.json();
            if (data.success) {
                renderBubble(data.message, true);
                msgInput.value = ''; msgInput.style.height = 'auto';
                fileInput.value = '';
                attachPrev.innerHTML = ''; attachPrev.style.display = 'none';
            }
        } finally {
            sendBtn.disabled = false; sendBtn.textContent = 'ส่ง';
        }
    });

    if (typeof io !== 'undefined') {
        var socket = io('{{ config('socket.public_url') }}');
        socket.emit('join', { room: studentRoom, token: studentToken });
        socket.emit('join', { room: threadRoom, token: threadToken });

        socket.on('chat:message', function(msg) {
            if (msg.sender_id == USER_ID) return;
            if (msg.job_id != JOB_ID) return;
            if (!document.getElementById('pcm-' + msg.id)) {
                renderBubble(msg, false);

        msgInput.addEventListener('input', function() {
            socket.emit('typing', {
                toRoom: typingRoom,
                token: typingToken,
                userId: USER_ID,
                name: '{{ addslashes(auth()->user()->full_name ?? '') }}'
            });
        });

        function togglePopupAdminOnlineDots(isOnline) {
            var show = isOnline ? 'inline-block' : 'none';
            document.querySelectorAll('.popup-admin-online-dot').forEach(function(el) {
                el.style.display = show;
            });
        }

        if (window.Echo) {
            var adminId = {{ $job->creator->id ?? 0 }};
            window.Echo.join('online')
                .here(function(users) {
                    var isOnline = users.some(function(u) { return u.id == adminId || u.role === 'admin' || u.role === 'staff'; });
                    togglePopupAdminOnlineDots(isOnline);
                })
                .joining(function(user) {
                    if (user.id == adminId || user.role === 'admin' || user.role === 'staff') togglePopupAdminOnlineDots(true);
                })
                .leaving(function(user) {
                    // It's hard to know if they were the LAST admin. We can just leave it or recalculate if needed.
                    // For simplicity, hide if this specific admin leaves
                    if (user.id == adminId) togglePopupAdminOnlineDots(false);
                });
        }
    }
})();
</script>
@endauth
@if($job->hasGeolocation())
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var jobMap, destMarker;
var destLat = parseFloat('{{ $job->latitude }}');
var destLng = parseFloat('{{ $job->longitude }}');

function initMap() {
    if (isNaN(destLat) || isNaN(destLng)) return;
    jobMap = L.map('jobDetailMap', { scrollWheelZoom: false }).setView([destLat, destLng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap', maxZoom: 19
    }).addTo(jobMap);

    destMarker = L.marker([destLat, destLng]).addTo(jobMap);
    destMarker.bindPopup(`
        <div style="text-align:center;">
            <b style="display:block;margin-bottom:5px;">{{ addslashes($job->location) }}</b>
            <a href="https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}" target="_blank" class="btn btn-primary btn-sm" style="padding:2px 8px;font-size:11px;text-decoration:none;">เปิดแอปแผนที่</a>
        </div>
    `).openPopup();
}


document.addEventListener('DOMContentLoaded', initMap);
</script>
@endif

<script>
// ฟังก์ชันสลับแสดงฟอร์มตอบกลับ (ถ้ามีจุดที่เรียกใช้)
function toggleReply(id) {
    var form = document.getElementById('replyForm' + id);
    if (!form) return;
    form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
    if (form.style.display === 'block') form.querySelector('textarea').focus();
}

document.addEventListener('DOMContentLoaded', function() {
});
</script>
@endsection