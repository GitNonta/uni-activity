@extends('layouts.app')
@section('title', 'ชำระเงิน — '.$activity->title)

@section('content')
<a href="{{ route('activities.show', $activity) }}" class="text-sm text-primary">{{ __('&larr; กลับกิจกรรม') }}</a>

<div class="card mt-2">
    <div class="card-body" style="font-size:.875rem;">
        <h1 class="font-bold" style="font-size:1.05rem;">💰 ชำระค่าสมัคร — {{ $activity->title }}</h1>

        <div class="mt-2" style="display:flex; gap:.75rem; align-items:center; flex-wrap:wrap;">
            <span class="text-muted">{{ __('ยอดชำระ:') }}</span>
            <span class="font-bold" style="font-size:1.15rem;">฿{{ number_format($payment->amount, 2) }}</span>
            <span class="badge badge-orange">{{ $payment->status }}</span>
            <span class="text-muted" style="font-size:.78rem;">อ้างอิง {{ $payment->ref }}</span>
        </div>

        @php
            $qrSvg = null;
            if ($qrPayload) {
                try { $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->generate($qrPayload); } catch (\Throwable) { $qrSvg = null; }
            }
        @endphp

        @if($enablePay && $qrSvg)
            <div class="mt-3" style="text-align:center;">
                <div style="background:#fff; border:1px solid #f1f5f9; border-radius:12px; padding:12px; display:inline-block;">
                    {!! $qrSvg !!}
                </div>
                <p class="text-muted" style="font-size:.78rem; margin:.5rem 0 0;">{{ __('สแกนผ่านแอปธนาคาร (PromptPay)') }}</p>
                <form method="POST" action="{{ route('activities.pay.paid', $activity) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block"
                            onclick="return confirm('ยืนยันว่าได้ชำระแล้ว? (Soft-launch — บันทึกสถานะ paid)')">{{ __('ฉันชำระเงินแล้ว — ยืนยัน') }}                    </button>
                </form>
            </div>
        @elseif(!$enablePay)
            <div class="mt-3" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:.85rem;">{{ __('✅ สถานะ:') }} <span class="font-bold">{{ $payment->status }}</span>
                @if($payment->paid_at)
                    <span class="text-muted" style="font-size:.78rem;">({{ $payment->paid_at->format('d/m/Y H:i') }})</span>
                @endif
            </div>
        @else
            <div class="mt-3" style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:.85rem;">{{ __('⚠️ กิจกรรมนี้กำหนดค่าสมัคร') }} <b>฿{{ number_format($activity->fee_amount, 2) }}</b> {{ __('แต่ยังไม่ได้ตั้งเบอร์ PromptPay ผู้รับเงิน — ยังสร้าง QR ไม่ได้ ติดต่อผู้จัดกิจกรรม') }}            </div>
        @endif
    </div>
</div>
@endsection
