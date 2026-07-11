{{-- หน้ามอนิเตอร์เช็คอิน (Admin): QR URL + เช็คอิน manual + รายชื่อผู้เช็คอินแล้ว --}}
@extends('layouts.admin')
@section('title', 'Monitor Check-in')

@section('content')
<a href="{{ route('admin.activities.show', $activity->id) }}" class="text-sm text-primary">&larr; กลับ</a>
<h1 class="font-bold mt-2 mb-1" style="font-size:1.25rem;">Monitor Check-in</h1>
<p class="text-muted text-sm mb-4">{{ $activity->title }}</p>

<div class="grid-2">
    <div>
        {{-- QR Check-in URL สำหรับให้นักศึกษาสแกน --}}
        <div class="card mb-4">
            <div class="card-header" style="background:#dcfce7;color:#166534;">QR สำหรับ "เข้างาน"</div>
            <div class="card-body text-center">
                <code class="text-sm" style="word-break:break-all;">{{ url('/check-in/' . $activity->qr_token) }}</code>
                <p class="text-xs text-muted mt-2">แสดง QR Code เพื่อเช็คอินเข้างาน (QR ที่ 1)</p>
                <form method="POST" action="{{ route('admin.activities.regenerate-qr', $activity->id) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('ยืนยันสร้าง QR เข้างานใหม่? QR เดิมจะใช้งานไม่ได้ทันที')">สร้าง QR เข้างานใหม่</button>
                </form>
            </div>
        </div>

        {{-- QR Check-out URL สำหรับให้นักศึกษาสแกน --}}
        <div class="card mb-4">
            <div class="card-header" style="background:#e0e7ff;color:#3730a3;">QR สำหรับ "ออกงาน (รับชั่วโมง)"</div>
            <div class="card-body text-center">
                <code class="text-sm" style="word-break:break-all;">{{ url('/check-in/' . $activity->qr_checkout_token) }}</code>
                <p class="text-xs text-muted mt-2">แสดง QR Code เพื่อบันทึกออกงานและรับชั่วโมง (QR ที่ 2)</p>
                <form method="POST" action="{{ route('admin.activities.regenerate-checkout-qr', $activity->id) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('ยืนยันสร้าง QR ออกงานใหม่? QR เดิมจะใช้งานไม่ได้ทันที')">สร้าง QR ออกงานใหม่</button>
                </form>
            </div>
        </div>
        {{-- ฟอร์มเช็คอิน manual: กรอกรหัสนักศึกษาเพื่อเช็คอินให้ --}}
        <div class="card">
            <div class="card-header">เช็คอินด้วยตนเอง</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.activities.manual-checkin', $activity->id) }}" class="flex gap-2">
                    @csrf
                    <input type="text" name="student_id" placeholder="รหัสนักศึกษา" class="form-control flex-1" style="text-align:center;letter-spacing:2px;" required>
                    <button type="submit" class="btn btn-success">เช็คอิน</button>
                </form>
            </div>
        </div>
    </div>

    {{-- รายชื่อรอการอนุมัติ --}}
    @php $pendingAtts = $activity->attendances->where('status', 'pending'); @endphp
    @if($pendingAtts->count() > 0)
    <div class="card mb-4">
        <div class="card-header" style="background:#fef3c7;color:#92400e;">
            รอการอนุมัติ ({{ $pendingAtts->count() }} คน)
        </div>
        <div style="max-height:300px;overflow-y:auto;">
            @foreach($pendingAtts->sortByDesc('checked_in_at') as $att)
            <div class="card-body" style="padding:.75rem 1.25rem;border-bottom:1px solid #f1f5f9;">
                <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:.5rem;">
                    <div>
                        <p class="font-semi text-sm">{{ $att->user->full_name }}</p>
                        <p class="text-xs text-muted">
                            {{ $att->user->student_id }}
                            | {{ $att->method === 'qr_scan' ? 'QR' : ($att->method === 'self' ? 'Self' : 'Manual') }}
                            | {{ $att->checked_in_at ? $att->checked_in_at->format('H:i:s') : '-' }}
                            @if($att->distance_meters !== null)
                                | {{ number_format($att->distance_meters, 0) }}m
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <form method="POST" action="{{ route('admin.attendances.approve', $att->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">อนุมัติ</button>
                        </form>
                        <form method="POST" action="{{ route('admin.attendances.reject', $att->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">ปฏิเสธ</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- รายชื่อผู้เช็คอินที่อนุมัติแล้ว --}}
    @php $approvedAtts = $activity->attendances->where('status', 'approved'); @endphp
    <div class="card">
        <div class="card-header flex justify-between">
            <span>อนุมัติแล้ว ({{ $approvedAtts->count() }} คน)</span>
            <span class="text-muted text-sm">จาก {{ $activity->getRegisteredCount() }} คน</span>
        </div>
        <div style="max-height:400px;overflow-y:auto;">
            @forelse($approvedAtts->sortByDesc('checked_in_at') as $att)
            <div class="card-body" style="padding:.75rem 1.25rem;border-bottom:1px solid #f1f5f9;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semi text-sm">{{ $att->user->full_name }}</p>
                        <p class="text-xs text-muted">
                            {{ $att->user->student_id }}
                            | {{ $att->method === 'qr_scan' ? 'QR' : ($att->method === 'self' ? 'Self' : 'Manual') }}
                            @if($att->distance_meters !== null)
                                | {{ number_format($att->distance_meters, 0) }}m
                            @endif
                        </p>
                    </div>
                    <span class="text-xs text-muted">{{ $att->checked_in_at ? $att->checked_in_at->format('H:i:s') : '-' }}</span>
                </div>
            </div>
            @empty
            <div class="card-body text-center text-muted" style="padding:2rem;">ยังไม่มีการเช็คอิน</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
