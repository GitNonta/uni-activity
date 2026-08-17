@extends('layouts.admin')
@section('title', 'จัดการโปรไฟล์ผู้ใช้')

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%;">

    {{-- ── 1. Executive Identity Banner ────────────────────────────────── --}}
    <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; padding: 1.75rem 2rem; color: #fff; box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.25); position: relative; overflow: hidden;">
        {{-- Background Geometric Accents --}}
        <div style="position: absolute; right: -20px; top: -20px; width: 240px; height: 240px; background: radial-gradient(circle, rgba(234, 88, 12, 0.18) 0%, rgba(234, 88, 12, 0) 70%); border-radius: 50%; pointer-events: none;"></div>
        <div style="position: absolute; right: 220px; bottom: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(99, 102, 241, 0.18) 0%, rgba(99, 102, 241, 0) 70%); border-radius: 50%; pointer-events: none;"></div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem; position: relative; z-index: 1;">
            <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                {{-- Avatar Container with Floating Badges on Edge --}}
                <div style="position: relative; width: 88px; height: 88px; flex-shrink: 0;">
                    <label for="profilePhotoInput" style="cursor: pointer; display: block; width: 100%; height: 100%; border-radius: 50%; position: relative; overflow: hidden; border: 3px solid rgba(255,255,255,0.25); box-shadow: 0 4px 14px rgba(0,0,0,0.35);" title="คลิกเพื่ออัปโหลดรูปโปรไฟล์ใหม่">
                        @if($user->profile_photo)
                            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ $user->full_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800;">
                                {{ strtoupper(substr($user->full_name ?? 'A', 0, 1)) }}
                            </div>
                        @endif
                        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s;" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0">
                            <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                        </div>
                    </label>

                    {{-- Camera Badge on Bottom-Right Edge --}}
                    <label for="profilePhotoInput" style="position: absolute; bottom: -2px; right: -2px; width: 28px; height: 28px; background: #ea580c; border: 2.5px solid #1e293b; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.3); transition: transform 0.15s, background 0.15s; z-index: 2;" title="เปลี่ยนรูปโปรไฟล์" onmouseenter="this.style.transform='scale(1.15)'; this.style.background='#c2410c';" onmouseleave="this.style.transform='scale(1)'; this.style.background='#ea580c';">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                    </label>

                    {{-- Delete Trash Badge on Top-Right Edge (if photo exists) --}}
                    @if($user->profile_photo)
                        <form method="POST" action="{{ route('profile.photo.destroy') }}" style="position: absolute; top: -2px; right: -2px; z-index: 2; margin: 0;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรูปโปรไฟล์นี้?');">
                            @csrf @method('DELETE')
                            <button type="submit" style="width: 24px; height: 24px; background: #ef4444; border: 2px solid #1e293b; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.3); padding: 0; transition: transform 0.15s, background 0.15s;" title="ลบรูปโปรไฟล์" onmouseenter="this.style.transform='scale(1.15)'; this.style.background='#b91c1c';" onmouseleave="this.style.transform='scale(1)'; this.style.background='#ef4444';">
                                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    @endif

                    <form id="profilePhotoForm" method="POST" action="{{ route('profile.photo.upload') }}" enctype="multipart/form-data" style="display: none;">
                        @csrf
                        <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp" onchange="document.getElementById('profilePhotoForm').submit();">
                    </form>
                </div>

                <div>
                    <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                        <h1 style="margin: 0; font-size: 1.65rem; font-weight: 800; color: #fff; letter-spacing: -0.02em;">
                            {{ $user->full_name }}
                        </h1>
                        @if($user->isAdmin())
                            <span style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.35); font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.65rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                ผู้ดูแลระบบสูงสุด (Super Admin)
                            </span>
                        @else
                            <span style="background: rgba(234, 88, 12, 0.2); color: #fdba74; border: 1px solid rgba(234, 88, 12, 0.35); font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.65rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                เจ้าหน้าที่ระบบ (Staff)
                            </span>
                        @endif
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.85rem; margin-top: 0.35rem; color: #94a3b8; font-size: 0.85rem; flex-wrap: wrap;">
                        <span>{{ $user->english_name ?: 'No English Name' }}</span>
                        <span>•</span>
                        <span>{{ $user->email }}</span>
                        <span>•</span>
                        <span>สังกัด: <strong style="color: #cbd5e1;">{{ $user->organization ?: ($user->position ?: 'หน่วยงานส่วนกลาง') }}</strong></span>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                <a href="{{ route('admin.settings.index', ['tab' => 'privacy']) }}" style="display: inline-flex; align-items: center; gap: 0.45rem; background: #ea580c; color: #fff; padding: 0.55rem 1.1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.3); transition: all 0.2s;" onmouseenter="this.style.background='#c2410c'" onmouseleave="this.style.background='#ea580c'">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>แก้ไขข้อมูลส่วนตัว & รหัสผ่าน</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 1rem 1.25rem; color: #065f46; display: flex; align-items: center; gap: 0.65rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <svg width="20" height="20" fill="none" stroke="#10b981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span style="font-weight: 600; font-size: 0.9rem;">{{ session('success') }}</span>
        </div>
    @endif

    {{-- ── 2. 4-Column KPI Metrics ─────────────────────────────────────── --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        {{-- Card 1: Activities --}}
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">กิจกรรมที่สร้าง</span>
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #fff7ed; color: #ea580c; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div style="display: flex; align-items: baseline; gap: 0.4rem;">
                <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a;">{{ $stats['activities_count'] }}</div>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">รายการ</span>
            </div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">กิจกรรมในระบบทั้งหมดที่คุณสร้าง</div>
        </div>

        {{-- Card 2: Announcements --}}
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">ข่าวประกาศ</span>
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                </div>
            </div>
            <div style="display: flex; align-items: baseline; gap: 0.4rem;">
                <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a;">{{ $stats['announcements_count'] }}</div>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">ข่าวสาร</span>
            </div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">ประกาศแจ้งเตือนนักศึกษา</div>
        </div>

        {{-- Card 3: Audit Logs --}}
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">ประวัติการทำงาน</span>
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
            </div>
            <div style="display: flex; align-items: baseline; gap: 0.4rem;">
                <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a;">{{ $stats['audit_logs_count'] }}</div>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">บันทึก</span>
            </div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">รายการ Audit Log ในระบบ</div>
        </div>

        {{-- Card 4: Security Posture --}}
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">สถานะความปลอดภัย</span>
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #f0fdf4; color: #15803d; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <div style="display: flex; align-items: baseline; gap: 0.4rem;">
                <div style="font-size: 1.35rem; font-weight: 800; color: #15803d;">ปกป้อง 100%</div>
            </div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">เข้ารหัส PDPA & Zero-Trust</div>
        </div>
    </div>

    {{-- ── 3. Full-Width Responsive Layout Grid ─────────────────────────── --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; align-items: start;">

        {{-- ── LEFT COLUMN: Identity & Access Matrix ── --}}
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            {{-- Profile & Organization Overview Card --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="18" height="18" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        ข้อมูลสังกัดและข้อมูลติดต่อ
                    </h3>
                    <a href="{{ route('admin.settings.index', ['tab' => 'privacy']) }}" style="font-size: 0.75rem; font-weight: 600; color: #ea580c; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                        <span>แก้ไข</span>
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.8rem; color: #64748b;">ชื่อ-นามสกุล (ไทย)</span>
                        <strong style="color: #0f172a; font-size: 0.875rem;">{{ $user->full_name }}</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.8rem; color: #64748b;">Full Name (EN)</span>
                        <strong style="color: #0f172a; font-size: 0.875rem;">{{ $user->english_name ?: '—' }}</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.8rem; color: #64748b;">อีเมลสำหรับล็อกอิน</span>
                        <strong style="color: #0f172a; font-size: 0.875rem;">{{ $user->email }}</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.8rem; color: #64748b;">เบอร์โทรศัพท์</span>
                        <strong style="color: #0f172a; font-size: 0.875rem;">{{ $user->phone ?: 'ไม่ได้ระบุ' }}</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.8rem; color: #64748b;">ตำแหน่งงาน</span>
                        <strong style="color: #0f172a; font-size: 0.875rem;">{{ $user->position ?: 'ไม่ได้ระบุตำแหน่ง' }}</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <span style="font-size: 0.8rem; color: #64748b;">หน่วยงาน / สังกัด</span>
                        <strong style="color: #0f172a; font-size: 0.875rem;">{{ $user->organization ?: 'หน่วยงานส่วนกลาง' }}</strong>
                    </div>
                </div>
            </div>

            {{-- Access Rights & System Privileges Card --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="18" height="18" fill="none" stroke="#10b981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        สิทธิ์การเข้าถึงระบบ (Role Privileges)
                    </h3>
                    <span style="background: #dcfce7; color: #15803d; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px;">
                        {{ strtoupper($user->role) }}
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @if($user->isAdmin())
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>จัดการกิจกรรม ข่าวสาร และประกาศได้ทั้งหมด</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>จัดการหมวดหมู่ สิทธิ์ และบัญชีผู้ใช้ระบบ</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>ตรวจสอบบันทึกความปลอดภัย (Audit Log) ทั้งระบบ</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>ส่งออกรายงานและสถิติกิจกรรมนักศึกษา</span>
                        </div>
                    @else
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>สร้างและจัดการกิจกรรม/ข่าวสารของตนเอง</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>เช็คชื่อและสแกนใบหน้านักศึกษาในกิจกรรม</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.85rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>ส่งออกรายชื่อผู้เข้าร่วมเป็น Excel/PDF</span>
                        </div>
                    @endif
                </div>

                <div style="margin-top: 1.15rem; padding-top: 0.85rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; font-size: 0.775rem; color: #64748b;">
                    <span>ลงทะเบียนเข้าใช้งานระบบเมื่อ:</span>
                    <strong style="color: #0f172a;">{{ $user->created_at ? $user->created_at->translatedFormat('d F Y') : '—' }}</strong>
                </div>
            </div>

        </div>

        {{-- ── RIGHT COLUMN: Activity Showcase & Audit Trail ── --}}
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            {{-- Recent Activities Created by User Card --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            กิจกรรมที่คุณสร้างล่าสุด
                        </h3>
                        <p style="margin: 0.2rem 0 0 0; font-size: 0.8rem; color: #64748b;">
                            รายการกิจกรรมมหาวิทยาลัยที่คุณเป็นผู้จัดและดูแล
                        </p>
                    </div>
                    <a href="{{ route('admin.activities.create') }}" style="display: inline-flex; align-items: center; gap: 0.35rem; background: #ea580c; color: #fff; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.775rem; font-weight: 700; text-decoration: none;">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>สร้างกิจกรรมใหม่</span>
                    </a>
                </div>

                @if(isset($recentActivities) && $recentActivities->count() > 0)
                    <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                        @foreach($recentActivities as $activity)
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #fafbfc; border-radius: 10px; border: 1px solid #f1f5f9; flex-wrap: wrap; gap: 0.5rem;">
                                <div>
                                    <a href="{{ route('admin.activities.show', $activity->id) }}" style="font-weight: 700; color: #0f172a; font-size: 0.9rem; text-decoration: none;" onmouseenter="this.style.color='#ea580c'" onmouseleave="this.style.color='#0f172a'">
                                        {{ $activity->title }}
                                    </a>
                                    <div style="display: flex; align-items: center; gap: 0.65rem; margin-top: 0.2rem; font-size: 0.75rem; color: #64748b;">
                                        <span>{{ $activity->activity_date ? \Carbon\Carbon::parse($activity->activity_date)->translatedFormat('d M Y') : '—' }}</span>
                                        <span>•</span>
                                        <span>{{ $activity->location ?: 'สถานที่ระบุในกิจกรรม' }}</span>
                                    </div>
                                </div>
                                <span style="background: #e0e7ff; color: #4338ca; font-size: 0.725rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 9999px;">
                                    {{ $activity->hour_count ?? 1 }} ชม.
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="padding: 1.5rem; text-align: center; color: #94a3b8; font-size: 0.85rem;">
                        ยังไม่มีกิจกรรมที่คุณสร้างในระบบ
                    </div>
                @endif
            </div>

            {{-- Recent Audit Activity Timeline Card --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            ไทม์ไลน์บันทึกการปฏิบัติงานล่าสุด (Audit Trail)
                        </h3>
                        <p style="margin: 0.2rem 0 0 0; font-size: 0.8rem; color: #64748b;">
                            บันทึกกิจกรรมความปลอดภัยและการดำเนินการต่างๆ ในระบบของคุณ
                        </p>
                    </div>
                    <a href="{{ route('admin.audit-logs.index', ['user_id' => $user->id]) }}" style="font-size: 0.75rem; font-weight: 600; color: #6366f1; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                        <span>ดูทั้งหมด</span>
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                @if(isset($recentLogs) && $recentLogs->count() > 0)
                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                        @foreach($recentLogs as $log)
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9; font-size: 0.825rem;">
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: #6366f1; flex-shrink: 0;"></div>
                                    <div>
                                        <strong style="color: #0f172a; font-size: 0.825rem;">{{ $log->description ?: $log->action }}</strong>
                                        <div style="font-size: 0.7rem; color: #94a3b8; font-family: monospace; margin-top: 0.1rem;">
                                            IP: {{ $log->ip_address }}
                                        </div>
                                    </div>
                                </div>
                                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500; white-space: nowrap;">
                                    {{ $log->created_at->diffForHumans() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="padding: 1.5rem; text-align: center; color: #94a3b8; font-size: 0.85rem;">
                        ไม่มีประวัติการบันทึกการทำงาน
                    </div>
                @endif
            </div>

        </div>

    </div>

</div>
@endsection
