{{-- หน้าโปรไฟล์นักศึกษา: ข้อมูลส่วนตัว + สรุปชั่วโมงกิจกรรม + ประวัติล่าสุด --}}
@extends('layouts.app')
@section('title', 'โปรไฟล์ของฉัน')

@section('content')

{{-- 1. Hero Card: ข้อมูลส่วนตัวและสถิติภาพรวม --}}
<style>
@keyframes swap-avatar-badge {
    0%, 40% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
    48%, 52% {
        opacity: 0;
        transform: scale(0.4) rotate(90deg);
    }
    60%, 100% {
        opacity: 0;
        transform: scale(0.4) rotate(-90deg);
    }
}
.swap-badge-1 {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: swap-avatar-badge 5s ease-in-out infinite;
}
.swap-badge-2 {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: swap-avatar-badge 5s ease-in-out infinite 2.5s;
    opacity: 0;
}
</style>
<div style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #f1f5f9;">
    <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; justify-content: space-between;">
        
        {{-- Profile Info (Left) --}}
        <div style="display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 250px;">
            <div style="position: relative; flex-shrink: 0;">
                <label for="photoInput" style="cursor: pointer; display: block;">
                    @if($user->profile_photo)
                        <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="profile"
                            style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #f8fafc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    @else
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 3px solid #f8fafc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <svg width="40" height="40" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                    @endif
                    <div style="position: absolute; bottom: 0; right: 0; width: 28px; height: 28px; background: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; overflow: hidden;">
                        @if($user->line_user_id)
                            <div class="swap-badge-1" title="ผูกบัญชี LINE สำเร็จแล้ว">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#06c755">
                                    <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
                                </svg>
                            </div>
                            <div class="swap-badge-2" title="อัปโหลดรูปโปรไฟล์">
                                <svg width="15" height="15" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                            </div>
                        @else
                            <svg width="15" height="15" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                        @endif
                    </div>
                </label>
                <form id="photoForm" method="POST" action="{{ route('profile.photo.upload') }}" enctype="multipart/form-data" style="display:none;">
                    @csrf
                    <input type="hidden" name="face_descriptor" id="faceDescriptorInput">
                    <input type="file" id="photoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp"
                        onchange="handleProfilePhotoSelect(event)">
                </form>
                {{-- Loading overlay for profile picture --}}
                <div id="photoLoadingOverlay" style="position: absolute; inset: 0; background: rgba(255,255,255,0.8); border-radius: 50%; display: none; align-items: center; justify-content: center; z-index: 10;">
                    <div style="width:24px;height:24px;border:3px solid #e2e8f0;border-top-color:#ea580c;border-radius:50%;animation:spin 1s linear infinite;"></div>
                </div>
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 8px; margin: 0 0 0.25rem 0;">
                    <h1 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.2;">{{ $user->full_name }}</h1>
                    @if($user->line_user_id)
                        <span title="ผูกบัญชี LINE เรียบร้อยแล้ว" style="display: inline-flex; align-items: center; justify-content: center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#06c755">
                                <path d="M22.5 12.5c0-1.58-.8-2.97-2-3.79.44-1.61.04-3.35-1.11-4.5-1.15-1.15-2.89-1.55-4.5-1.11-.82-1.2-2.21-2-3.79-2s-2.97.8-3.79 2c-1.61-.44-3.35-.04-4.5 1.11-1.15 1.15-1.55 2.89-1.11 4.5-1.2.82-2 2.21-2 3.79s.8 2.97 2 3.79c-.44 1.61-.04 3.35 1.11 4.5 1.15 1.15 2.89 1.55 4.5 1.11.82 1.2 2.21 2 3.79 2s2.97-.8 3.79-2c1.61.44 3.35.04 4.5-1.11 1.15-1.15 1.55-2.89 1.11-4.5 1.2-.82 2-2.21 2-3.79zm-12.21 4.21l-3.5-3.5 1.41-1.41 2.09 2.09 5.68-5.68 1.41 1.41-7.09 7.09z"/>
                            </svg>
                        </span>
                    @endif
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin: 0 0 0.4rem 0;">
                    <p style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin: 0;">{{ $user->english_name ?? '(กำลังประมวลผลชื่อภาษาอังกฤษ...)' }}</p>
                    <button onclick="editEnglishName('{{ addslashes($user->english_name) }}')" style="background: none; border: none; color: #ea580c; cursor: pointer; padding: 0; display: flex; align-items: center;" title="แก้ไขชื่อภาษาอังกฤษ">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>
                </div>
                <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 0.4rem 0; font-weight: 500;">{{ $user->student_id }}</p>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span style="background: #ffedd5; color: #c2410c; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">
                        {{ $user->program ?? 'นักศึกษา' }}
                    </span>
                    @if($user->profile_photo)
                    <form method="POST" action="{{ route('profile.photo.destroy') }}" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="background: none; border: none; color: #ef4444; font-size: 0.75rem; cursor: pointer; text-decoration: underline; padding: 0;"
                            onclick="return confirm('ต้องการลบรูปโปรไฟล์?')">ลบรูป</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Stats (Right) --}}
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1.25rem; text-align: center; flex: 1; min-width: 100px;">
                <p style="font-size: 1.5rem; font-weight: 700; color: #ea580c; line-height: 1; margin: 0;">{{ number_format($totalHours, 1) }}</p>
                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; font-weight: 500;">ชั่วโมงรวม</p>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1.25rem; text-align: center; flex: 1; min-width: 100px;">
                <p style="font-size: 1.5rem; font-weight: 700; color: #1e293b; line-height: 1; margin: 0;">{{ $totalActivities }}</p>
                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; font-weight: 500;">กิจกรรม</p>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.75rem 1.25rem; text-align: center; flex: 1; min-width: 100px;">
                <p style="font-size: 1.5rem; font-weight: 700; color: #1e293b; line-height: 1; margin: 0;">{{ number_format($totalRequired, 0) }}</p>
                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; font-weight: 500;">เป้าหมาย (ชม.)</p>
            </div>
        </div>
    </div>
</div>

{{-- QR Code Button (Changed to Modal Popup) --}}
<div style="margin-bottom: 1.5rem;">
    <button onclick="openCardModal()" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.875rem; background: #ffffff; color: #ea580c; border: 1px solid #fed7aa; border-radius: 10px; font-weight: 600; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s; cursor: pointer;">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
        บัตรประจำตัวนักศึกษา
    </button>
</div>

{{-- 2. ข้อมูลส่วนตัว --}}
<div class="card mb-4" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;">
    <div class="card-body" style="padding: 1.5rem;">
        <h2 class="font-bold mb-4" style="font-size: 1.1rem; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            ข้อมูลประวัตินักศึกษา
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.25rem;">
            <div style="display: flex; gap: 0.75rem;">
                <div style="color: #94a3b8; margin-top: 0.1rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-muted" style="margin-bottom: 0.15rem; font-weight: 500;">คณะ</p>
                    <p class="text-sm" style="font-weight: 600; color: #334155;">{{ $user->faculty ?? '-' }}</p>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <div style="color: #94a3b8; margin-top: 0.1rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-muted" style="margin-bottom: 0.15rem; font-weight: 500;">สาขา</p>
                    <p class="text-sm" style="font-weight: 600; color: #334155;">{{ $user->department ?? '-' }}</p>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <div style="color: #94a3b8; margin-top: 0.1rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                    <p class="text-xs text-muted" style="margin-bottom: 0.15rem; font-weight: 500;">ชั้นปี</p>
                    <p class="text-sm" style="font-weight: 600; color: #334155;">{{ $user->year ? 'ปี ' . $user->year : '-' }}</p>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <div style="color: #94a3b8; margin-top: 0.1rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-muted" style="margin-bottom: 0.15rem; font-weight: 500;">อีเมล</p>
                    <p class="text-sm" style="font-weight: 600; color: #334155;">{{ $user->email ?? '-' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 3. ชั่วโมงแยกตามหมวดหมู่ --}}
<div class="card mb-4" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;">
    <div class="card-body" style="padding: 1.5rem;">
        <h2 class="font-bold mb-4" style="font-size: 1.1rem; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            ความคืบหน้าตามหมวดหมู่
        </h2>
        
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            @foreach($byCategory as $cat)
            @php 
                $p = $cat['required'] > 0 ? min(100, ($cat['hours']/$cat['required'])*100) : 0; 
                $isCompleted = $p >= 100;
            @endphp
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm" style="font-weight: 600; color: #334155;">{{ $cat['name'] }}</span>
                    <span class="text-sm" style="font-weight: 500; color: {{ $isCompleted ? '#16a34a' : '#64748b' }};">
                        {{ number_format($cat['hours'], 1) }} / {{ number_format($cat['required'], 0) }} ชม.
                    </span>
                </div>
                <div style="height: 8px; background: #f1f5f9; border-radius: 999px; overflow: hidden; position: relative;">
                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: {{ $p }}%; background: {{ $isCompleted ? '#10b981' : '#ea580c' }}; border-radius: 999px; transition: width 0.5s ease;"></div>
                </div>
                @if(!$isCompleted && $cat['required'] > 0)
                <p class="text-xs" style="color: #94a3b8; margin-top: 0.3rem; text-align: right;">ขาดอีก {{ number_format($cat['required'] - $cat['hours'], 1) }} ชม.</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- 4. ประวัติกิจกรรมล่าสุด --}}
<div class="card mb-4" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;">
    <div class="card-body" style="padding: 1.5rem;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold" style="font-size: 1.1rem; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="20" height="20" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ประวัติกิจกรรมล่าสุด
            </h2>
            <a href="{{ route('student.history') }}" class="text-sm" style="color: #ea580c; font-weight: 600; text-decoration: none;">ดูทั้งหมด →</a>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @forelse($recentAttendances as $att)
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #f1f5f9; border-radius: 10px; background: #fafafa;">
                <div style="flex: 1; min-width: 0; padding-right: 1rem;">
                    <p style="font-size: 0.95rem; font-weight: 600; color: #1e293b; margin: 0 0 0.2rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $att->activity->title }}">{{ Str::limit($att->activity->title, 45, '...') }}</p>
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.75rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>{{ $att->checked_in_at?->format('d/m/Y H:i') ?? '-' }}</span>
                        @if($att->activity->category)
                            <span style="display: inline-block; width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                            <span>{{ $att->activity->category->name }}</span>
                        @endif
                    </div>
                </div>
                <div style="text-align: right; flex-shrink: 0;">
                    @if($att->status === 'approved')
                        <span style="display: inline-flex; align-items: center; justify-content: center; background: #ecfdf5; color: #059669; font-size: 0.7rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 999px; border: 1px solid #a7f3d0;">
                            +{{ $att->activity->activity_hours }} ชม.
                        </span>
                    @elseif($att->status === 'pending')
                        <span style="display: inline-flex; align-items: center; justify-content: center; background: #fffbeb; color: #d97706; font-size: 0.7rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 999px; border: 1px solid #fde68a;">
                            รออนุมัติ
                        </span>
                    @else
                        <span style="display: inline-flex; align-items: center; justify-content: center; background: #fef2f2; color: #dc2626; font-size: 0.7rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 999px; border: 1px solid #fecaca;">
                            ปฏิเสธ
                        </span>
                    @endif
                </div>
            </div>
            @empty
            <div style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8; background: #f8fafc; border-radius: 10px; border: 1px dashed #cbd5e1;">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 0.75rem auto;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p style="font-size: 0.9rem;">ยังไม่มีประวัติการเข้าร่วมกิจกรรม</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- 5. การแจ้งเตือนผ่าน LINE --}}
<div class="card mb-4" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;">
    <div class="card-body" style="padding: 1.5rem;">
        <h2 class="font-bold mb-4" style="font-size: 1.1rem; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
            <img src="{{ asset('images/line-logo.png') }}" alt="LINE" style="width: 22px; height: 22px; object-fit: contain; flex-shrink: 0;">
            การแจ้งเตือนผ่าน LINE
        </h2>

        @if(auth()->user()->line_user_id)
            {{-- ═══ สถานะ: ผูกแล้ว ═══ --}}
            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; margin-bottom: 1rem;">
                <div style="width: 36px; height: 36px; background: transparent; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <img src="{{ asset('images/line-logo.png') }}" alt="LINE" style="width: 32px; height: 32px; object-fit: contain; flex-shrink: 0;">
                </div>
                <div style="flex: 1; min-width: 0;">
                    <p style="font-weight: 600; font-size: 0.9rem; color: #15803d; margin: 0;">เชื่อมต่อ LINE สำเร็จ</p>
                    <p style="font-size: 0.75rem; color: #16a34a; margin: 0.1rem 0 0 0;">{{ auth()->user()->line_display_name ?? 'LINE Account' }}</p>
                </div>
                <svg width="18" height="18" fill="none" stroke="#16a34a" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" fill="none" stroke="#64748b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #334155;">รับการแจ้งเตือน</span>
                </div>
                <form method="POST" action="{{ route('line.toggle-notify') }}" style="margin: 0;">
                    @csrf
                    <button type="submit"
                        style="position: relative; width: 44px; height: 24px; border-radius: 12px; border: none; cursor: pointer; transition: background 0.2s; background: {{ auth()->user()->line_notify_enabled ? '#06c755' : '#cbd5e1' }};"
                        title="{{ auth()->user()->line_notify_enabled ? 'คลิกเพื่อปิด' : 'คลิกเพื่อเปิด' }}">
                        <span style="position: absolute; top: 3px; width: 18px; height: 18px; background: #fff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: left 0.2s; left: {{ auth()->user()->line_notify_enabled ? '23px' : '3px' }};"></span>
                    </button>
                </form>
            </div>

            @if(!auth()->user()->line_notify_enabled)
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; padding: 0.5rem 0.75rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px;">
                <svg width="14" height="14" fill="none" stroke="#d97706" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p style="font-size: 0.75rem; color: #b45309; margin: 0;">การแจ้งเตือนปิดอยู่ — คุณจะไม่ได้รับข่าวสารทาง LINE</p>
            </div>
            @endif

            <form method="POST" action="{{ route('line.unlink') }}" onsubmit="return confirm('ต้องการยกเลิกการผูกบัญชี LINE ใช่หรือไม่?')">
                @csrf
                <button type="submit" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.6rem; border: 1px solid #f1f5f9; border-radius: 10px; background: #fafafa; color: #94a3b8; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#ef4444'; this.style.borderColor='#fecaca'; this.style.background='#fef2f2';" onmouseout="this.style.color='#94a3b8'; this.style.borderColor='#f1f5f9'; this.style.background='#fafafa';">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    ยกเลิกการเชื่อมต่อ LINE
                </button>
            </form>

        @else
            {{-- ═══ สถานะ: ยังไม่ได้ผูก ═══ --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.5rem; margin-bottom: 1.25rem;">
                @foreach([
                    ['icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'text'=>'กิจกรรมใหม่'],
                    ['icon'=>'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'text'=>'ประกาศงาน'],
                    ['icon'=>'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z', 'text'=>'ข่าวประกาศ'],
                    ['icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'text'=>'Reminder 1 วันก่อน'],
                ] as $item)
                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px;">
                    <svg width="14" height="14" fill="none" stroke="#06c755" viewBox="0 0 24 24" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $item['icon'] }}"/></svg>
                    <span style="font-size: 0.75rem; font-weight: 500; color: #475569;">{{ $item['text'] }}</span>
                </div>
                @endforeach
            </div>

            <a href="{{ route('line.redirect') }}" style="display: flex; align-items: center; justify-content: center; gap: 0.55rem; width: 100%; padding: 0.8rem; background: #06c755; color: #fff; border-radius: 10px; font-weight: 600; font-size: 0.95rem; text-decoration: none; box-shadow: 0 4px 6px rgba(6, 199, 85, 0.2); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 8px rgba(6, 199, 85, 0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(6, 199, 85, 0.2)';">
                <img src="{{ asset('images/line-logo.png') }}" alt="LINE" style="width: 24px; height: 24px; object-fit: contain; flex-shrink: 0; border-radius: 4px;">
                เชื่อมต่อบัญชี LINE
            </a>
            <p style="text-align: center; font-size: 0.75rem; color: #94a3b8; margin: 0.75rem 0 0 0;">รับข้อมูลข่าวสารรวดเร็วผ่าน LINE Official Account</p>
        @endif
    </div>
</div>

{{-- 6. ปุ่มดาวน์โหลด PDF --}}
<div style="margin: 2rem 0; text-align: center;">
    <a href="{{ route('student.summary.pdf') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.8rem 1.5rem; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 999px; font-weight: 600; font-size: 0.9rem; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseout="this.style.background='#ffffff'; this.style.color='#475569';">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        ดาวน์โหลดใบแสดงผลกิจกรรม (PDF)
    </a>
</div>

@endsection

@push('scripts')
<!-- ID Card Modal -->
<div id="idCardModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);">
    <div id="idCardContainer" style="--theme-primary: #ea580c; --theme-secondary: #27272a; --theme-bg-image: none; position: relative; animation: slideUp 0.3s ease-out; display: flex; flex-direction: column; align-items: center;">
        <!-- Close button -->
        <button onclick="closeCardModal()" style="position: absolute; top: -15px; right: -15px; width: 32px; height: 32px; background: #fff; border: none; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.25); cursor: pointer; z-index: 30; display: flex; align-items: center; justify-content: center; color: #333; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <!-- 3D Perspective Stage Wrapper -->
        <div class="student-card-stage">
            <!-- ID Card Structure (Exact Original PKRU Theme with 3D Depth & Gloss) -->
            <div id="studentIdCard" class="student-id-card">
                
                <!-- Dynamic Specular Glare Reflection Layer -->
                <div class="student-card-glare"></div>

                <!-- Holographic Rainbow Foil Sheen Layer -->
                <div class="student-card-hologram"></div>

                <!-- Diagonal Gloss Sweep -->
                <div class="student-card-gloss-sweep"></div>

                <!-- Top Diagonal (confined to header so it never touches photo) -->
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 75px; background: linear-gradient(105deg, var(--theme-primary) 38%, #fff 38.5%); z-index: 1;"></div>
                
                <!-- Content Wrapper -->
                <div style="position: relative; z-index: 2; display: flex; flex-direction: column; height: 100%;">
                    
                    <!-- Header (Logo & Univ Name) -->
                    <div style="display: flex; align-items: center; padding: 15px 15px 0 15px; gap: 8px;">
                        <div style="width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.15); background: #fff;">
                            <img src="{{ asset('images/pkru-logo.png') }}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;" alt="PKRU Logo">
                        </div>
                        <div style="line-height: 1.2; margin-top: -5px; z-index: 2;">
                            <div style="font-size: 16px; font-weight: 700; color: #1e293b; letter-spacing: -0.2px;">มหาวิทยาลัยราชภัฏภูเก็ต</div>
                            <div style="font-size: 8.5px; font-weight: 600; color: #334155; letter-spacing: 0.3px;">PHUKET RAJABHAT UNIVERSITY</div>
                        </div>
                    </div>

                    <!-- Photo (elevated to z-index: 30 so glare/shadow/hologram never overlays the photo) -->
                    <div style="width: 100%; display: flex; justify-content: center; margin-top: 15px; position: relative; z-index: 30;">
                        <div style="background: #ffffff; padding: 2px; border-radius: 4px; box-shadow: 0 3px 8px rgba(0,0,0,0.15); display: inline-block;">
                            @if($user->profile_photo)
                                <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="profile" style="width: 125px; height: 160px; object-fit: cover; border-radius: 2px; display: block;">
                            @else
                                <div style="width: 125px; height: 160px; background: #ea580c; display: flex; align-items: center; justify-content: center; border-radius: 2px; color: #fff;">
                                    <svg width="50" height="50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Details -->
                    <div style="padding: 15px 25px; z-index: 2; position: relative;">
                        <div style="font-size: 11px; color: #1e293b; font-weight: 700; margin-bottom: 0px;">รหัสนักศึกษา :</div>
                        <div style="font-size: 24px; font-weight: 700; letter-spacing: 1px; color: #0f172a; margin-bottom: 8px; line-height: 1;">{{ $user->student_id }}</div>
                        
                        <div style="font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.1; letter-spacing: -0.3px;">{{ $user->full_name }}</div>
                        @if($user->english_name)
                            <div style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 6px;">{{ strtoupper($user->english_name) }}</div>
                        @else
                            <div style="font-size: 12px; font-weight: 600; color: #94a3b8; margin-bottom: 6px; font-style: italic;">(No English Name)</div>
                        @endif
                        
                        <!-- Thin black divider -->
                        <div style="width: 100%; height: 1px; background: #64748b; margin: 8px 0; opacity: 0.5;"></div>
                        
                        <div style="font-size: 14px; font-weight: 700; color: #1e293b;">สาขา{{ $user->department ?? 'วิทยาการคอมพิวเตอร์' }}</div>
                    </div>

                    <div style="flex: 1;"></div>
                </div>

                <!-- Bottom slanted area -->
                <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 120px; overflow: visible; z-index: 1;">
                    <div style="position: absolute; top: 35px; left: -20px; right: -20px; bottom: -20px; background: var(--theme-secondary); transform: rotate(-7deg); border-top: 14px solid var(--theme-primary); box-shadow: 0 -2px 5px rgba(0,0,0,0.08);"></div>
                    
                    <!-- Bottom content overlay -->
                    <div style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 15px 20px; display: flex; justify-content: space-between; align-items: flex-end; z-index: 3;">
                        
                        <!-- Left: text -->
                        <div style="font-size: 11px; color: #a1a1aa; font-weight: 500; margin-bottom: 2px;">บัตรประจำตัวนักศึกษา</div>
                        
                        <!-- Center: Chip (Enhanced Realistic Gold EMV Smart Chip) -->
                        <div style="position: absolute; left: 50%; bottom: 60px; transform: translateX(-50%) rotate(0deg); z-index: 10;">
                            <div style="width: 42px; height: 34px; background: linear-gradient(135deg, #fef08a 0%, #fde047 25%, #d97706 60%, #fef08a 80%, #b45309 100%); border-radius: 6px; position: relative; overflow: hidden; border: 1px solid #b45309; box-shadow: 0 4px 10px rgba(0,0,0,0.35), inset 0 1px 2px rgba(255,255,255,0.95), inset 0 -1px 2px rgba(0,0,0,0.4);">
                                <svg width="42" height="34" viewBox="0 0 44 34" fill="none" style="display: block; width: 100%; height: 100%;">
                                    <line x1="0" y1="17" x2="44" y2="17" stroke="#b45309" stroke-width="1.2" stroke-opacity="0.85"/>
                                    <line x1="15" y1="0" x2="15" y2="34" stroke="#b45309" stroke-width="1.2" stroke-opacity="0.85"/>
                                    <line x1="29" y1="0" x2="29" y2="34" stroke="#b45309" stroke-width="1.2" stroke-opacity="0.85"/>
                                    <rect x="14" y="8.5" width="16" height="17" rx="3" fill="#fef08a" fill-opacity="0.45" stroke="#b45309" stroke-width="1.2" stroke-opacity="0.9"/>
                                    <path d="M0 13C2.5 13 3.5 15 3.5 17C3.5 19 2.5 21 0 21" fill="#b45309" fill-opacity="0.35"/>
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Right: Contactless & VISA -->
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#d4d4d8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transform: rotate(90deg); margin-right: 8px; margin-bottom: 4px; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4));"><path d="M5 12.55a11 11 0 0114.08 0M1.42 9a16 16 0 0121.16 0M8.53 16.11a6 6 0 016.95 0M12 20h.01"/></svg>
                            <div style="font-size: 28px; font-weight: 900; font-style: italic; color: #fff; letter-spacing: 1px; line-height: 1; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5));">VISA</div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <!-- Theme Selector -->
        <div style="margin-top: 20px; display: flex; gap: 12px; justify-content: center; z-index: 10; flex-wrap: wrap; max-width: 320px;">
            <button onclick="setCardTheme('#ea580c', '#27272a')" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #ea580c 50%, #27272a 50%); border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="PKRU Default"></button>
            <button onclick="setCardTheme('#0ea5e9', '#0f172a')" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9 50%, #0f172a 50%); border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Ocean Blue"></button>
            <button onclick="setCardTheme('#10b981', '#064e3b')" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #10b981 50%, #064e3b 50%); border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Emerald Green"></button>
            <button onclick="setCardTheme('#a855f7', '#3b0764')" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #a855f7 50%, #3b0764 50%); border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Amethyst Purple"></button>
            <button onclick="setCardTheme('#e11d48', '#4c0519')" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #e11d48 50%, #4c0519 50%); border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Crimson Red"></button>
        </div>
    </div>
</div>

<style>
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* 3D Stage & Interactive Perspective */
.student-card-stage {
    perspective: 1200px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px;
}

.student-id-card {
    width: 320px;
    height: 504px;
    background-color: #ffffff;
    background-image: var(--theme-bg-image);
    background-size: cover;
    background-position: center;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    font-family: 'Kanit', sans-serif;
    user-select: none;
    transform-style: preserve-3d;
    will-change: transform;
    transition: transform 0.12s cubic-bezier(0.2, 0, 0.2, 1), box-shadow 0.25s ease;
    box-shadow: 
        0 28px 60px -12px rgba(0, 0, 0, 0.45),
        0 14px 28px -8px rgba(0, 0, 0, 0.25),
        inset 0 1px 2px rgba(255, 255, 255, 0.95),
        inset 0 -1px 2px rgba(0, 0, 0, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.7);
    outline: 1px solid rgba(0, 0, 0, 0.08);
}

/* Dynamic Specular Glass Glare Reflection */
.student-card-glare {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 25;
    border-radius: 16px;
    mix-blend-mode: overlay;
    opacity: 0.9;
    background: radial-gradient(circle 350px at var(--mouse-x, 50%) var(--mouse-y, 30%), rgba(255,255,255,0.85) 0%, rgba(255,255,255,0.3) 35%, rgba(255,255,255,0) 70%);
    transition: opacity 0.3s ease;
}

/* Holographic Rainbow Foil Sheen */
.student-card-hologram {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 24;
    border-radius: 16px;
    mix-blend-mode: color-dodge;
    opacity: 0.5;
    background: linear-gradient(115deg, transparent 0%, rgba(255, 0, 128, 0.14) 16%, rgba(255, 215, 0, 0.18) 32%, rgba(0, 255, 128, 0.16) 48%, rgba(0, 195, 255, 0.18) 64%, rgba(180, 0, 255, 0.14) 80%, transparent 100%);
    background-size: 220% 220%;
    background-position: var(--holo-x, 50%) var(--holo-y, 50%);
}

/* Diagonal Specular Gloss Sweeper */
.student-card-gloss-sweep {
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    pointer-events: none;
    z-index: 23;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(255,255,255,0.42) 0%, rgba(255,255,255,0.12) 20%, transparent 45%, rgba(255,255,255,0.08) 70%, transparent 100%);
    transform: rotate(-12deg);
}

/* Micro Security Pattern Overlay */
.student-card-security-pattern {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 2;
    opacity: 0.08;
    background-image: radial-gradient(rgba(255,255,255,0.7) 1px, transparent 1px);
    background-size: 8px 8px;
}


/* Dust Disintegration Effect */
@keyframes dustDisintegrate {
    0% {
        opacity: 1;
        filter: blur(0px) grayscale(0);
        transform: scale(1) translateY(0);
    }
    30% {
        opacity: 0.8;
        filter: blur(3px) sepia(0.3);
        transform: scale(1.02) translateY(-5px);
    }
    100% {
        opacity: 0;
        filter: blur(20px) contrast(150%);
        transform: scale(0.9) translateY(-40px) rotate(5deg);
    }
}

.dust-effect {
    animation: dustDisintegrate 0.75s cubic-bezier(0.55, 0.085, 0.68, 0.53) forwards !important;
    pointer-events: none;
}
</style>

<script>
    // Open Modal Logic
    function openCardModal() {
        const modal = document.getElementById('idCardModal');
        if (modal) {
            modal.style.display = 'flex';
            resetCardTilt();
        }
    }

    // Close Modal Logic with Dust Effect
    function closeCardModal() {
        const container = document.getElementById('idCardContainer');
        container.classList.add('dust-effect');
        setTimeout(() => {
            document.getElementById('idCardModal').style.display = 'none';
            container.classList.remove('dust-effect');
            resetCardTilt();
        }, 700);
    }

    // Close modal when clicking outside the card
    document.getElementById('idCardModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCardModal();
        }
    });

    // 3D Tilt & Specular Reflection Management
    const studentCard = document.getElementById('studentIdCard');

    function resetCardTilt() {
        if (!studentCard) return;
        studentCard.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
        studentCard.style.setProperty('--mouse-x', '50%');
        studentCard.style.setProperty('--mouse-y', '30%');
        studentCard.style.setProperty('--holo-x', '50%');
        studentCard.style.setProperty('--holo-y', '50%');
    }

    function handleCardMove(e) {
        if (!studentCard) return;
        const rect = studentCard.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        const x = (clientX - rect.left) / rect.width;
        const y = (clientY - rect.top) / rect.height;

        if (x < 0 || x > 1 || y < 0 || y > 1) return;

        const rotateX = ((y - 0.5) * -24).toFixed(2);
        const rotateY = ((x - 0.5) * 24).toFixed(2);

        studentCard.style.transform = `perspective(1200px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.025, 1.025, 1.025)`;
        studentCard.style.setProperty('--mouse-x', `${(x * 100).toFixed(1)}%`);
        studentCard.style.setProperty('--mouse-y', `${(y * 100).toFixed(1)}%`);
        studentCard.style.setProperty('--holo-x', `${(x * 200 - 50).toFixed(1)}%`);
        studentCard.style.setProperty('--holo-y', `${(y * 200 - 50).toFixed(1)}%`);
    }

    if (studentCard) {
        studentCard.addEventListener('mousemove', handleCardMove);
        studentCard.addEventListener('mouseleave', resetCardTilt);
        studentCard.addEventListener('touchmove', handleCardMove, { passive: true });
        studentCard.addEventListener('touchend', resetCardTilt);
    }

    // Theme Management
    function setCardTheme(primary, secondary, bgImage = 'none') {
        const container = document.getElementById('idCardContainer');
        container.style.setProperty('--theme-primary', primary);
        container.style.setProperty('--theme-secondary', secondary);
        container.style.setProperty('--theme-bg-image', bgImage);
        localStorage.setItem('idCardThemePrimary', primary);
        localStorage.setItem('idCardThemeSecondary', secondary);
        localStorage.setItem('idCardThemeBgImage', bgImage);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const p = localStorage.getItem('idCardThemePrimary');
        const s = localStorage.getItem('idCardThemeSecondary');
        const bg = localStorage.getItem('idCardThemeBgImage');
        if (p && s) {
            setCardTheme(p, s, bg || 'none');
        }
    });

    // Edit English Name
    function editEnglishName(currentName) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'แก้ไขชื่อภาษาอังกฤษ',
                text: 'หากระบบแปลชื่อภาษาอังกฤษของคุณไม่ถูกต้อง สามารถแก้ไขได้ที่นี่',
                input: 'text',
                inputValue: currentName || "",
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก',
                inputValidator: (value) => {
                    if (!value) {
                        return 'กรุณาระบุชื่อภาษาอังกฤษ!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('englishNameInput').value = result.value.trim();
                    document.getElementById('editEnglishNameForm').submit();
                }
            });
        } else {
            let newName = prompt("กรุณาระบุชื่อ-นามสกุลภาษาอังกฤษของคุณ:\n(หากระบบแปลผิด สามารถแก้ไขได้)", currentName || "");
            if (newName !== null && newName.trim() !== "") {
                document.getElementById('englishNameInput').value = newName.trim();
                document.getElementById('editEnglishNameForm').submit();
            }
        }
    }
</script>

<form id="editEnglishNameForm" action="{{ route('student.profile.english_name') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="english_name" id="englishNameInput">
</form>

@endpush

@push('scripts')
<script>
    function handleProfilePhotoSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        const overlay = document.getElementById('photoLoadingOverlay');
        overlay.style.display = 'flex';

        // ส่งฟอร์มให้ Server จัดการเรื่องการสกัด Vector 512-d ทันที
        document.getElementById('photoForm').submit();
    }
</script>
@endpush
