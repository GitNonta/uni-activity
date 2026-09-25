@extends('layouts.admin')
@section('title', 'ตั้งค่าระบบ')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="font-bold flex items-center gap-3" style="font-size:1.5rem;">
            <svg style="width:28px; height:28px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            ตั้งค่าระบบ
        </h1>
        <p class="text-sm text-muted mt-1">จัดการพารามิเตอร์ของระบบ การเชื่อมต่อ LINE OA และข้อมูลความสมบูรณ์ของเซิร์ฟเวอร์</p>
    </div>
</div>

{{-- Navigation Tabs for Settings & Profile --}}
<div style="display:flex; gap:0.5rem; border-bottom:1px solid #e2e8f0; margin-bottom:1.5rem;">
    <a href="{{ route('admin.settings.index', ['tab' => 'general']) }}" style="padding:0.75rem 1.25rem; font-weight:600; font-size:0.9rem; text-decoration:none; border-bottom: 2px solid {{ $activeTab === 'general' ? '#c2410c' : 'transparent' }}; color: {{ $activeTab === 'general' ? '#c2410c' : '#475569' }}; display:flex; align-items:center; gap:0.5rem; line-height:1.5;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        ตั้งค่าทั่วไป & SSO
    </a>
    <a href="{{ route('admin.settings.index', ['tab' => 'privacy']) }}" style="padding:0.75rem 1.25rem; font-weight:600; font-size:0.9rem; text-decoration:none; border-bottom: 2px solid {{ in_array($activeTab, ['privacy', 'profile']) ? '#c2410c' : 'transparent' }}; color: {{ in_array($activeTab, ['privacy', 'profile']) ? '#c2410c' : '#475569' }}; display:flex; align-items:center; gap:0.5rem; line-height:1.5;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        ข้อมูลส่วนตัวผู้ดูแล & รหัสผ่าน
    </a>
    <a href="{{ route('admin.settings.index', ['tab' => 'auto-approval']) }}" style="padding:0.75rem 1.25rem; font-weight:600; font-size:0.9rem; text-decoration:none; border-bottom: 2px solid {{ $activeTab === 'auto-approval' ? '#c2410c' : 'transparent' }}; color: {{ $activeTab === 'auto-approval' ? '#c2410c' : '#475569' }}; display:flex; align-items:center; gap:0.5rem; line-height:1.5;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        กฎอนุมัติอัตโนมัติ (Auto-Approve)
    </a>
</div>

@if(in_array($activeTab, ['privacy', 'api-keys', 'profile']))
    {{-- แท็บตั้งค่าข้อมูลส่วนตัวผู้ดูแล & รหัสผ่าน --}}
    <div style="display:flex; flex-direction:column; gap:1.5rem; max-width: 900px;">
        <form method="POST" action="{{ route('admin.profile.update') }}">
            @csrf
            @method('PATCH')

            {{-- 1. ข้อมูลส่วนตัว --}}
            <div class="card mb-6" style="border-radius:12px;">
                <div class="card-header" style="padding:1rem 1.5rem;">
                    <h3 class="font-semi" style="font-size:1rem;">ข้อมูลส่วนตัว</h3>
                </div>
                <div class="card-body" style="padding:1.5rem;">
                    <div class="grid-2 mb-4">
                        <div>
                            <label class="form-label">ชื่อ-นามสกุล (ภาษาไทย) <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" class="form-control" required>
                            @error('full_name') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">ชื่อ-นามสกุล (ภาษาอังกฤษ) <span class="text-danger">*</span></label>
                            <input type="text" name="english_name" value="{{ old('english_name', $user->english_name) }}" class="form-control" placeholder="เช่น John Doe" required>
                            @error('english_name') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- ตัวเลือกเพศ (Gender & Avatar Preview) --}}
                    <div class="mb-4">
                        <label class="form-label" style="font-weight:600; font-size:0.875rem; display:block; margin-bottom:0.5rem;">
                            เพศสภาพ
                        </label>
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem;">
                            {{-- เพศชาย --}}
                            <label style="cursor:pointer; display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border:2px solid {{ old('gender', $user->gender) === 'male' ? '#2563eb' : '#e2e8f0' }}; border-radius:10px; background: {{ old('gender', $user->gender) === 'male' ? '#eff6ff' : '#ffffff' }}; transition:all 0.2s;" id="genderLabelMale">
                                <input type="radio" name="gender" value="male" {{ old('gender', $user->gender) === 'male' ? 'checked' : '' }} style="margin:0;" onchange="updateGenderStyles()">
                                <x-avatar gender="male" size="36" />
                                <div>
                                    <div style="font-weight:700; font-size:0.875rem; color:#1e293b;">เพศชาย</div>
                                    <div style="font-size:0.75rem; color:#64748b;">Male Avatar</div>
                                </div>
                            </label>

                            {{-- เพศหญิง --}}
                            <label style="cursor:pointer; display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border:2px solid {{ old('gender', $user->gender) === 'female' ? '#ec4899' : '#e2e8f0' }}; border-radius:10px; background: {{ old('gender', $user->gender) === 'female' ? '#fdf2f8' : '#ffffff' }}; transition:all 0.2s;" id="genderLabelFemale">
                                <input type="radio" name="gender" value="female" {{ old('gender', $user->gender) === 'female' ? 'checked' : '' }} style="margin:0;" onchange="updateGenderStyles()">
                                <x-avatar gender="female" size="36" />
                                <div>
                                    <div style="font-weight:700; font-size:0.875rem; color:#1e293b;">เพศหญิง</div>
                                    <div style="font-size:0.75rem; color:#64748b;">Female Avatar</div>
                                </div>
                            </label>

                            {{-- อื่นๆ / ไม่ระบุ --}}
                            <label style="cursor:pointer; display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border:2px solid {{ in_array(old('gender', $user->gender), ['other', null, '']) ? '#ea580c' : '#e2e8f0' }}; border-radius:10px; background: {{ in_array(old('gender', $user->gender), ['other', null, '']) ? '#fff7ed' : '#ffffff' }}; transition:all 0.2s;" id="genderLabelOther">
                                <input type="radio" name="gender" value="other" {{ in_array(old('gender', $user->gender), ['other', null, '']) ? 'checked' : '' }} style="margin:0;" onchange="updateGenderStyles()">
                                <x-avatar gender="neutral" size="36" />
                                <div>
                                    <div style="font-weight:700; font-size:0.875rem; color:#1e293b;">อื่นๆ / ทั่วไป</div>
                                    <div style="font-size:0.75rem; color:#64748b;">Neutral Avatar</div>
                                </div>
                            </label>
                        </div>
                        @error('gender') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="grid-2 mb-4">
                        <div>
                            <label class="form-label">อีเมลติดต่อ <span class="text-danger">*</span></label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                            @error('email') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" placeholder="เช่น 081-xxx-xxxx">
                            @error('phone') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="grid-2 mb-4">
                        <div>
                            <label class="form-label">ตำแหน่ง</label>
                            <input type="text" name="position" value="{{ old('position', $user->position) }}" class="form-control" placeholder="เช่น นักวิชาการศึกษา">
                            @error('position') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">สังกัด / หน่วยงาน</label>
                            <input type="text" name="organization" value="{{ old('organization', $user->organization) }}" class="form-control" placeholder="เช่น สำนักวิทยบริการและเทคโนโลยีสารสนเทศ">
                            @error('organization') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. ความปลอดภัยและรหัสผ่าน --}}
            <div class="card mb-6" style="border-radius:12px;">
                <div class="card-header" style="padding:1rem 1.5rem;">
                    <div class="flex items-center gap-2">
                        <svg style="width:20px; height:20px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <h3 class="font-semi" style="font-size:1rem;">ความปลอดภัยและรหัสผ่าน</h3>
                    </div>
                    <p class="text-xs text-muted mt-1" style="font-weight:normal;">ปล่อยช่องรหัสผ่านใหม่ว่างไว้ หากไม่ต้องการเปลี่ยนแปลง</p>
                </div>
                <div class="card-body" style="padding:1.5rem;">
                    <div class="mb-4">
                        <label class="form-label">รหัสผ่านปัจจุบัน</label>
                        <input type="password" name="password_old" class="form-control" style="max-width:400px;" placeholder="กรุณากรอกรหัสผ่านเดิม หากต้องการแก้ไขรหัสผ่าน">
                        @error('password_old') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="grid-2">
                        <div>
                            <label class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" name="password" class="form-control" placeholder="ความยาวไม่น้อยกว่า 6 ตัวอักษร">
                            @error('password') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="password_confirmation" class="form-control" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. ลิงก์เชื่อมโยงไปยังการจัดการ API Keys --}}
            <div class="card mb-6" style="border-radius:12px; border:1px solid #e2e8f0; background:#f8fafc;">
                <div class="card-body" style="padding:1.25rem 1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
                    <div style="display:flex; align-items:center; gap:0.875rem;">
                        <div style="width:40px; height:40px; border-radius:10px; background:#eff6ff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <svg style="width:20px; height:20px; color:#2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 style="font-weight:700; font-size:0.925rem; color:#1e293b; margin:0;">จัดการ Personal Access Tokens & คีย์ API</h4>
                            <p style="font-size:0.8rem; color:#64748b; margin:0.2rem 0 0 0;">สร้างและเพิกถอน Token สำหรับเชื่อมต่อภายนอกหรือ Web API ได้ที่เมนูคีย์ API โดยเฉพาะ</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.api-keys.index') }}" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.85rem; font-weight:600; padding:0.5rem 1rem; border-radius:8px; text-decoration:none;">
                        <span>ไปยังหน้าจัดการคีย์ API</span>
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex justify-end gap-2 mt-4">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline">ยกเลิก</a>
                <button type="submit" class="btn btn-primary" style="background:#ea580c; color:white; border-radius:8px; font-weight:600; border:none; padding:0.6rem 1.5rem; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <svg style="width:16px; height:16px; margin-right:6px; display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
@elseif($activeTab === 'auto-approval')
    {{-- ═══ แท็บตั้งค่ากฎอนุมัติอัตโนมัติ (Smart Auto-Approval Rules) ═══ --}}
    <div style="display:flex; flex-direction:column; gap:1.5rem; max-width: 960px;">

        {{-- คำอธิบายฟีเจอร์และแนวคิด (Hero Banner) --}}
        <div class="card" style="border-radius:12px; border:1px solid #fed7aa; background:linear-gradient(135deg, rgba(255,247,237,0.8), rgba(254,243,199,0.5)); padding:1.25rem 1.5rem;">
            <div style="display:flex; align-items:flex-start; gap:1rem;">
                <div style="width:44px; height:44px; border-radius:10px; background:#ffedd5; color:#c2410c; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg style="width:24px; height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                        <h2 style="font-size:1.15rem; font-weight:700; color:#9a3412; margin:0;">ระบบกฎอนุมัติอัตโนมัติ (Smart Auto-Approval Rules)</h2>
                        <span style="font-size:0.75rem; font-weight:700; background:#ea580c; color:#fff; padding:2px 8px; border-radius:999px;">AI Powered</span>
                    </div>
                    <p style="font-size:0.875rem; color:#475569; margin:0.35rem 0 0 0; line-height:1.6;">
                        ช่วยลดภาระงานแอดมินในกิจกรรมที่มีคนเข้าร่วมหลักร้อยหลักพันคน โดยระบบจะตรวจสอบผลสแกนใบหน้า AI Confidence และระยะทาง GPS อัตโนมัติ หากข้อมูลผ่านเกณฑ์ความปลอดภัยครบถ้วน ระบบจะ<strong>อนุมัติและมอบชั่วโมงกิจกรรมทันที</strong> เฉพาะรายการที่มีความเสี่ยง (ใบหน้าไม่ชัดเจน หรือ GPS ไกลเกินเกณฑ์) ค่อยส่งเข้าคิว <strong>Approval Queue</strong> ให้แอดมินตรวจแบบ Manual
                    </p>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_section" value="auto_approval">
            <input type="hidden" name="tab" value="auto-approval">

            {{-- 1. การ์ดเปิด/ปิดสวิตช์หลัก --}}
            <div class="card mb-6" style="border-radius:12px;">
                <div class="card-header" style="padding:1rem 1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <svg style="width:20px; height:20px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <h3 class="font-semi" style="font-size:1rem; margin:0;">สถานะการทำงานของระบบกฎอนุมัติอัตโนมัติ</h3>
                    </div>
                    <span id="main-status-badge" style="font-size:0.8rem; font-weight:700; padding:3px 10px; border-radius:999px; {{ $settings['auto_approve_enabled'] ? 'background:#dcfce7; color:#15803d;' : 'background:#f1f5f9; color:#64748b;' }}">
                        {{ $settings['auto_approve_enabled'] ? 'เปิดใช้งาน (Active)' : 'ปิดใช้งาน (Disabled)' }}
                    </span>
                </div>
                <div class="card-body" style="padding:1.5rem;">
                    <label style="display:flex; align-items:center; gap:0.875rem; cursor:pointer; user-select:none;">
                        <input type="checkbox" name="auto_approve_enabled" id="auto_approve_enabled" value="1" {{ $settings['auto_approve_enabled'] ? 'checked' : '' }} style="width:20px; height:20px; accent-color:#ea580c; cursor:pointer;" onchange="updateAutoApproveToggleState()">
                        <div>
                            <span style="font-weight:700; font-size:0.95rem; color:var(--text-main, #0f172a);">เปิดใช้งานระบบกฎอนุมัติอัตโนมัติ (Smart Auto-Approval) ทั่วทั้งมหาวิทยาลัย</span>
                            <p style="font-size:0.8rem; color:#64748b; margin:2px 0 0 0;">หากปิดใช้งาน รายการเช็คอินที่กิจกรรมเปิดการขออนุมัติไว้ จะต้องรอแอดมินกดอนุมัติด้วยตนเองทั้งหมด 100%</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- 2. การ์ดกำหนดเกณฑ์ความแม่นยำ AI และระยะห่าง GPS --}}
            <div class="card mb-6" style="border-radius:12px;">
                <div class="card-header" style="padding:1rem 1.5rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <svg style="width:20px; height:20px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        <h3 class="font-semi" style="font-size:1rem; margin:0;">เกณฑ์การประเมินอนุมัติอัตโนมัติ (Decision Thresholds)</h3>
                    </div>
                    <p class="text-xs text-muted mt-1" style="font-weight:normal;">กำหนดค่าเกณฑ์ความเข้มงวดของระบบ หากนักศึกษาเช็คอินผ่านเกณฑ์เหล่านี้ ระบบจะอนุมัติทันที</p>
                </div>
                <div class="card-body" style="padding:1.5rem; display:flex; flex-direction:column; gap:1.75rem;">

                    {{-- เกณฑ์ 1: คะแนน AI Face Match --}}
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem; flex-wrap:wrap; gap:0.5rem;">
                            <label style="font-weight:700; font-size:0.9rem; color:var(--text-main, #0f172a); display:flex; align-items:center; gap:0.5rem;">
                                <svg style="width:18px; height:18px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                เกณฑ์ความเหมือนใบหน้า AI Face Match ขั้นต่ำ
                            </label>
                            <span style="font-size:0.85rem; font-weight:700; color:#c2410c; background:#ffedd5; padding:3px 10px; border-radius:6px;">
                                ต้องได้คะแนน &ge; <span id="face-score-display">{{ number_format($settings['auto_approve_min_face_score'], 0) }}</span>%
                            </span>
                        </div>
                        <p style="font-size:0.8rem; color:#64748b; margin-bottom:0.75rem;">
                            เมื่อนักศึกษาถ่ายภาพเซลฟี่ AI บนเซิร์ฟเวอร์จะเปรียบเทียบกับภาพโปรไฟล์ หากคะแนนความเหมือนถึงเกณฑ์นี้จะถือว่าผ่านการยืนยันตัวตน
                        </p>
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <input type="range" id="face-score-slider" min="50" max="100" step="1" value="{{ $settings['auto_approve_min_face_score'] }}" style="flex:1; accent-color:#ea580c; cursor:pointer;" oninput="syncFaceScore(this.value)">
                            <div style="display:flex; align-items:center; gap:4px; width:90px;">
                                <input type="number" name="auto_approve_min_face_score" id="face-score-input" min="50" max="100" step="0.5" value="{{ $settings['auto_approve_min_face_score'] }}" class="form-control" style="text-align:center; font-weight:700; padding:0.4rem;" oninput="syncFaceScore(this.value)" required>
                                <span style="font-weight:600; font-size:0.85rem; color:#475569;">%</span>
                            </div>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:#94a3b8; margin-top:4px;">
                            <span>50% (ผ่อนปรน)</span>
                            <span style="font-weight:700; color:#ea580c;">80% (ค่าแนะนำมาตรฐาน)</span>
                            <span>100% (เข้มงวดสูงสุด)</span>
                        </div>
                    </div>

                    <div style="height:1px; background:var(--border, #f1f5f9);"></div>

                    {{-- เกณฑ์ 2: ระยะห่าง GPS Geofence --}}
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem; flex-wrap:wrap; gap:0.5rem;">
                            <label style="font-weight:700; font-size:0.9rem; color:var(--text-main, #0f172a); display:flex; align-items:center; gap:0.5rem;">
                                <svg style="width:18px; height:18px; color:#0284c7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                เกณฑ์ระยะห่าง GPS จากจุดจัดกิจกรรมสูงสุด
                            </label>
                            <span style="font-size:0.85rem; font-weight:700; color:#0369a1; background:#e0f2fe; padding:3px 10px; border-radius:6px;">
                                ระยะห่างต้อง &le; <span id="distance-display">{{ number_format($settings['auto_approve_max_distance'], 0) }}</span> เมตร
                            </span>
                        </div>
                        <p style="font-size:0.8rem; color:#64748b; margin-bottom:0.75rem;">
                            ระยะห่างคำนวณจากพิกัด GPS อุปกรณ์ของนักศึกษาถึงจุดศูนย์กลางกิจกรรม หากระยะเกินเกณฑ์นี้จะถูกส่งเข้า Approval Queue ให้แอดมินพิจารณา
                        </p>
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <input type="range" id="distance-slider" min="5" max="300" step="5" value="{{ $settings['auto_approve_max_distance'] }}" style="flex:1; accent-color:#0284c7; cursor:pointer;" oninput="syncDistance(this.value)">
                            <div style="display:flex; align-items:center; gap:4px; width:95px;">
                                <input type="number" name="auto_approve_max_distance" id="distance-input" min="5" max="1000" step="1" value="{{ $settings['auto_approve_max_distance'] }}" class="form-control" style="text-align:center; font-weight:700; padding:0.4rem;" oninput="syncDistance(this.value)" required>
                                <span style="font-weight:600; font-size:0.85rem; color:#475569;">ม.</span>
                            </div>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:#94a3b8; margin-top:4px;">
                            <span>5 ม. (ระยะใกล้มาก)</span>
                            <span style="font-weight:700; color:#0284c7;">50 ม. (ค่าแนะนำครอบคลุมอาคาร)</span>
                            <span>300 ม. (ลานกว้าง/กลางแจ้ง)</span>
                        </div>
                    </div>

                    <div style="height:1px; background:var(--border, #f1f5f9);"></div>

                    {{-- เกณฑ์ 3: ความปลอดภัยขั้นสูง (Liveness & Device Anti-Fraud) --}}
                    <div>
                        <label style="font-weight:700; font-size:0.9rem; color:var(--text-main, #0f172a); display:block; margin-bottom:0.75rem;">
                            เงื่อนไขความปลอดภัยและป้องกันการทุจริตเพิ่มเติม
                        </label>
                        <div style="display:flex; flex-direction:column; gap:0.875rem;">
                            {{-- Liveness Detection --}}
                            <label style="display:flex; align-items:flex-start; gap:0.75rem; cursor:pointer; padding:0.75rem 1rem; border-radius:8px; border:1px solid var(--border, #e2e8f0); background:var(--surface, #f8fafc);">
                                <input type="checkbox" name="auto_approve_require_liveness" value="1" {{ $settings['auto_approve_require_liveness'] ? 'checked' : '' }} style="margin-top:3px; width:18px; height:18px; accent-color:#ea580c; cursor:pointer;">
                                <div>
                                    <div style="font-weight:700; font-size:0.875rem; color:var(--text-main, #0f172a);">บังคับผ่านการตรวจจับบุคคลจริง (AI Passive Liveness Anti-Spoofing)</div>
                                    <div style="font-size:0.775rem; color:#64748b; margin-top:2px;">
                                        ป้องกันการใช้รูปถ่าย, หน้าจอมือถือ, หรือภาพพิมพ์สแกนแทน หากระบบสงสัยว่าไม่ใช่บุคคลจริง จะไม่ได้รับการอนุมัติอัตโนมัติ
                                    </div>
                                </div>
                            </label>

                            {{-- Shared Device Protection --}}
                            <label style="display:flex; align-items:flex-start; gap:0.75rem; cursor:pointer; padding:0.75rem 1rem; border-radius:8px; border:1px solid var(--border, #e2e8f0); background:var(--surface, #f8fafc);">
                                <input type="checkbox" name="auto_approve_prevent_shared_device" value="1" {{ $settings['auto_approve_prevent_shared_device'] ? 'checked' : '' }} style="margin-top:3px; width:18px; height:18px; accent-color:#ea580c; cursor:pointer;">
                                <div>
                                    <div style="font-weight:700; font-size:0.875rem; color:var(--text-main, #0f172a);">ระงับการอนุมัติอัตโนมัติเมื่อตรวจพบการใช้อุปกรณ์ซ้ำหลายบัญชี (Device Fingerprint)</div>
                                    <div style="font-size:0.775rem; color:#64748b; margin-top:2px;">
                                        หากตรวจพบว่าเครื่องเดียวกันมีการล็อกอินเช็คอินให้นักศึกษาหลายคนพร้อมกัน จะส่งเข้าคิวให้แอดมินตรวจสอบเพื่อป้องกันการเช็คอินแทนกัน
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                </div>
            </div>

            {{-- 3. การ์ดจำลองตัวอย่างการทำงาน (Live Logic Simulation) --}}
            <div class="card mb-6" style="border-radius:12px; border:1px dashed #cbd5e1; background:var(--surface, #f8fafc);">
                <div class="card-header" style="padding:1rem 1.5rem; background:transparent;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <svg style="width:18px; height:18px; color:#64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h4 style="font-size:0.9rem; font-weight:700; color:#334155; margin:0;">ตัวอย่างการตัดสินใจของระบบตามเกณฑ์ปัจจุบัน</h4>
                    </div>
                </div>
                <div class="card-body" style="padding:0 1.5rem 1.5rem 1.5rem;">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
                        {{-- ตัวอย่างที่ 1: ผ่านเกณฑ์ (Auto-Approved) --}}
                        <div style="border-radius:10px; border:1px solid #bbf7d0; background:#f0fdf4; padding:1rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                                <span style="font-size:0.75rem; font-weight:700; color:#15803d; text-transform:uppercase;">กรณีผ่านเกณฑ์ครบถ้วน</span>
                                <span style="font-size:0.7rem; font-weight:700; background:#22c55e; color:#fff; padding:2px 8px; border-radius:999px;">อนุมัติทันที</span>
                            </div>
                            <div style="font-size:0.85rem; color:#166534; font-weight:600; line-height:1.5;">
                                AI ใบหน้า 88% (&ge; <span class="sim-face-val">{{ number_format($settings['auto_approve_min_face_score'], 0) }}</span>%)<br>
                                ระยะ GPS 18 ม. (&le; <span class="sim-dist-val">{{ number_format($settings['auto_approve_max_distance'], 0) }}</span> ม.)
                            </div>
                            <div style="font-size:0.75rem; color:#15803d; margin-top:6px; line-height:1.4;">
                                &bull; สถานะปรับเป็น "อนุมัติแล้ว" อัตโนมัติ<br>
                                &bull; มอบชั่วโมงกิจกรรมให้นักศึกษาทันที<br>
                                &bull; ไม่ต้องรอแอดมินกดอนุมัติในคิว
                            </div>
                        </div>

                        {{-- ตัวอย่างที่ 2: ติดความเสี่ยง (Held for Manual Review) --}}
                        <div style="border-radius:10px; border:1px solid #fde68a; background:#fffbeb; padding:1rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                                <span style="font-size:0.75rem; font-weight:700; color:#92400e; text-transform:uppercase;">กรณีพบความเสี่ยง</span>
                                <span style="font-size:0.7rem; font-weight:700; background:#d97706; color:#fff; padding:2px 8px; border-radius:999px;">ส่งเข้า Approval Queue</span>
                            </div>
                            <div style="font-size:0.85rem; color:#92400e; font-weight:600; line-height:1.5;">
                                AI ใบหน้า 62% หรือ ระยะ GPS 85 ม.<br>
                                หรือตรวจพบลักษณะอุปกรณ์ซ้ำ
                            </div>
                            <div style="font-size:0.75rem; color:#b45309; margin-top:6px; line-height:1.4;">
                                &bull; สถานะเป็น "รออนุมัติ (pending)"<br>
                                &bull; ส่งเข้า Approval Queue บนแดชบอร์ด<br>
                                &bull; แสดง Risk Badge ให้แอดมินตรวจสอบเฉพาะเคสนี้
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ปุ่มบันทึกการตั้งค่า --}}
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline" style="border-radius:8px;">ยกเลิก</a>
                <button type="submit" class="btn btn-primary" style="background:#ea580c; color:white; border-radius:8px; font-weight:600; border:none; padding:0.65rem 1.75rem; box-shadow:0 2px 4px rgba(234,88,12,0.2); display:inline-flex; align-items:center; gap:0.5rem;">
                    <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>บันทึกกฎอนุมัติอัตโนมัติ</span>
                </button>
            </div>
        </form>
    </div>

    <script>
    function syncFaceScore(val) {
        val = parseFloat(val) || 80;
        document.getElementById('face-score-slider').value = val;
        document.getElementById('face-score-input').value = val;
        document.getElementById('face-score-display').textContent = Math.round(val);
        document.querySelectorAll('.sim-face-val').forEach(el => el.textContent = Math.round(val));
    }

    function syncDistance(val) {
        val = parseFloat(val) || 50;
        document.getElementById('distance-slider').value = val;
        document.getElementById('distance-input').value = val;
        document.getElementById('distance-display').textContent = Math.round(val);
        document.querySelectorAll('.sim-dist-val').forEach(el => el.textContent = Math.round(val));
    }

    function updateAutoApproveToggleState() {
        const checked = document.getElementById('auto_approve_enabled').checked;
        const badge = document.getElementById('main-status-badge');
        if (checked) {
            badge.textContent = 'เปิดใช้งาน (Active)';
            badge.style.background = '#dcfce7';
            badge.style.color = '#15803d';
        } else {
            badge.textContent = 'ปิดใช้งาน (Disabled)';
            badge.style.background = '#f1f5f9';
            badge.style.color = '#64748b';
        }
    }
    </script>
@else
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
    
    {{-- ═══ คอลัมน์ซ้าย: การฟอร์แมตอีเมล SSO (2 ส่วนบนหน้าจอใหญ่) ═══ --}}
    <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 1.5rem;">
        
        <div class="card" style="box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-radius:12px;">
            <div class="card-header" style="padding:1.25rem 1.5rem;">
                <h3 class="font-semi flex items-center gap-2" style="font-size:1.05rem; margin:0;">
                    <svg style="width:20px; height:20px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    รูปแบบอีเมลนักศึกษาอัตโนมัติ (SSO Sync)
                </h3>
            </div>
            <div class="card-body" style="padding:1.5rem;">
                <p class="text-sm text-muted mb-6" style="line-height:1.5;">
                    เมื่อนักศึกษาเข้าสู่ระบบครั้งแรกผ่านระบบล็อกอินส่วนกลาง (SSO) ระบบจะดึงรหัสนักศึกษาและสร้างอีเมลขึ้นมาโดยอัตโนมัติตามรูปแบบที่ระบุด้านล่าง เพื่ออำนวยความสะดวกในการจัดส่งเอกสารและข้อมูลกิจกรรม
                </p>

                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-bottom:1.5rem;">
                        <div>
                            <label class="form-label" style="font-weight:600; color:#334155; margin-bottom:0.5rem; display:block;">คำนำหน้าอีเมล (Prefix)</label>
                            <input type="text" name="student_email_prefix" value="{{ old('student_email_prefix', $settings['student_email_prefix']) }}" class="form-control" style="width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 8px; transition: border-color 0.2s;" placeholder="เช่น s (ปล่อยว่างได้)">
                            @error('student_email_prefix')
                                <p class="text-xs mt-1" style="color: #ef4444; font-weight:500;">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-muted mt-1.5" style="line-height:1.5; color:#475569;">ตัวอักษรที่จะนำหน้ารหัสนักศึกษา (เช่น ใส่ <code style="background:#f1f5f9; padding:2px 4px; border-radius:4px; font-weight:600;">s</code> จะได้ s6710886217...)</p>
                        </div>

                        <div>
                            <label class="form-label" style="font-weight:600; color:#334155; margin-bottom:0.5rem; display:block;">โดเมนอีเมลสถาบัน (Domain) <span class="text-danger">*</span></label>
                            <input type="text" name="student_email_domain" value="{{ old('student_email_domain', $settings['student_email_domain']) }}" class="form-control" style="width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 8px; transition: border-color 0.2s;" placeholder="เช่น @pkru.ac.th" required>
                            @error('student_email_domain')
                                <p class="text-xs mt-1" style="color: #ef4444; font-weight:500;">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-muted mt-1.5" style="line-height:1.5; color:#475569;">โดเมนอีเมลของมหาวิทยาลัย ต้องขึ้นต้นด้วยเครื่องหมาย <code style="background:#f1f5f9; padding:2px 4px; border-radius:4px; font-weight:600;">@</code> เสมอ</p>
                        </div>
                    </div>

                    {{-- Live Preview Box --}}
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; display:flex; flex-direction:column; gap:6px;">
                        <span class="text-xs font-semi text-muted" style="text-transform:uppercase; letter-spacing:0.05em; color:#475569;">ตัวอย่างอีเมลที่ได้จริง:</span>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <code class="font-mono text-sm" id="email-preview" style="color: #c2410c; background: #fff; padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0; display:inline-block; font-weight:700; box-shadow: 0 1px 2px rgba(0,0,0,0.02); letter-spacing:-0.01em;"></code>
                            <span style="font-size:0.75rem; color:#475569; line-height:1.5;">(สมมติรหัส นศ. 6710886217)</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline" style="border-radius:8px;">ยกเลิก</a>
                        <button type="submit" class="btn btn-primary" style="background:#ea580c; color:white; border-radius:8px; font-weight:600; border:none; padding:0.6rem 1.5rem; box-shadow:0 2px 4px rgba(234,88,12,0.2);">
                            บันทึกการตั้งค่า
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    {{-- ═══ คอลัมน์ขวา: LINE OA Integration และ Diagnostics (1 ส่วนบนหน้าจอใหญ่) ═══ --}}
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        {{-- การ์ด LINE Bot Integration --}}
        <div class="card" style="box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-radius:12px;">
            <div class="card-header flex items-center gap-2" style="padding:1rem 1.25rem;">
                <svg style="width:20px; height:20px; color:#06c755;" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 10.3c0-4.8-5.4-8.8-12-8.8S0 5.5 0 10.3c0 4.3 4.3 7.9 10.1 8.7.4.1.9.3 1 .7.1.3.1.8 0 1.1l-.4 1.7c-.1.4-.4 1.7 1.1.9s8.1-4.8 11-8.2c.8-1.2 1.2-2.8 1.2-4.2zm-16.7 2H5.7c-.3 0-.5-.2-.5-.5v-4c0-.3.2-.5.5-.5h1.6c.3 0 .5.2.5.5v3.5h.5c.3 0 .5.2.5.5s-.2.5-.5.5zm3.7 0c0 .3-.2.5-.5.5h-1.6c-.3 0-.5-.2-.5-.5v-4c0-.3.2-.5.5-.5h1.6c.3 0 .5.2.5.5s-.2.5-.5.5h-1.1v1h1.1c.3 0 .5.2.5.5s-.2.5-.5.5h-1.1v1h1.1c.3 0 .5.2.5.5s-.2.5-.5.5zm4.8 0c0 .3-.2.5-.5.5h-1.6c-.3 0-.5-.2-.5-.5v-4c0-.3.2-.5.5-.5h.5c.3 0 .5.2.5.5v2.8l1-2.9c.1-.2.3-.4.5-.4h.6c.4 0 .6.4.4.7l-1.3 3c-.1.2-.2.3-.4.3zm5 0c0 .3-.2.5-.5.5h-1.6c-.3 0-.5-.2-.5-.5v-4c0-.3.2-.5.5-.5h1.6c.3 0 .5.2.5.5s-.2.5-.5.5H19v1h1.1c.3 0 .5.2.5.5s-.2.5-.5.5H19v1h1.1c.3 0 .5.2.5.5s-.2.5-.5.5z"/>
                </svg>
                <span class="font-semi text-sm" style="color:#334155;">LINE OA Integration</span>
            </div>
            <div class="card-body" style="padding:1.25rem; display:flex; flex-direction:column; gap:1rem;">
                <div class="flex justify-between items-center" style="background:#f0fdf4; border:1px solid #bbf7d0; padding:8px 12px; border-radius:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="display:block; width:8px; height:8px; border-radius:50%; background:#22c55e; box-shadow:0 0 8px #22c55e; animation: pulse 2s infinite;"></span>
                        <span class="text-xs font-semi" style="color:#14532d;">บอท LINE OA ออนไลน์</span>
                    </div>
                    <span class="text-xs font-bold" style="color:#15803d; background:#dcfce7; padding:2px 6px; border-radius:4px;">Online</span>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px; border-top:1px solid #f1f5f9; padding-top:0.75rem;">
                    <div>
                        <span class="text-xs text-muted" style="display:block; margin-bottom:2px;">ไอดีบอท:</span>
                        <span class="text-sm font-semi" style="color:#334155;">@436quwjw (ระบบกิจกรรม มหาลัย)</span>
                    </div>
                    <div>
                        <span class="text-xs text-muted" style="display:block; margin-bottom:2px;">Webhook Auto-Update:</span>
                        <span class="text-xs font-semi" style="color:#0369a1; background:#e0f2fe; padding:2px 6px; border-radius:4px; display:inline-block; max-width:100%; word-break:break-all; line-height:1.5;">เปิดใช้งานระบบซิงก์ออโต้แล้ว</span>
                    </div>
                    <p class="text-xs text-muted" style="line-height:1.5; margin:0; padding-top:4px; color:#475569;">
                        Webhook URL และไฟล์ดีดทางหน้าเพจ (Redirect Json Proxy) จะถูกปรับแต่งและอัปเดตแบบเรียลไทม์เมื่อเซิร์ฟเวอร์เปิดใช้งานอุโมงค์ Cloudflare Tunnel โดยอัตโนมัติ
                    </p>
                </div>
            </div>
        </div>

        {{-- การ์ด Diagnostics --}}
        <div class="card" style="box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-radius:12px;">
            <div class="card-header flex items-center gap-2" style="padding:1rem 1.25rem;">
                <svg style="width:20px; height:20px; color:#475569;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="font-semi text-sm" style="color:#334155;">ข้อมูลสถานะเซิร์ฟเวอร์</span>
            </div>
            <div class="card-body" style="padding:1.25rem; display:flex; flex-direction:column; gap:0.75rem;">
                <div class="flex justify-between items-center text-sm" style="border-bottom: 1px solid #f8fafc; padding-bottom: 6px;">
                    <span class="text-muted text-xs">Laravel Version</span>
                    <span class="font-mono font-semi" style="color:#334155;">v{{ app()->version() }}</span>
                </div>
                <div class="flex justify-between items-center text-sm" style="border-bottom: 1px solid #f8fafc; padding-bottom: 6px;">
                    <span class="text-muted text-xs">PHP Version</span>
                    <span class="font-mono font-semi" style="color:#334155;">v{{ PHP_VERSION }}</span>
                </div>
                <div class="flex justify-between items-center text-sm" style="border-bottom: 1px solid #f8fafc; padding-bottom: 6px;">
                    <span class="text-muted text-xs">Active Environment</span>
                    <span class="font-semi text-xs" style="color:#475569; background:#f1f5f9; padding:2px 6px; border-radius:4px; text-transform:uppercase;">{{ app()->environment() }}</span>
                </div>
                <div class="flex justify-between items-center text-sm" style="border-bottom: 1px solid #f8fafc; padding-bottom: 6px;">
                    <span class="text-muted text-xs">Debug Status</span>
                    <span class="font-semi text-xs" style="color:{{ config('app.debug') ? '#d97706' : '#475569' }}; background:{{ config('app.debug') ? '#fffbeb' : '#f8fafc' }}; padding:2px 6px; border-radius:4px; line-height:1.5;">{{ config('app.debug') ? 'เปิด (True)' : 'ปิด (False)' }}</span>
                </div>
                <div class="flex justify-between items-center text-sm" style="border-bottom: 1px solid #f8fafc; padding-bottom: 6px;">
                    <span class="text-muted text-xs">Database Connection</span>
                    <span class="font-mono font-semi" style="color:#334155; text-transform:uppercase;">{{ config('database.default') }}</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-muted text-xs">Queue Driver</span>
                    <span class="font-mono font-semi" style="color:#ea580c; text-transform:uppercase;">{{ config('queue.default') }}</span>
                </div>
            </div>
        </div>

    </div>
</div>
@endif

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: .4; transform: scale(1.15); }
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const prefixInput = document.querySelector('input[name="student_email_prefix"]');
        const domainInput = document.querySelector('input[name="student_email_domain"]');
        const preview = document.getElementById('email-preview');

        function updatePreview() {
            const prefix = prefixInput.value.trim();
            const domain = domainInput.value.trim();
            preview.textContent = `${prefix}6710886217${domain}`;
        }

        prefixInput.addEventListener('input', updatePreview);
        domainInput.addEventListener('input', updatePreview);
        
        // Initial preview
        if (prefixInput && domainInput && preview) {
            updatePreview();
        }
    });

    function updateGenderStyles() {
        const selected = document.querySelector('input[name="gender"]:checked')?.value;
        const maleLabel = document.getElementById('genderLabelMale');
        const femaleLabel = document.getElementById('genderLabelFemale');
        const otherLabel = document.getElementById('genderLabelOther');
        if (!maleLabel || !femaleLabel || !otherLabel) return;

        maleLabel.style.borderColor = selected === 'male' ? '#2563eb' : '#e2e8f0';
        maleLabel.style.background = selected === 'male' ? '#eff6ff' : '#ffffff';

        femaleLabel.style.borderColor = selected === 'female' ? '#ec4899' : '#e2e8f0';
        femaleLabel.style.background = selected === 'female' ? '#fdf2f8' : '#ffffff';

        otherLabel.style.borderColor = (selected === 'other' || !selected) ? '#ea580c' : '#e2e8f0';
        otherLabel.style.background = (selected === 'other' || !selected) ? '#fff7ed' : '#ffffff';
    }
    window.updateGenderStyles = updateGenderStyles;
</script>
@endsection
