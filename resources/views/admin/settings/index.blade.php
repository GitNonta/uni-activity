@extends('layouts.admin')
@section('title', 'ตั้งค่าระบบ')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="font-bold flex items-center gap-3" style="font-size:1.5rem; color:#1e293b;">
            <svg style="width:28px; height:28px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            ตั้งค่าระบบ
        </h1>
        <p class="text-sm text-muted mt-1">จัดการพารามิเตอร์ของระบบ การเชื่อมต่อ LINE OA และข้อมูลความสมบูรณ์ของเซิร์ฟเวอร์</p>
    </div>
</div>

{{-- Navigation Tabs for Settings & Privacy --}}
<div style="display:flex; gap:0.5rem; border-bottom:1px solid #e2e8f0; margin-bottom:1.5rem;">
    <a href="{{ route('admin.settings.index', ['tab' => 'privacy']) }}" style="padding:0.75rem 1.25rem; font-weight:600; font-size:0.9rem; text-decoration:none; border-bottom: 2px solid {{ in_array($activeTab, ['privacy', 'profile']) ? '#ea580c' : 'transparent' }}; color: {{ in_array($activeTab, ['privacy', 'profile']) ? '#ea580c' : '#64748b' }}; display:flex; align-items:center; gap:0.5rem;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        ตั้งค่าความเป็นส่วนตัว & ข้อมูลส่วนตัว
    </a>
    <a href="{{ route('admin.settings.index', ['tab' => 'general']) }}" style="padding:0.75rem 1.25rem; font-weight:600; font-size:0.9rem; text-decoration:none; border-bottom: 2px solid {{ $activeTab === 'general' ? '#ea580c' : 'transparent' }}; color: {{ $activeTab === 'general' ? '#ea580c' : '#64748b' }}; display:flex; align-items:center; gap:0.5rem;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        ตั้งค่าทั่วไป & SSO
    </a>
</div>

@if(in_array($activeTab, ['privacy', 'api-keys', 'profile']))
    {{-- 🔒 แท็บตั้งค่าความเป็นส่วนตัว & ข้อมูลส่วนตัว (ฟอร์มที่ย้ายมาจากโปรไฟล์) --}}
    <div style="display:flex; flex-direction:column; gap:1.5rem; max-width: 900px;">
        <form method="POST" action="{{ route('admin.profile.update') }}">
            @csrf
            @method('PATCH')

            {{-- 1. ข้อมูลส่วนตัว --}}
            <div class="card mb-6" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border-radius:12px; background:#fff;">
                <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1rem 1.5rem;">
                    <h3 class="font-semi" style="font-size:1rem; color:#1e293b;">ข้อมูลส่วนตัว</h3>
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
            <div class="card mb-6" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border-radius:12px; background:#fff;">
                <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1rem 1.5rem;">
                    <div class="flex items-center gap-2">
                        <svg style="width:20px; height:20px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <h3 class="font-semi" style="font-size:1rem; color:#1e293b;">ความปลอดภัยและรหัสผ่าน</h3>
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

            {{-- 3. API Keys & Tokens (ตั้งค่าความเป็นส่วนตัว) --}}
            <div class="card mb-6" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border-radius:12px; background:#fff;">
                <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1rem 1.5rem;">
                    <div class="flex items-center gap-2">
                        <svg style="width:20px; height:20px; color:#059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        <div>
                            <h3 class="font-semi" style="font-size:1rem; color:#1e293b;">API Keys & Tokens (ตั้งค่าความเป็นส่วนตัว)</h3>
                            <p class="text-xs text-muted mt-0.5" style="font-weight:normal;">จัดการ Personal Access Token สำหรับการเชื่อมต่อแอปพลิเคชันหรือระบบภายนอกอย่างปลอดภัย</p>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="padding:1.5rem;">
                    @if(session('new_token'))
                        <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:1rem; margin-bottom:1.25rem;">
                            <div style="font-weight:700; color:#065f46; font-size:0.9rem; margin-bottom:0.35rem;">🔑 Token ใหม่ถูกสร้างเรียบร้อยแล้ว:</div>
                            <div style="font-family:monospace; background:#ffffff; padding:0.6rem 0.8rem; border-radius:6px; border:1px solid #6ee7b7; color:#047857; font-size:0.85rem; word-break:break-all;">
                                {{ session('new_token') }}
                            </div>
                            <p style="font-size:0.75rem; color:#047857; margin-top:0.35rem; font-weight:500;">⚠️ กรุณาคัดลอก Token นี้เก็บไว้ทันที เพราะระบบจะแสดงเพียงครั้งเดียวเพื่อความปลอดภัย</p>
                        </div>
                    @endif

                    <div style="overflow-x:auto;">
                        <table class="table" style="width:100%; font-size:0.85rem; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left;">
                                    <th style="padding:0.6rem 0.75rem; color:#475569; font-weight:600;">ชื่อ Token</th>
                                    <th style="padding:0.6rem 0.75rem; color:#475569; font-weight:600;">ใช้งานล่าสุด</th>
                                    <th style="padding:0.6rem 0.75rem; color:#475569; font-weight:600;">วันที่สร้าง</th>
                                    <th style="padding:0.6rem 0.75rem; color:#475569; font-weight:600; text-align:right;">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tokens ?? [] as $token)
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:0.65rem 0.75rem; font-weight:600; color:#1e293b;">{{ $token->name }}</td>
                                        <td style="padding:0.65rem 0.75rem; color:#64748b;">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'ยังไม่เคยใช้งาน' }}</td>
                                        <td style="padding:0.65rem 0.75rem; color:#64748b;">{{ $token->created_at ? $token->created_at->format('d/m/Y H:i') : '-' }}</td>
                                        <td style="padding:0.65rem 0.75rem; text-align:right;">
                                            <button type="button" onclick="event.preventDefault(); if(confirm('ยืนยันลบ API Key นี้?')) document.getElementById('delete-token-{{ $token->id }}').submit();" style="background:none; border:none; color:#ef4444; font-size:0.8rem; cursor:pointer; font-weight:500;">
                                                ลบ Token
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align:center; padding:1.5rem; color:#94a3b8; font-size:0.85rem;">ยังไม่มีการสร้าง API Key ในระบบ</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex justify-end gap-2 mt-4">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline" style="background:#fff;">ยกเลิก</a>
                <button type="submit" class="btn btn-primary" style="background:#ea580c; color:white; border-radius:8px; font-weight:600; border:none; padding:0.6rem 1.5rem; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <svg style="width:16px; height:16px; margin-right:6px; display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    บันทึกข้อมูล
                </button>
            </div>
        </form>
        @foreach($tokens ?? [] as $token)
            <form id="delete-token-{{ $token->id }}" action="{{ route('admin.api-keys.destroy', $token->id) }}" method="POST" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </div>
@else
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
    
    {{-- ═══ คอลัมน์ซ้าย: การฟอร์แมตอีเมล SSO (2 ส่วนบนหน้าจอใหญ่) ═══ --}}
    <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 1.5rem;">
        
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); background:#fff; border-radius:12px;">
            <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1.25rem 1.5rem;">
                <h3 class="font-semi flex items-center gap-2" style="font-size:1.05rem; color:#1e293b; margin:0;">
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
                            <p class="text-xs text-muted mt-1.5" style="line-height:1.4;">ตัวอักษรที่จะนำหน้ารหัสนักศึกษา (เช่น ใส่ <code style="background:#f1f5f9; padding:2px 4px; border-radius:4px; font-weight:600;">s</code> จะได้ s6710886217...)</p>
                        </div>

                        <div>
                            <label class="form-label" style="font-weight:600; color:#334155; margin-bottom:0.5rem; display:block;">โดเมนอีเมลสถาบัน (Domain) <span class="text-danger">*</span></label>
                            <input type="text" name="student_email_domain" value="{{ old('student_email_domain', $settings['student_email_domain']) }}" class="form-control" style="width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 8px; transition: border-color 0.2s;" placeholder="เช่น @pkru.ac.th" required>
                            @error('student_email_domain')
                                <p class="text-xs mt-1" style="color: #ef4444; font-weight:500;">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-muted mt-1.5" style="line-height:1.4;">โดเมนอีเมลของมหาวิทยาลัย ต้องขึ้นต้นด้วยเครื่องหมาย <code style="background:#f1f5f9; padding:2px 4px; border-radius:4px; font-weight:600;">@</code> เสมอ</p>
                        </div>
                    </div>

                    {{-- Live Preview Box --}}
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; display:flex; flex-direction:column; gap:6px;">
                        <span class="text-xs font-semi text-muted" style="text-transform:uppercase; letter-spacing:0.05em;">ตัวอย่างอีเมลที่ได้จริง:</span>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <code class="font-mono text-sm" id="email-preview" style="color: #ea580c; background: #fff; padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0; display:inline-block; font-weight:700; box-shadow: 0 1px 2px rgba(0,0,0,0.02); letter-spacing:-0.01em;"></code>
                            <span style="font-size:0.75rem; color:#64748b;">(สมมติรหัส นศ. 6710886217)</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline" style="background:#fff; border-radius:8px;">ยกเลิก</a>
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
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
            <div class="card-header flex items-center gap-2" style="background:#f8fafc; border-bottom:1px solid #f1f5f9; padding:1rem 1.25rem;">
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
                        <span class="text-xs font-semi" style="color:#0284c7; background:#e0f2fe; padding:2px 6px; border-radius:4px; display:inline-block; max-width:100%; word-break:break-all;">เปิดใช้งานระบบซิงก์ออโต้แล้ว</span>
                    </div>
                    <p class="text-xs text-muted" style="line-height:1.4; margin:0; padding-top:4px;">
                        Webhook URL และไฟล์ดีดทางหน้าเพจ (Redirect Json Proxy) จะถูกปรับแต่งและอัปเดตแบบเรียลไทม์เมื่อเซิร์ฟเวอร์เปิดใช้งานอุโมงค์ Cloudflare Tunnel โดยอัตโนมัติ
                    </p>
                </div>
            </div>
        </div>

        {{-- การ์ด Diagnostics --}}
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
            <div class="card-header flex items-center gap-2" style="background:#f8fafc; border-bottom:1px solid #f1f5f9; padding:1rem 1.25rem;">
                <svg style="width:20px; height:20px; color:#64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <span class="font-semi text-xs" style="color:{{ config('app.debug') ? '#d97706' : '#64748b' }}; background:{{ config('app.debug') ? '#fffbeb' : '#f8fafc' }}; padding:2px 6px; border-radius:4px;">{{ config('app.debug') ? 'เปิด (True)' : 'ปิด (False)' }}</span>
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
        updatePreview();
    });
</script>
@endsection
