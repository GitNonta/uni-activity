{{-- หน้ารายละเอียดกิจกรรม (Admin): ข้อมูล, สถิติ, toggle เช็คอินก่อนเวลา, QR URL --}}
@extends('layouts.admin')
@section('title', $activity->title)

@section('content')
<a href="{{ route('admin.activities.index') }}" class="text-sm text-primary">&larr; กลับ</a>

{{-- การ์ดข้อมูลกิจกรรม --}}
<div class="card mt-2 mb-4">
    <div class="card-body">
        {{-- หัวข้อ: ชื่อ + สถานะ + ปุ่มจัดการ (แก้ไข/ผู้เข้าร่วม/เช็คอิน) --}}
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:.5rem;">
            <div>
                <h1 class="font-bold" style="font-size:1.25rem;">{{ $activity->title }}</h1>
                <div class="flex gap-2 mt-1" style="flex-wrap:wrap;">
                    @include('components.status-badge', ['status' => $activity->computed_status])
                    @if($activity->is_mandatory)<span class="badge badge-red">บังคับ</span>@endif
                    @if($activity->category)<span class="badge badge-blue">{{ $activity->category->name }}</span>@endif
                    @if($activity->scope === 'faculty')
                        <span class="badge" style="background:#fef3c7;color:#92400e;">คณะ: {{ $activity->faculty }}</span>
                    @elseif($activity->scope === 'department')
                        <span class="badge" style="background:#ede9fe;color:#5b21b6;">สาขา: {{ $activity->department }} ({{ $activity->faculty }})</span>
                    @else
                        <span class="badge" style="background:#e0f2fe;color:#0369a1;">ระดับมหาวิทยาลัย</span>
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.activities.edit', $activity->id) }}" class="btn btn-outline btn-sm">แก้ไข</a>
                <a href="{{ route('admin.activities.participants', $activity->id) }}" class="btn btn-outline btn-sm">ผู้เข้าร่วม</a>
                <a href="{{ route('admin.activities.scanner', $activity->id) }}" class="btn btn-primary btn-sm">สแกนนักศึกษา</a>
                <a href="{{ route('admin.activities.checkin', $activity->id) }}" class="btn btn-success btn-sm">มอนิเตอร์</a>
            </div>
        </div>

        @if($activity->description)
            <p class="text-muted text-sm mt-4">{{ $activity->description }}</p>
        @endif

        {{-- การ์ดข้อมูล: วันที่, เวลา, สถานที่, ชั่วโมง --}}
        <div class="grid-4 mt-4" style="font-size:.875rem;">
            <div class="card stat-card">
                <p class="stat-label">วันที่</p>
                <p class="font-semi">{{ $activity->activity_date->format('d/m/Y') }}</p>
            </div>
            <div class="card stat-card">
                <p class="stat-label">เวลา</p>
                <p class="font-semi">{{ \Carbon\Carbon::parse($activity->start_time)->format('H:i') }}-{{ \Carbon\Carbon::parse($activity->end_time)->format('H:i') }}</p>
            </div>
            <div class="card stat-card">
                <p class="stat-label">สถานที่</p>
                <p class="font-semi">{{ $activity->location ?? '-' }}</p>
            </div>
            <div class="card stat-card">
                <p class="stat-label">ชั่วโมง</p>
                <p class="font-semi">{{ $activity->activity_hours }} ชม.</p>
            </div>
        </div>

        {{-- สถิติผู้ลงทะเบียนและเช็คอิน --}}
        @php
            $approvedCount = $activity->attendances->where('status', 'approved')->count();
            $pendingCount = $activity->attendances->where('status', 'pending')->count();
        @endphp
        <div class="grid-2 mt-4" style="font-size:.875rem;">
            <div class="card stat-card">
                <p class="stat-label">ผู้ลงทะเบียน</p>
                <p class="font-bold" style="font-size:1.25rem;">{{ $activity->getRegisteredCount() }}/{{ $activity->max_participants }}</p>
            </div>
            <div class="card stat-card">
                <p class="stat-label">เช็คอิน (อนุมัติ / รออนุมัติ)</p>
                <p class="font-bold text-success" style="font-size:1.25rem;">{{ $approvedCount }} คน
                    @if($pendingCount > 0)
                        <span style="font-size:.85rem;color:#d97706;">(รอ {{ $pendingCount }})</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- แผนที่พิกัดสถานที่ (ถ้ามี) --}}
        @if($activity->hasGeolocation())
        <div class="mt-4">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semi text-sm">พิกัดสถานที่จัดกิจกรรม</p>
                <span class="text-xs text-muted">{{ $activity->latitude }}, {{ $activity->longitude }} | รัศมี {{ $activity->checkin_radius }}m</span>
            </div>
            <div id="showMap" style="height:280px;border-radius:8px;border:1px solid #e2e8f0;z-index:0;"></div>
        </div>
        @else
        <div class="mt-2" style="font-size:.875rem;">
            <div class="card stat-card" style="background:#fef3c7;">
                <p class="text-sm" style="color:#92400e;">ยังไม่ได้ตั้งค่าพิกัดสถานที่ — นักศึกษาบันทึกกิจกรรมจะต้องรอผู้จัดอนุมัติทุกครั้ง</p>
            </div>
        </div>
        @endif

        {{-- Toggle เปิด/ปิด เช็คอินก่อนเวลา --}}
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0;">
            <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:.75rem;">
                <div>
                    <p class="font-semi text-sm">อนุญาตบันทึกกิจกรรมก่อนเวลา</p>
                    <p class="text-xs text-muted">เปิดให้นักศึกษาบันทึกกิจกรรมได้ทันที ไม่ต้องรอถึงเวลาเช็คอิน</p>
                </div>
                <form method="POST" action="{{ route('admin.activities.toggle-early-checkin', $activity->id) }}">
                    @csrf
                    <label class="toggle-wrap">
                        <label class="toggle">
                            <input type="checkbox" onchange="this.form.submit()" {{ $activity->allow_early_checkin ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="text-sm font-semi {{ $activity->allow_early_checkin ? 'text-success' : 'text-muted' }}">
                            {{ $activity->allow_early_checkin ? 'เปิดอยู่' : 'ปิดอยู่' }}
                        </span>
                    </label>
                </form>
            </div>
        </div>

        {{-- Walk-in Check-in URL --}}
        @if($activity->qr_token)
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0;">
            <p class="text-sm text-muted mb-1">Walk-in Check-in URL (กรอกรหัสนักศึกษา ไม่ต้องลงทะเบียนล่วงหน้า):</p>
            <div class="flex items-center gap-2" style="flex-wrap:wrap;">
                <code id="walkin-url" class="text-sm" style="background:#fef3c7;padding:.375rem .75rem;border-radius:6px;display:block;word-break:break-all;flex:1;">{{ url('/walkin/' . $activity->qr_token) }}</code>
                <button onclick="copyWalkInUrl()" class="btn btn-sm btn-outline" style="white-space:nowrap;" title="คัดลอกลิงก์">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-right: 4px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    คัดลอก
                </button>
                <button onclick="showQRModal()" class="btn btn-sm btn-outline" style="white-space:nowrap;" title="แสดง QR Code">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-right: 4px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    QR Code
                </button>
                <a href="{{ route('checkin.walkin', $activity->qr_token) }}" target="_blank" class="btn btn-sm" style="background:#f59e0b;color:#fff;white-space:nowrap;">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-right: 4px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    เปิดหน้า
                </a>
            </div>
            
            @if($activity->qr_expires_at)
            <div class="mt-2 text-sm" style="color:#ef4444;font-weight:600;">
                * QR Code หมดอายุ: {{ \Carbon\Carbon::parse($activity->qr_expires_at)->format('d/m/Y H:i') }}
            </div>
            @endif
            
            <div class="mt-4 p-3 bg-gray-50 rounded" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                <p class="font-semi text-sm mb-2" style="color:#334155;">สร้าง QR Code ใหม่ (Regenerate QR)</p>
                <form method="POST" action="{{ route('admin.activities.regenerate-qr', $activity->id) }}" onsubmit="return confirm('ยืนยันสร้าง QR Code ใหม่? ลิงก์และ QR Code เดิมจะไม่สามารถใช้งานได้อีก')">
                    @csrf
                    <div class="flex items-center gap-2" style="flex-wrap:wrap;">
                        <select name="expires_in_hours" class="form-control form-control-sm" style="width:auto;font-size:0.875rem;padding:0.25rem 0.5rem;">
                            <option value="">-- ไม่จำกัดเวลา (ไม่หมดอายุ) --</option>
                            <option value="1">1 ชั่วโมง</option>
                            <option value="6">6 ชั่วโมง</option>
                            <option value="24">24 ชั่วโมง (1 วัน)</option>
                        </select>
                        <button type="submit" class="btn btn-sm" style="background:#ef4444;color:#fff;">
                            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-right: 4px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            สร้าง QR Code ใหม่
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- QR Code Modal --}}
        <div id="qrModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;">
            <div style="background:white;padding:2rem;border-radius:12px;max-width:400px;width:90%;text-align:center;">
                <h3 style="margin-bottom:1rem;color:#1f2937;">QR Code สำหรับ Walk-in Check-in</h3>
                <div id="qr-code" style="margin:1rem 0;"></div>
                <p style="font-size:0.9rem;color:#6b7280;margin-bottom:1.5rem;">สแกน QR Code เพื่อเข้าสู่หน้า Check-in</p>
                <div style="display:flex;gap:0.5rem;justify-content:center;">
                    <button onclick="closeQRModal()" class="btn btn-outline">ปิด</button>
                    <a href="{{ route('checkin.walkin', $activity->qr_token) }}" target="_blank" class="btn btn-primary">เปิดหน้า Check-in</a>
                </div>
            </div>
        </div>

        {{-- QR Code Library --}}
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
        <script>
            function copyWalkInUrl() {
                const url = document.getElementById('walkin-url').textContent;
                navigator.clipboard.writeText(url).then(() => {
                    // แสดง toast แจ้งว่าคัดลอกสำเร็จ
                    const toast = document.createElement('div');
                    toast.textContent = 'คัดลอกลิงก์สำเร็จแล้ว!';
                    toast.style.cssText = 'position:fixed;top:20px;right:20px;background:#10b981;color:white;padding:12px 20px;border-radius:8px;z-index:2000;animation:slideIn 0.3s ease;';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 3000);
                });
            }

            function showQRModal() {
                document.getElementById('qrModal').style.display = 'flex';
                // สร้าง QR Code
                const qrContainer = document.getElementById('qr-code');
                qrContainer.innerHTML = ''; // ล้างเก่าก่อน
                new QRCode(qrContainer, {
                    text: document.getElementById('walkin-url').textContent,
                    width: 256,
                    height: 256,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            }

            function closeQRModal() {
                document.getElementById('qrModal').style.display = 'none';
            }

            // ปิด modal เมื่อคลิกพื้นหลัง
            document.getElementById('qrModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeQRModal();
                }
            });
        </script>

        <style>
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        </style>
        @endif
    </div>
</div>
@endsection

@if($activity->hasGeolocation())
@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var lat = {!! json_encode((float)$activity->latitude) !!};
    var lng = {!! json_encode((float)$activity->longitude) !!};
    var radius = {!! json_encode((int)($activity->checkin_radius ?? 200)) !!};

    var map = L.map('showMap', { scrollWheelZoom: false }).setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    L.marker([lat, lng]).addTo(map).bindPopup('{{ $activity->location ?? $activity->title }}');
    L.circle([lat, lng], {
        radius: radius,
        color: '#4f46e5',
        fillColor: '#818cf8',
        fillOpacity: 0.15,
        weight: 2
    }).addTo(map);
});
</script>
@endsection
@endif
