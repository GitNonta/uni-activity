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
        <div class="card-body">
            <div class="flex justify-between text-sm mb-1">
                <span class="font-semi">{{ $cat['name'] }}</span>
                <span>{{ number_format($cat['hours'], 1) }}/{{ $cat['required'] }} ชม.</span>
            </div>
            <div class="progress">
                @php $p = $cat['required'] > 0 ? min(100, ($cat['hours']/$cat['required'])*100) : 0; @endphp
                <div class="progress-bar {{ $p >= 100 ? 'green' : ($p >= 50 ? 'yellow' : 'primary') }}" style="width:{{ $p }}%"></div>
            </div>
        </div>
    </div>
@endforeach
@endsection
