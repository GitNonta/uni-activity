{{-- หน้าโปรไฟล์นักศึกษา: ข้อมูลส่วนตัว + สรุปชั่วโมงกิจกรรม + ประวัติล่าสุด --}}
@extends('layouts.app')
@section('title', 'โปรไฟล์ของฉัน')

@section('content')
{{-- Hero Card: ข้อมูลส่วนตัว --}}
<div class="hero-card" style="margin-bottom:1.25rem;">
    <div class="flex items-center gap-4">
        {{-- รูปโปรไฟล์: กดเพื่ออัปโหลด --}}
        <div style="position:relative;flex-shrink:0;">
            <label for="photoInput" style="cursor:pointer;display:block;">
                @if($user->profile_photo)
                    <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="profile"
                        style="width:68px;height:68px;border-radius:50%;object-fit:cover;border:2.5px solid rgba(255,255,255,.5);">
                @else
                    <div style="width:68px;height:68px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;">
                        <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#fff;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                @endif
                <div style="position:absolute;bottom:-2px;right:-2px;width:22px;height:22px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(0,0,0,.2);">
                    <svg width="12" height="12" fill="none" stroke="#3b82f6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                </div>
            </label>
            <form id="photoForm" method="POST" action="{{ route('profile.photo.upload') }}" enctype="multipart/form-data" style="display:none;">
                @csrf
                <input type="file" id="photoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp"
                    onchange="document.getElementById('photoForm').submit();">
            </form>
        </div>
        <div style="flex:1;min-width:0;">
            <p class="hero-label" style="margin-bottom:.15rem;">โปรไฟล์ของฉัน</p>
            <h1 style="font-size:1.3rem;font-weight:700;color:#fff;line-height:1.3;margin:0;">{{ $user->full_name }}</h1>
            <p style="color:rgba(255,255,255,.8);font-size:.85rem;margin:.2rem 0 0;">{{ $user->student_id }}</p>
            @if($user->profile_photo)
            <form method="POST" action="{{ route('profile.photo.destroy') }}" style="display:inline;margin-top:.3rem;">
                @csrf @method('DELETE')
                <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.7);font-size:.7rem;cursor:pointer;text-decoration:underline;padding:0;"
                    onclick="return confirm('ต้องการลบรูปโปรไฟล์?')">ลบรูป</button>
            </form>
            @endif
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-top:1.25rem;">
        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:.6rem .75rem;text-align:center;">
            <p style="font-size:1.5rem;font-weight:700;color:#fff;line-height:1;">{{ number_format($totalHours, 1) }}</p>
            <p style="font-size:.7rem;color:rgba(255,255,255,.75);margin-top:.15rem;">ชั่วโมงรวม</p>
        </div>
        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:.6rem .75rem;text-align:center;">
            <p style="font-size:1.5rem;font-weight:700;color:#fff;line-height:1;">{{ $totalActivities }}</p>
            <p style="font-size:.7rem;color:rgba(255,255,255,.75);margin-top:.15rem;">กิจกรรมทั้งหมด</p>
        </div>
        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:.6rem .75rem;text-align:center;">
            <p style="font-size:1.5rem;font-weight:700;color:#fff;line-height:1;">{{ number_format($totalRequired, 0) }}</p>
            <p style="font-size:.7rem;color:rgba(255,255,255,.75);margin-top:.15rem;">เป้าหมาย ชม.</p>
        </div>
    </div>
    
    <div style="margin-top: 1.25rem;">
        <a href="{{ route('student.qr') }}" class="btn btn-white w-full" style="background: #fff; color: #4f46e5; font-weight: 700; border: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            QR Code ของฉัน (แสดงตัวตน)
        </a>
    </div>
</div>

{{-- ข้อมูลส่วนตัว --}}
<div class="card mb-4">
    <div class="card-body">
        <h2 class="font-bold mb-3" style="font-size:1rem;display:flex;align-items:center;gap:.5rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            ข้อมูลส่วนตัว
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem;">
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">รหัสนักศึกษา</p>
                <p class="font-semi text-sm">{{ $user->student_id ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">ชื่อ-นามสกุล</p>
                <p class="font-semi text-sm">{{ $user->full_name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">คณะ</p>
                <p class="font-semi text-sm">{{ $user->faculty ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">สาขา</p>
                <p class="font-semi text-sm">{{ $user->department ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">ชั้นปี</p>
                <p class="font-semi text-sm">{{ $user->year ? 'ปี ' . $user->year : '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">อีเมล</p>
                <p class="font-semi text-sm">{{ $user->email ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">ภาคเรียน</p>
                <p class="font-semi text-sm">{{ $user->program ?? '-' }}</p>
            </div>
        </div>
    </div>
</div>

{{-- ชั่วโมงแยกตามหมวดหมู่ --}}
<h2 class="font-bold mb-3" style="font-size:1rem;display:flex;align-items:center;gap:.5rem;">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    ชั่วโมงแยกตามหมวดหมู่
</h2>
@foreach($byCategory as $cat)
<div class="card mb-2">
    <div class="card-body" style="padding:.75rem 1rem;">
        @php $p = $cat['required'] > 0 ? min(100, ($cat['hours']/$cat['required'])*100) : 0; @endphp
        <div class="flex justify-between items-center mb-2">
            <span class="font-semi text-sm">{{ $cat['name'] }}</span>
            <span class="text-xs font-bold" style="background:#f1f5f9; padding:.15rem .4rem; border-radius:4px; color:#475569;">
                {{ number_format($p, 0) }}%
            </span>
        </div>
        <div class="progress" style="height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: .4rem;">
            <div class="progress-bar {{ $p >= 100 ? 'green' : ($p >= 50 ? 'yellow' : 'primary') }}" style="width:{{ $p }}%; height: 100%; transition: width 0.5s ease;"></div>
        </div>
        <div class="flex justify-between items-center">
            @if($p >= 100)
                <p class="text-xs font-semi" style="color:#16a34a;">✓ ผ่านเกณฑ์ขั้นต่ำ</p>
            @else
                <p class="text-xs text-muted">ต้องการอีก <span style="font-weight:600; color:#ef4444;">{{ number_format($cat['required'] - $cat['hours'], 1) }}</span> ชม.</p>
            @endif
            <span class="text-sm">
                <span style="color:{{ $p >= 100 ? '#16a34a' : '#1e40af' }};font-weight:700;">{{ number_format($cat['hours'], 1) }}</span>
                <span class="text-muted">/{{ number_format($cat['required'], 0) }} ชม.</span>
            </span>
        </div>
    </div>
</div>
@endforeach

{{-- ประวัติกิจกรรมล่าสุด --}}
<div class="flex items-center justify-between mt-4 mb-3">
    <h2 class="font-bold" style="font-size:1rem;display:flex;align-items:center;gap:.5rem;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        กิจกรรมล่าสุด
    </h2>
    <a href="{{ route('student.history') }}" class="text-sm text-primary">ดูทั้งหมด →</a>
</div>
@forelse($recentAttendances as $att)
<div class="card mb-2">
    <div class="card-body" style="padding:.7rem 1rem;">
        <div class="flex items-center justify-between gap-2">
            <div style="flex:1;min-width:0;">
                <p class="font-semi text-sm line-clamp-1">{{ $att->activity->title }}</p>
                <p class="text-xs text-muted" style="margin-top:.1rem;">
                    {{ $att->checked_in_at?->format('d/m/Y H:i') ?? '-' }}
                    @if($att->activity->category)
                        · {{ $att->activity->category->name }}
                    @endif
                </p>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <span class="badge {{ $att->status === 'approved' ? 'badge-green' : ($att->status === 'pending' ? 'badge-yellow' : 'badge-red') }}">
                    {{ $att->status === 'approved' ? 'อนุมัติ' : ($att->status === 'pending' ? 'รออนุมัติ' : 'ปฏิเสธ') }}
                </span>
                @if($att->status === 'approved')
                    <p class="text-xs font-semi" style="color:#16a34a;margin-top:.2rem;">+{{ $att->activity->activity_hours }} ชม.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center;padding:2rem;color:#94a3b8;">
        <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 1rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p>ยังไม่มีประวัติการเข้าร่วมกิจกรรม</p>
    </div>
</div>
@endforelse

{{-- ปุ่มดาวน์โหลด PDF --}}
<div style="margin-top:1.5rem;text-align:center;">
    <a href="{{ route('student.summary.pdf') }}" class="btn btn-primary" style="gap:.5rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        ดาวน์โหลดใบแสดงผลกิจกรรม (PDF)
    </a>
</div>

{{-- ── LINE Account Linking ── --}}
<div class="card mt-3">
    <div class="card-body">
        <div class="flex items-center gap-3 mb-3">
            {{-- LINE Logo --}}
            <div style="width:40px;height:40px;background:#06c755;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                    <path d="M19.365 9.89c.50 0 .907.407.907.907s-.407.907-.907.907h-2.538v1.59h2.538c.5 0 .907.407.907.907s-.407.907-.907.907H15.92a.907.907 0 01-.907-.907V9.89c0-.5.407-.907.907-.907h3.445zm-6.742 0c.5 0 .907.407.907.907v4.311a.907.907 0 01-1.814 0V9.89c0-.5.407-.907.907-.907zm-2.17 0c.282 0 .534.13.7.334l2.808 3.813V9.89c0-.5.407-.907.907-.907s.907.407.907.907v4.311a.907.907 0 01-1.607.573l-2.808-3.813v2.333a.907.907 0 01-1.814 0V9.89c0-.5.407-.907.907-.907zm-3.352 0c.5 0 .907.407.907.907v4.311a.907.907 0 01-1.814 0V9.89c0-.5.407-.907.907-.907zM12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.683-.217.683-.482 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12c0-5.523-4.477-10-10-10z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold" style="font-size:1rem;">การแจ้งเตือนผ่าน LINE</h3>
                <p class="text-sm text-muted">รับข่าวสารกิจกรรม ประกาศ และงาน ผ่าน LINE OA</p>
            </div>
        </div>

        @if(auth()->user()->line_user_id)
            {{-- ผูกแล้ว --}}
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                <div class="flex items-center gap-2">
                    <svg width="20" height="20" fill="none" stroke="#16a34a" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-semi text-sm" style="color:#16a34a;">ผูกบัญชี LINE แล้ว</span>
                </div>
                <p class="text-sm" style="margin-top:.4rem;color:#555;">
                    LINE: <span class="font-semi">{{ auth()->user()->line_display_name ?? 'ไม่ทราบชื่อ' }}</span>
                </p>
            </div>

            <div class="flex gap-2" style="flex-wrap:wrap;">
                {{-- ปุ่มเปิด/ปิด notify --}}
                <form method="POST" action="{{ route('line.toggle-notify') }}" style="flex:1;">
                    @csrf
                    @if(auth()->user()->line_notify_enabled)
                        <button type="submit" class="btn btn-outline w-full" style="font-size:.875rem;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            ปิดการแจ้งเตือน
                        </button>
                    @else
                        <button type="submit" class="btn btn-primary w-full" style="font-size:.875rem;background:#06c755;border-color:#06c755;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            เปิดการแจ้งเตือน
                        </button>
                    @endif
                </form>

                {{-- ปุ่มยกเลิกผูก --}}
                <form method="POST" action="{{ route('line.unlink') }}" onsubmit="return confirm('ต้องการยกเลิกการผูกบัญชี LINE ใช่หรือไม่?')">
                    @csrf
                    <button type="submit" class="btn btn-outline" style="font-size:.875rem;color:#ef4444;border-color:#ef4444;">
                        ยกเลิกผูก LINE
                    </button>
                </form>
            </div>

            @if(!auth()->user()->line_notify_enabled)
                <p class="text-xs text-muted" style="margin-top:.75rem;">
                    ⚠️ การแจ้งเตือน LINE ปิดอยู่ คุณจะไม่ได้รับข่าวสารทาง LINE
                </p>
            @endif

        @else
            {{-- ยังไม่ได้ผูก --}}
            <div style="background:#fafafa;border:1px solid #e2e8f0;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                <p class="text-sm text-muted" style="margin-bottom:.5rem;">เมื่อผูกบัญชี LINE แล้ว คุณจะได้รับ:</p>
                <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
                    <li class="text-sm flex items-center gap-2">
                        <svg width="14" height="14" fill="#06c755" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        การแจ้งเตือนเมื่อมีกิจกรรมใหม่
                    </li>
                    <li class="text-sm flex items-center gap-2">
                        <svg width="14" height="14" fill="#06c755" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        การแจ้งเตือนประกาศงาน / Part-time ใหม่
                    </li>
                    <li class="text-sm flex items-center gap-2">
                        <svg width="14" height="14" fill="#06c755" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        การแจ้งเตือนประกาศข่าวสาร
                    </li>
                    <li class="text-sm flex items-center gap-2">
                        <svg width="14" height="14" fill="#06c755" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Reminder กิจกรรมที่ลงทะเบียนไว้ (1 วันก่อน)
                    </li>
                </ul>
            </div>

            <a href="{{ route('line.redirect') }}" 
               class="btn w-full" 
               style="background:#06c755;color:#fff;border:none;display:flex;align-items:center;justify-content:center;gap:.6rem;font-weight:600;font-size:.95rem;padding:.75rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                    <path d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.683-.217.683-.482 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12c0-5.523-4.477-10-10-10z"/>
                </svg>
                ผูกบัญชี LINE
            </a>
        @endif
    </div>
</div>
@endsection
