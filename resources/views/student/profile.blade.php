{{-- หน้าโปรไฟล์นักศึกษา: ข้อมูลส่วนตัว + สรุปชั่วโมงกิจกรรม + ประวัติล่าสุด --}}
@extends('layouts.app')
@section('title', 'โปรไฟล์ของฉัน')

@section('content')

{{-- 1. Hero Card: ข้อมูลส่วนตัวและสถิติภาพรวม --}}
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
                    <div style="position: absolute; bottom: 0; right: 0; width: 26px; height: 26px; background: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); border: 1px solid #e2e8f0;">
                        <svg width="14" height="14" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                    </div>
                </label>
                <form id="photoForm" method="POST" action="{{ route('profile.photo.upload') }}" enctype="multipart/form-data" style="display:none;">
                    @csrf
                    <input type="file" id="photoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp"
                        onchange="document.getElementById('photoForm').submit();">
                </form>
            </div>
            <div style="flex: 1; min-width: 0;">
                <h1 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 0.25rem 0; line-height: 1.2;">{{ $user->full_name }}</h1>
                <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 0.4rem 0; font-weight: 500;">{{ $user->student_id }}</p>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span style="background: #e0e7ff; color: #4338ca; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">
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
                <p style="font-size: 1.5rem; font-weight: 700; color: #4f46e5; line-height: 1; margin: 0;">{{ number_format($totalHours, 1) }}</p>
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
    <button onclick="document.getElementById('idCardModal').style.display='flex'" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.875rem; background: #ffffff; color: #4f46e5; border: 1px solid #c7d2fe; border-radius: 10px; font-weight: 600; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s; cursor: pointer;">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
        บัตรประจำตัวนักศึกษา
    </button>
</div>

{{-- 2. ข้อมูลส่วนตัว --}}
<div class="card mb-4" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;">
    <div class="card-body" style="padding: 1.5rem;">
        <h2 class="font-bold mb-4" style="font-size: 1.1rem; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
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
            <svg width="20" height="20" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
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
                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: {{ $p }}%; background: {{ $isCompleted ? '#10b981' : '#4f46e5' }}; border-radius: 999px; transition: width 0.5s ease;"></div>
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
                <svg width="20" height="20" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ประวัติกิจกรรมล่าสุด
            </h2>
            <a href="{{ route('student.history') }}" class="text-sm" style="color: #4f46e5; font-weight: 600; text-decoration: none;">ดูทั้งหมด →</a>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @forelse($recentAttendances as $att)
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #f1f5f9; border-radius: 10px; background: #fafafa;">
                <div style="flex: 1; min-width: 0; padding-right: 1rem;">
                    <p style="font-size: 0.95rem; font-weight: 600; color: #1e293b; margin: 0 0 0.2rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $att->activity->title }}</p>
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
            <svg width="20" height="20" fill="none" stroke="#06c755" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            การแจ้งเตือนผ่าน LINE
        </h2>

        @if(auth()->user()->line_user_id)
            {{-- ═══ สถานะ: ผูกแล้ว ═══ --}}
            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; margin-bottom: 1rem;">
                <div style="width: 36px; height: 36px; background: #06c755; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
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

            <a href="{{ route('line.redirect') }}" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.8rem; background: #06c755; color: #fff; border-radius: 10px; font-weight: 600; font-size: 0.95rem; text-decoration: none; box-shadow: 0 4px 6px rgba(6, 199, 85, 0.2); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 8px rgba(6, 199, 85, 0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(6, 199, 85, 0.2)';">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
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
<div id="idCardModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div style="position: relative; animation: slideUp 0.3s ease-out;">
        <!-- Close button -->
        <button onclick="document.getElementById('idCardModal').style.display='none'" style="position: absolute; top: -15px; right: -15px; width: 32px; height: 32px; background: #fff; border: none; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.2); cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; color: #333; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <!-- ID Card Structure -->
        <div style="width: 320px; height: 500px; background: #fff; border-radius: 12px; overflow: hidden; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.2); font-family: 'Kanit', sans-serif;">
            
            <!-- Top Orange Diagonal -->
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 130px; background: linear-gradient(105deg, #ea580c 38%, #fff 38.5%); z-index: 1;"></div>
            
            <!-- Content Wrapper -->
            <div style="position: relative; z-index: 2; display: flex; flex-direction: column; height: 100%;">
                
                <!-- Header (Logo & Univ Name) -->
                <div style="display: flex; align-items: center; padding: 15px 15px 0 15px; gap: 8px;">
                    <!-- Logo placeholder -->
                    <div style="width: 40px; height: 40px; background: #15803d; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: relative;">
                        <!-- PKRU generic logo style -->
                        <div style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid #fff; background: #ea580c;"></div>
                    </div>
                    <div style="line-height: 1.1; margin-top: -5px; z-index: 2;">
                        <div style="font-size: 15px; font-weight: 700; color: #1e293b; letter-spacing: -0.2px;">มหาวิทยาลัยราชภัฏภูเก็ต</div>
                        <div style="font-size: 9px; font-weight: 500; color: #475569; letter-spacing: 0.5px;">PHUKET RAJABHAT UNIVERSITY</div>
                    </div>
                </div>

                <!-- Photo -->
                <div style="text-align: center; margin-top: 15px; position: relative; z-index: 2;">
                    @if($user->profile_photo)
                        <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="profile" style="width: 120px; height: 150px; object-fit: cover; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    @else
                        <div style="width: 120px; height: 150px; background: #cbd5e1; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); color: #94a3b8; margin: 0 auto;">
                            <svg width="50" height="50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                    @endif
                </div>

                <!-- Details -->
                <div style="padding: 15px 25px; z-index: 2;">
                    <div style="font-size: 11px; color: #334155; font-weight: 600; margin-bottom: 2px;">รหัสนักศึกษา :</div>
                    <div style="font-size: 24px; font-weight: 700; letter-spacing: 1.5px; color: #0f172a; margin-bottom: 6px; line-height: 1;">{{ $user->student_id }}</div>
                    
                    <div style="font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.2;">{{ $user->full_name }}</div>
                    <div style="font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 8px;">{{ strtoupper(str_replace('นาย ', 'Mr. ', str_replace('นางสาว ', 'Ms. ', $user->full_name))) }}</div>
                    
                    <div style="width: 100%; height: 1px; background: #cbd5e1; margin: 8px 0;"></div>
                    
                    <div style="font-size: 15px; font-weight: 700; color: #1e293b;">สาขา{{ $user->department ?? 'วิทยาการคอมพิวเตอร์' }}</div>
                </div>

                <div style="flex: 1;"></div>
            </div>

            <!-- Bottom slanted area -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 120px; overflow: hidden; z-index: 1;">
                <div style="position: absolute; top: 30px; left: -20px; right: -20px; bottom: -20px; background: #27272a; transform: rotate(-8deg); border-top: 6px solid #ea580c; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);"></div>
                
                <!-- Bottom content overlay -->
                <div style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 15px 20px; display: flex; justify-content: space-between; align-items: flex-end; z-index: 3;">
                    
                    <!-- Left: text -->
                    <div style="font-size: 11px; color: #a1a1aa; font-weight: 500; margin-bottom: 2px;">บัตรประจำตัวนักศึกษา</div>
                    
                    <!-- Center: Chip -->
                    <div style="position: absolute; left: 50%; bottom: 25px; transform: translateX(-50%);">
                        <div style="width: 38px; height: 32px; background: #d4d4d8; border-radius: 5px; position: relative; overflow: hidden; border: 1px solid #71717a; box-shadow: inset 0 1px 2px rgba(255,255,255,0.5);">
                            <div style="position:absolute; top:50%; left:0; width:100%; height:1px; background:#a1a1aa;"></div>
                            <div style="position:absolute; top:0; left:30%; width:1px; height:100%; background:#a1a1aa;"></div>
                            <div style="position:absolute; top:0; left:70%; width:1px; height:100%; background:#a1a1aa;"></div>
                            <div style="position:absolute; top:30%; left:30%; right:30%; bottom:30%; border:1px solid #a1a1aa; border-radius:3px;"></div>
                        </div>
                    </div>
                    
                    <!-- Right: Contactless & VISA -->
                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#d4d4d8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transform: rotate(90deg); margin-right: 8px; margin-bottom: 2px;"><path d="M5 12.55a11 11 0 0114.08 0M1.42 9a16 16 0 0121.16 0M8.53 16.11a6 6 0 016.95 0M12 20h.01"/></svg>
                        <div style="font-size: 28px; font-weight: 900; font-style: italic; color: #fff; letter-spacing: 1px; line-height: 1;">VISA</div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
<style>
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
/* Close modal when clicking outside */
#idCardModal {
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
}
</style>
<script>
    // Close modal when clicking outside the card
    document.getElementById('idCardModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>
@endsection
