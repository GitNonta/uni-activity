@extends('layouts.admin')
@section('title', 'จัดการโปรไฟล์')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="font-bold" style="font-size:1.5rem; color:#1e293b;">จัดการโปรไฟล์</h1>
        <p class="text-sm text-muted mt-1">ตั้งค่าและจัดการข้อมูลส่วนตัวของคุณในระบบ</p>
    </div>
</div>

<div style="max-width: 580px; margin: 0 auto;">
    <div style="display:flex; flex-direction:column; gap:1.5rem;">
        
        {{-- การ์ด Avatar และข้อมูลเบื้องต้น --}}
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); text-align:center;">
            <div class="card-body" style="padding-top:2rem; padding-bottom:1.5rem;">
                <div style="position: relative; display: inline-block; margin-bottom:1rem;">
                    <label for="photoInput" style="cursor: pointer; display: block;" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                        @if($user->profile_photo)
                            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="profile"
                                style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                        @else
                            <div style="display:inline-flex; align-items:center; justify-content:center; width:90px; height:90px; border-radius:50%; background:#e0e7ff; color:#4f46e5; font-size:2rem; font-weight:700; border:4px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                {{ strtoupper(substr($user->full_name ?? 'A', 0, 1)) }}
                            </div>
                        @endif
                        <div style="position: absolute; bottom: 0; right: 0; width: 26px; height: 26px; background: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); border: 1px solid #e2e8f0;">
                            <svg width="14" height="14" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                        </div>
                    </label>
                    <form id="photoForm" method="POST" action="{{ route('profile.photo.upload') }}" enctype="multipart/form-data" style="display:none;">
                        @csrf
                        <input type="file" id="photoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp"
                            onchange="document.getElementById('photoForm').submit()">
                    </form>
                </div>
                
                @if($user->profile_photo)
                    <div style="margin-top:-0.5rem; margin-bottom:1rem;">
                        <form method="POST" action="{{ route('profile.photo.destroy') }}" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" style="background: none; border: none; color: #ef4444; font-size: 0.75rem; cursor: pointer; text-decoration: underline; padding: 0;"
                                onclick="return confirm('ต้องการลบรูปโปรไฟล์?')">ลบรูปโปรไฟล์</button>
                        </form>
                    </div>
                @endif

                <div style="display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:0.25rem;">
                    <h2 class="font-bold" style="font-size:1.25rem; color:#1e293b; margin:0;">{{ $user->full_name }}</h2>
                    <a href="{{ route('admin.settings.index', ['tab' => 'privacy']) }}" title="แก้ไขข้อมูลโปรไฟล์ & ตั้งค่าความเป็นส่วนตัว" style="display:inline-flex; align-items:center; justify-content:center; color:#6366f1; background:transparent; padding:0; border:none; text-decoration:none; transition:color 0.2s, transform 0.2s;" onmouseover="this.style.color='#4338ca'; this.style.transform='scale(1.15)';" onmouseout="this.style.color='#6366f1'; this.style.transform='scale(1)';">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </a>
                </div>
                <p class="text-sm text-muted mb-4">{{ $user->position ?? 'ไม่ได้ระบุตำแหน่ง' }}</p>
                
                @if($user->isAdmin())
                    <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:999px; font-size:0.75rem; font-weight:600; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;">
                        <span style="display:block; width:6px; height:6px; border-radius:50%; background:#ef4444;"></span>
                        ผู้ดูแลระบบ (Admin)
                    </span>
                @else
                    <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:999px; font-size:0.75rem; font-weight:600; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;">
                        <span style="display:block; width:6px; height:6px; border-radius:50%; background:#3b82f6;"></span>
                        เจ้าหน้าที่ (Staff)
                    </span>
                @endif
            </div>
            
            <div style="display:flex; border-top:1px solid #f1f5f9; padding:0;">
                <div class="flex-1" style="padding:1rem 0; border-right:1px solid #f1f5f9;">
                    <div class="font-bold" style="font-size:1.25rem; color:#334155;">{{ $stats['activities_count'] }}</div>
                    <div class="text-xs text-muted mt-1" style="text-transform:uppercase; letter-spacing:0.05em;">กิจกรรม</div>
                </div>
                <div class="flex-1" style="padding:1rem 0;">
                    <div class="font-bold" style="font-size:1.25rem; color:#334155;">{{ $stats['announcements_count'] }}</div>
                    <div class="text-xs text-muted mt-1" style="text-transform:uppercase; letter-spacing:0.05em;">ประกาศ</div>
                </div>
            </div>
        </div>

        {{-- สิทธิ์การเข้าถึง (Access Rights) --}}
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <div class="card-header flex items-center gap-2" style="background:#f8fafc; border-bottom:1px solid #f1f5f9; padding:0.75rem 1.25rem;">
                <svg style="width:16px; height:16px; color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span class="font-semi text-sm" style="color:#334155;">สิทธิ์การเข้าถึงระบบ</span>
            </div>
            <div class="card-body" style="padding:1.25rem; display:flex; flex-direction:column; gap:0.75rem;">
                @if($user->isAdmin())
                    <div class="flex gap-2">
                        <svg style="width:18px; height:18px; color:#10b981; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm" style="color:#334155;">จัดการกิจกรรมและประกาศได้ทั้งหมด</span>
                    </div>
                    <div class="flex gap-2">
                        <svg style="width:18px; height:18px; color:#10b981; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm" style="color:#334155;">จัดการหมวดหมู่และบัญชีผู้ใช้งาน</span>
                    </div>
                    <div class="flex gap-2">
                        <svg style="width:18px; height:18px; color:#10b981; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm" style="color:#334155;">มีสิทธิ์เข้าถึงหน้าบันทึก (Audit Log)</span>
                    </div>
                @else
                    <div class="flex gap-2">
                        <svg style="width:18px; height:18px; color:#10b981; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm" style="color:#334155;">จัดการเฉพาะกิจกรรม/ประกาศของตนเอง</span>
                    </div>
                    <div class="flex gap-2 opacity-60">
                        <svg style="width:18px; height:18px; color:#94a3b8; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <span class="text-sm" style="color:#64748b; text-decoration:line-through;">จัดการในระดับผู้ดูแลระบบ</span>
                    </div>
                @endif
                <div class="flex gap-2">
                    <svg style="width:18px; height:18px; color:#10b981; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-sm" style="color:#334155;">ส่งออกรายงานระบบแบบ Excel</span>
                </div>
            </div>
            <div style="background:#f8fafc; border-top:1px solid #f1f5f9; padding:0.75rem 1.25rem; display:flex; justify-content:space-between; align-items:center;">
                <span class="text-xs text-muted">เข้าร่วมครั้งแรก</span>
                <span class="text-xs font-semi text-muted">{{ $user->created_at->translatedFormat('d M Y') }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
