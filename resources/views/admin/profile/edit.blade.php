@extends('layouts.admin')
@section('title', 'จัดการโปรไฟล์ผู้ใช้')

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%;">

    {{-- ── 1. Page Header & Identity Banner ─────────────────────────────── --}}
    <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 16px; padding: 1.75rem 2rem; color: #fff; box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.25); position: relative; overflow: hidden;">
        {{-- Background Geometric Accents --}}
        <div style="position: absolute; right: -20px; top: -20px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(234, 88, 12, 0.15) 0%, rgba(234, 88, 12, 0) 70%); border-radius: 50%; pointer-events: none;"></div>
        <div style="position: absolute; right: 180px; bottom: -40px; width: 180px; height: 180px; background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0) 70%); border-radius: 50%; pointer-events: none;"></div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem; position: relative; z-index: 1;">
            <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                {{-- Avatar with Upload Overlay --}}
                <div style="position: relative; width: 80px; height: 80px; flex-shrink: 0;">
                    <label for="profilePhotoInput" style="cursor: pointer; display: block; width: 100%; height: 100%; border-radius: 50%; position: relative; overflow: hidden; border: 3px solid rgba(255,255,255,0.2); box-shadow: 0 4px 12px rgba(0,0,0,0.3);" title="คลิกเพื่ออัปโหลดรูปโปรไฟล์ใหม่">
                        @if($user->profile_photo)
                            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ $user->full_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.85rem; font-weight: 800;">
                                {{ strtoupper(substr($user->full_name ?? 'A', 0, 1)) }}
                            </div>
                        @endif
                        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s;" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0">
                            <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                        </div>
                    </label>

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
                            <span style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.35); font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.65rem; borderRadius: 9999px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                ผู้ดูแลระบบสูงสุด (Super Admin)
                            </span>
                        @else
                            <span style="background: rgba(234, 88, 12, 0.2); color: #fdba74; border: 1px solid rgba(234, 88, 12, 0.35); font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.65rem; borderRadius: 9999px; display: inline-flex; align-items: center; gap: 0.35rem;">
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

            {{-- Photo Action Buttons --}}
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <label for="profilePhotoInput" style="display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(255, 255, 255, 0.12); hover: background: rgba(255, 255, 255, 0.2); color: #fff; border: 1px solid rgba(255, 255, 255, 0.25); padding: 0.5rem 0.95rem; border-radius: 8px; font-size: 0.825rem; font-weight: 600; cursor: pointer; backdrop-filter: blur(4px); transition: all 0.2s;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                    <span>เปลี่ยนรูปโปรไฟล์</span>
                </label>
                @if($user->profile_photo)
                    <form method="POST" action="{{ route('profile.photo.destroy') }}" style="display: inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรูปโปรไฟล์นี้?');">
                        @csrf @method('DELETE')
                        <button type="submit" style="display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.35); padding: 0.5rem 0.85rem; border-radius: 8px; font-size: 0.825rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            <span>ลบรูป</span>
                        </button>
                    </form>
                @endif
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

    @if($errors->any())
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 1rem 1.25rem; color: #991b1b; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; margin-bottom: 0.35rem;">
                <svg width="18" height="18" fill="none" stroke="#ef4444" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>กรุณาตรวจสอบข้อผิดพลาดด้านล่าง:</span>
            </div>
            <ul style="margin: 0 0 0 1.5rem; padding: 0; font-size: 0.85rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── 2. Full-Width Responsive Layout Grid ─────────────────────────── --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">

        {{-- ── LEFT COLUMN: Identity, KPI Stats & Permission Matrix ── --}}
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">

            {{-- 3-Item KPI Stats Card --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">
                    ผลงานและการมีส่วนร่วมในระบบ
                </span>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-top: 0.75rem;">
                    <div style="background: #fafbfc; border: 1px solid #f1f5f9; border-radius: 10px; padding: 0.85rem 0.5rem; text-align: center;">
                        <div style="font-size: 1.4rem; font-weight: 800; color: #ea580c;">{{ $stats['activities_count'] }}</div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.2rem; font-weight: 600;">กิจกรรมที่สร้าง</div>
                    </div>
                    <div style="background: #fafbfc; border: 1px solid #f1f5f9; border-radius: 10px; padding: 0.85rem 0.5rem; text-align: center;">
                        <div style="font-size: 1.4rem; font-weight: 800; color: #6366f1;">{{ $stats['announcements_count'] }}</div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.2rem; font-weight: 600;">ข่าวประกาศ</div>
                    </div>
                    <div style="background: #fafbfc; border: 1px solid #f1f5f9; border-radius: 10px; padding: 0.85rem 0.5rem; text-align: center;">
                        <div style="font-size: 1.4rem; font-weight: 800; color: #10b981;">{{ $stats['audit_logs_count'] }}</div>
                        <div style="font-size: 0.725rem; color: #64748b; margin-top: 0.2rem; font-weight: 600;">ประวัติการทำงาน</div>
                    </div>
                </div>
            </div>

            {{-- System Permission & Security Status Card --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                        <svg width="18" height="18" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <h3 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">สิทธิ์การเข้าถึงและการควบคุมระบบ</h3>
                    </div>
                    <span style="background: #dcfce7; color: #15803d; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px;">
                        ACTIVE
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                    @if($user->isAdmin())
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.825rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>จัดการกิจกรรมและข่าวประกาศได้ทั้งระบบ</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.825rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>จัดการบัญชีผู้ใช้งานและหมวดหมู่กิจกรรม</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.825rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>เข้าถึงบันทึกความปลอดภัย (Audit Log) ทั้งหมด</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.825rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>ส่งออกข้อมูลรายงานระบบภาพรวมระดับสถาบัน</span>
                        </div>
                    @else
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.825rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>สร้างและจัดการกิจกรรม/ประกาศของตนเอง</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.825rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>เช็คชื่อและสแกนใบหน้านักศึกษาในกิจกรรม</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.55rem; font-size: 0.825rem; color: #334155;">
                            <svg width="16" height="16" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>ส่งออกรายชื่อผู้เข้าร่วมเป็น Excel/PDF</span>
                        </div>
                    @endif
                </div>

                <div style="margin-top: 1rem; padding-top: 0.85rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; font-size: 0.775rem; color: #64748b;">
                    <span>เข้าร่วมระบบเมื่อ:</span>
                    <strong style="color: #0f172a;">{{ $user->created_at ? $user->created_at->translatedFormat('d M Y') : '—' }}</strong>
                </div>
            </div>

            {{-- Recent Audit Activity Card --}}
            @if(isset($recentLogs) && $recentLogs->count() > 0)
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">
                            ประวัติกิจกรรมล่าสุดของคุณ
                        </span>
                        <a href="{{ route('admin.audit-logs.index', ['user_id' => $user->id]) }}" style="font-size: 0.75rem; color: #ea580c; font-weight: 600; text-decoration: none;">
                            ดูทั้งหมด ➔
                        </a>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        @foreach($recentLogs as $log)
                            <div style="padding: 0.55rem 0.7rem; background: #fafbfc; border-radius: 8px; border: 1px solid #f1f5f9; font-size: 0.8rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: #0f172a; font-size: 0.8rem;">{{ $log->description ?: $log->action }}</strong>
                                    <span style="font-size: 0.7rem; color: #94a3b8;">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.15rem; font-family: monospace;">
                                    IP: {{ $log->ip_address }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        {{-- ── RIGHT COLUMN: Main Edit Forms (Personal Info & Password) ── --}}
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            {{-- 1. Personal & Organization Details Form --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <h2 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            ข้อมูลส่วนตัวและสังกัดหน่วยงาน
                        </h2>
                        <p style="margin: 0.2rem 0 0 0; font-size: 0.8rem; color: #64748b;">
                            ข้อมูลนี้จะแสดงในกิจกรรม ข่าวประกาศ และบันทึกประวัติการทำงานของระบบ
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.15rem;">
                        {{-- ชื่อ-นามสกุล (ภาษาไทย) --}}
                        <div>
                            <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                ชื่อ-นามสกุล (ภาษาไทย) <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative;">
                                <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" required
                                    style="width: 100%; padding: 0.55rem 0.75rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='#ea580c'" onblur="this.style.borderColor='#cbd5e1'">
                            </div>
                        </div>

                        {{-- Full Name (English) --}}
                        <div>
                            <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                ชื่อ-นามสกุล (English) <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative;">
                                <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                </div>
                                <input type="text" name="english_name" value="{{ old('english_name', $user->english_name) }}" required
                                    style="width: 100%; padding: 0.55rem 0.75rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='#ea580c'" onblur="this.style.borderColor='#cbd5e1'">
                            </div>
                        </div>

                        {{-- Email Address --}}
                        <div>
                            <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                อีเมลสำหรับเข้าสู่ระบบ <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative;">
                                <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                                    style="width: 100%; padding: 0.55rem 0.75rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='#ea580c'" onblur="this.style.borderColor='#cbd5e1'">
                            </div>
                        </div>

                        {{-- Phone Number --}}
                        <div>
                            <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                เบอร์โทรศัพท์ติดต่อ
                            </label>
                            <div style="position: relative;">
                                <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                </div>
                                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="เช่น 081-234-5678"
                                    style="width: 100%; padding: 0.55rem 0.75rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='#ea580c'" onblur="this.style.borderColor='#cbd5e1'">
                            </div>
                        </div>

                        {{-- Position --}}
                        <div>
                            <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                ตำแหน่งงาน (Position / Role)
                            </label>
                            <div style="position: relative;">
                                <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <input type="text" name="position" value="{{ old('position', $user->position) }}" placeholder="เช่น เจ้าหน้าที่ฝ่ายพัฒนานักศึกษา"
                                    style="width: 100%; padding: 0.55rem 0.75rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='#ea580c'" onblur="this.style.borderColor='#cbd5e1'">
                            </div>
                        </div>

                        {{-- Organization / Department --}}
                        <div>
                            <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                หน่วยงาน / คณะสังกัด
                            </label>
                            <div style="position: relative;">
                                <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <input type="text" name="organization" value="{{ old('organization', $user->organization) }}" placeholder="เช่น กองกิจการนักศึกษา"
                                    style="width: 100%; padding: 0.55rem 0.75rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='#ea580c'" onblur="this.style.borderColor='#cbd5e1'">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                        <button type="submit" style="display: inline-flex; align-items: center; gap: 0.45rem; background: #ea580c; color: #fff; border: none; padding: 0.6rem 1.4rem; border-radius: 8px; font-size: 0.875rem; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25); transition: background 0.2s;" onmouseenter="this.style.background='#c2410c'" onmouseleave="this.style.background='#ea580c'">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>บันทึกข้อมูลส่วนตัว</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- 2. Security & Password Change Form --}}
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <h2 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            ความปลอดภัย & เปลี่ยนรหัสผ่าน
                        </h2>
                        <p style="margin: 0.2rem 0 0 0; font-size: 0.8rem; color: #64748b;">
                            แนะนำให้ตั้งรหัสผ่านที่มีความยาวอย่างน้อย 8 ตัวอักษร ผสมตัวอักษรพิมพ์ใหญ่ พิมพ์เล็ก และตัวเลข
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    {{-- Hidden dummy inputs to retain personal fields when submitting password only --}}
                    <input type="hidden" name="full_name" value="{{ $user->full_name }}">
                    <input type="hidden" name="english_name" value="{{ $user->english_name }}">
                    <input type="hidden" name="email" value="{{ $user->email }}">
                    <input type="hidden" name="phone" value="{{ $user->phone }}">
                    <input type="hidden" name="position" value="{{ $user->position }}">
                    <input type="hidden" name="organization" value="{{ $user->organization }}">

                    <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                        {{-- Current Password --}}
                        <div>
                            <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                รหัสผ่านเดิม (Current Password) <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative;">
                                <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                                <input type="password" id="input_password_old" name="password_old" required placeholder="กรอกรหัสผ่านปัจจุบันของคุณ"
                                    style="width: 100%; padding: 0.55rem 2.4rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                    onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#cbd5e1'">
                                <button type="button" onclick="togglePass('input_password_old')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; display: flex;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.15rem;">
                            {{-- New Password --}}
                            <div>
                                <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                    รหัสผ่านใหม่ (New Password) <span style="color: #ef4444;">*</span>
                                </label>
                                <div style="position: relative;">
                                    <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    </div>
                                    <input type="password" id="input_password" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร"
                                        style="width: 100%; padding: 0.55rem 2.4rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                        onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#cbd5e1'">
                                    <button type="button" onclick="togglePass('input_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; display: flex;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Confirm Password --}}
                            <div>
                                <label style="display: block; font-size: 0.825rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                                    ยืนยันรหัสผ่านใหม่ (Confirm Password) <span style="color: #ef4444;">*</span>
                                </label>
                                <div style="position: relative;">
                                    <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8; display: flex;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <input type="password" id="input_password_confirmation" name="password_confirmation" required placeholder="กรอกรหัสผ่านใหม่อีกครั้ง"
                                        style="width: 100%; padding: 0.55rem 2.4rem 0.55rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s;"
                                        onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#cbd5e1'">
                                    <button type="button" onclick="togglePass('input_password_confirmation')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; display: flex;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                        <button type="submit" style="display: inline-flex; align-items: center; gap: 0.45rem; background: #4f46e5; color: #fff; border: none; padding: 0.6rem 1.4rem; border-radius: 8px; font-size: 0.875rem; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25); transition: background 0.2s;" onmouseenter="this.style.background='#4338ca'" onmouseleave="this.style.background='#4f46e5'">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span>อัปเดตรหัสผ่านใหม่</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>

</div>

<script>
function togglePass(id) {
    var elem = document.getElementById(id);
    if (!elem) return;
    elem.type = elem.type === 'password' ? 'text' : 'password';
}
</script>
@endsection
