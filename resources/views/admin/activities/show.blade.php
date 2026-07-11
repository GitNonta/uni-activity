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

        {{-- QR Code Section --}}
        <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #e2e8f0;">
            <h3 class="font-bold mb-3" style="font-size:1.1rem;color:#1e293b;">ระบบคิวอาร์โค้ด (QR Codes)</h3>
            
            <div class="grid-2" style="gap:1rem;">
                {{-- QR 1: Check-in --}}
                <div class="card" style="border:1px solid #dcfce7;box-shadow:none;">
                    <div class="card-header" style="background:#dcfce7;color:#166534;padding:0.75rem 1rem;">QR ที่ 1: เข้างาน (Check-in)</div>
                    <div class="card-body" style="padding:1rem;">
                        <div class="flex items-center gap-2 mb-3" style="flex-wrap:wrap;">
                            <code id="entry-url" class="text-xs" style="background:#f1f5f9;padding:.375rem;border-radius:4px;flex:1;word-break:break-all;">{{ url('/check-in/' . $activity->qr_token) }}</code>
                            <button onclick="copyToClipboard('entry-url')" class="btn btn-sm btn-outline" style="white-space:nowrap;" title="คัดลอก">คัดลอก</button>
                            <button onclick="showQRModal('{{ url('/check-in/' . $activity->qr_token) }}', 'QR สำหรับเข้างาน')" class="btn btn-sm btn-outline" style="white-space:nowrap;">แสดง QR</button>
                        </div>
                        
                        <form method="POST" action="{{ route('admin.activities.regenerate-qr', $activity->id) }}" onsubmit="return confirm('ยืนยันสร้าง QR เข้างานใหม่? ลิงก์และ QR เดิมจะไม่สามารถใช้งานได้อีก')">
                            @csrf
                            <div class="flex items-center gap-2" style="flex-wrap:wrap;">
                                <select name="expires_in_hours" class="form-control form-control-sm" style="width:auto;font-size:0.75rem;padding:0.25rem;">
                                    <option value="">-- ไม่จำกัดเวลา --</option>
                                    <option value="1">1 ชั่วโมง</option>
                                    <option value="6">6 ชั่วโมง</option>
                                    <option value="24">24 ชั่วโมง</option>
                                </select>
                                <button type="submit" class="btn btn-sm" style="background:#166534;color:#fff;">สร้าง QR ใหม่</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- QR 2: Check-out --}}
                <div class="card" style="border:1px solid #e0e7ff;box-shadow:none;">
                    <div class="card-header" style="background:#e0e7ff;color:#3730a3;padding:0.75rem 1rem;">QR ที่ 2: ออกงาน (Check-out)</div>
                    <div class="card-body" style="padding:1rem;">
                        <div class="flex items-center gap-2 mb-3" style="flex-wrap:wrap;">
                            <code id="exit-url" class="text-xs" style="background:#f1f5f9;padding:.375rem;border-radius:4px;flex:1;word-break:break-all;">{{ url('/check-in/' . $activity->qr_checkout_token) }}</code>
                            <button onclick="copyToClipboard('exit-url')" class="btn btn-sm btn-outline" style="white-space:nowrap;" title="คัดลอก">คัดลอก</button>
                            <button onclick="showQRModal('{{ url('/check-in/' . $activity->qr_checkout_token) }}', 'QR สำหรับออกงาน (รับชั่วโมง)')" class="btn btn-sm btn-outline" style="white-space:nowrap;">แสดง QR</button>
                        </div>
                        
                        <form method="POST" action="{{ route('admin.activities.regenerate-checkout-qr', $activity->id) }}" onsubmit="return confirm('ยืนยันสร้าง QR ออกงานใหม่? ลิงก์และ QR เดิมจะไม่สามารถใช้งานได้อีก')">
                            @csrf
                            <div class="flex items-center gap-2" style="flex-wrap:wrap;">
                                <select name="expires_in_hours" class="form-control form-control-sm" style="width:auto;font-size:0.75rem;padding:0.25rem;">
                                    <option value="">-- ไม่จำกัดเวลา --</option>
                                    <option value="1">1 ชั่วโมง</option>
                                    <option value="6">6 ชั่วโมง</option>
                                    <option value="24">24 ชั่วโมง</option>
                                </select>
                                <button type="submit" class="btn btn-sm" style="background:#3730a3;color:#fff;">สร้าง QR ใหม่</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Walk-in Check-in URL (Staff only) --}}
            <div class="mt-3 card" style="border:1px solid #fef3c7;box-shadow:none;">
                <div class="card-header" style="background:#fef3c7;color:#92400e;padding:0.75rem 1rem;">สแกนสำหรับ Walk-in (แอดมินเท่านั้น)</div>
                <div class="card-body" style="padding:1rem;">
                    <div class="flex items-center gap-2" style="flex-wrap:wrap;">
                        <code id="walkin-url" class="text-xs" style="background:#f1f5f9;padding:.375rem;border-radius:4px;flex:1;word-break:break-all;">{{ url('/walkin/' . $activity->qr_token) }}</code>
                        <button onclick="copyToClipboard('walkin-url')" class="btn btn-sm btn-outline" style="white-space:nowrap;" title="คัดลอก">คัดลอก</button>
                        <button onclick="showQRModal('{{ url('/walkin/' . $activity->qr_token) }}', 'QR สำหรับ Walk-in')" class="btn btn-sm btn-outline" style="white-space:nowrap;">แสดง QR</button>
                        <a href="{{ route('checkin.walkin', $activity->qr_token) }}" target="_blank" class="btn btn-sm" style="background:#f59e0b;color:#fff;white-space:nowrap;">เปิดหน้า Walk-in</a>
                    </div>
                </div>
            </div>
            
            @if($activity->qr_expires_at)
            <div class="mt-3 text-sm" style="color:#ef4444;font-weight:600;">
                * QR Code เข้างานเดิม หมดอายุ: {{ \Carbon\Carbon::parse($activity->qr_expires_at)->format('d/m/Y H:i') }}
            </div>
            @endif
        </div>

        {{-- QR Code Modal --}}
        @if($activity->qr_token)
        <div id="qrModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;">
            <div style="background:white;padding:2rem;border-radius:12px;max-width:400px;width:90%;text-align:center;">
                <h3 style="margin-bottom:1rem;color:#1f2937;">QR Code</h3>
                <div id="qr-code" style="margin:1rem 0;"></div>
                <p style="font-size:0.9rem;color:#6b7280;margin-bottom:1.5rem;">สแกน QR Code เพื่อเข้าสู่หน้า Check-in</p>
                <div style="display:flex;gap:0.5rem;justify-content:center;">
                    <button onclick="closeQRModal()" class="btn btn-outline">ปิด</button>
                    <button onclick="closeQRModal()" class="btn btn-outline" style="width:100%;">ปิด</button>
                </div>
            </div>
        </div>

        {{-- QR Code Library --}}
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
        <script>
            function copyToClipboard(elementId) {
                const url = document.getElementById(elementId).textContent;
                navigator.clipboard.writeText(url).then(() => {
                    const toast = document.createElement('div');
                    toast.textContent = 'คัดลอกลิงก์สำเร็จแล้ว!';
                    toast.style.cssText = 'position:fixed;top:20px;right:20px;background:#10b981;color:white;padding:12px 20px;border-radius:8px;z-index:2000;animation:slideIn 0.3s ease;';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 3000);
                });
            }

            function showQRModal(url, titleText = 'QR Code') {
                document.getElementById('qrModal').style.display = 'flex';
                document.querySelector('#qrModal h3').textContent = titleText;
                const qrContainer = document.getElementById('qr-code');
                qrContainer.innerHTML = '';
                new QRCode(qrContainer, {
                    text: url,
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
        @endif

        <style>
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        </style>
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
