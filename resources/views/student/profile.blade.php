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
        <div class="flex justify-between items-center mb-1">
            <span class="font-semi text-sm">{{ $cat['name'] }}</span>
            <span class="text-sm">
                <span style="color:{{ $cat['hours'] >= $cat['required'] ? '#16a34a' : '#1e40af' }};font-weight:600;">{{ number_format($cat['hours'], 1) }}</span>
                <span class="text-muted">/{{ number_format($cat['required'], 0) }} ชม.</span>
            </span>
        </div>
        <div class="progress">
            @php $p = $cat['required'] > 0 ? min(100, ($cat['hours']/$cat['required'])*100) : 0; @endphp
            <div class="progress-bar {{ $p >= 100 ? 'green' : ($p >= 50 ? 'yellow' : 'primary') }}" style="width:{{ $p }}%"></div>
        </div>
        @if($p >= 100)
            <p class="text-xs" style="color:#16a34a;margin-top:.25rem;">✓ ผ่านเกณฑ์แล้ว</p>
        @else
            <p class="text-xs text-muted" style="margin-top:.25rem;">ต้องการอีก {{ number_format($cat['required'] - $cat['hours'], 1) }} ชม.</p>
        @endif
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
@endsection
