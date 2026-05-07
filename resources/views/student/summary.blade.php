{{-- หน้าสรุปชั่วโมงกิจกรรม: ชั่วโมงรวม + แยกตามหมวดหมู่พร้อม progress bar --}}
@extends('layouts.app')
@section('title', 'สรุปกิจกรรม')

@section('content')
{{-- การ์ดสรุปชั่วโมงรวม --}}
<div class="hero-card">
    <div class="flex justify-between items-center" style="margin-bottom:.5rem;">
        <div>
            <p class="hero-label">ชั่วโมงกิจกรรมรวม</p>
            <p class="hero-value">{{ number_format($totalHours, 1) }}</p>
            <p class="hero-sub">จากทั้งหมด {{ $totalRequired }} ชั่วโมงที่กำหนด</p>
        </div>
        <a href="{{ route('student.summary.pdf') }}" class="btn" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);gap:.375rem;">
            <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            ดาวน์โหลด PDF
        </a>
    </div>
</div>

{{-- ชั่วโมงแยกตามหมวดพร้อม progress bar: เขียว/เหลือง/แดง ตามสัดส่วน --}}
<h2 class="font-bold mb-2">ชั่วโมงแยกตามหมวดหมู่</h2>
@foreach($byCategory as $cat)
    <div class="card mb-2">
        <div class="card-body" style="padding:.75rem 1rem;">
            @php $p = $cat['required'] > 0 ? min(100, ($cat['hours']/$cat['required'])*100) : 0; @endphp
            <div class="flex justify-between items-center mb-2">
                <span class="font-semi text-sm">{{ $cat['name'] }}</span>
                <span class="text-xs font-bold" style="background:#f1f5f9; padding:.15rem .4rem; border-radius:4px; color:#475569;">
                    {{ number_format($p, 0) }}%
                </span>
            </div>
            <div class="progress" style="height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: .4rem;">
                <div class="progress-bar {{ $p >= 100 ? 'green' : ($p >= 50 ? 'yellow' : 'primary') }}" style="width:{{ $p }}%; height: 100%; transition: width 0.5s ease;"></div>
            </div>
            <div class="flex justify-between items-center">
                @if($p >= 100)
                    <p class="text-xs font-semi" style="color:#16a34a;">✓ ผ่านเกณฑ์ขั้นต่ำ</p>
                @else
                    <p class="text-xs text-muted">ต้องการอีก <span style="font-weight:600; color:#ef4444;">{{ number_format($cat['required'] - $cat['hours'], 1) }}</span> ชม.</p>
                @endif
                <span class="text-sm">
                    <span style="color:{{ $p >= 100 ? '#16a34a' : '#1e40af' }};font-weight:700;">{{ number_format($cat['hours'], 1) }}</span>
                    <span class="text-muted">/{{ number_format($cat['required'], 0) }} ชม.</span>
                </span>
            </div>
        </div>
    </div>
@endforeach
@endsection
