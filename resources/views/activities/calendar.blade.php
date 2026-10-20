@extends('layouts.app')
@section('title', 'ปฏิทินกิจกรรม')

@section('content')
<a href="{{ route('activities.index') }}" class="text-sm text-primary">&larr; กลับรายการกิจกรรม</a>

<div class="card mt-2">
    <div class="card-body" style="font-size:.875rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem;">
            <h1 class="font-bold" style="font-size:1.1rem; margin:0;">📅 ปฏิทินกิจกรรม — {{ $month->translatedFormat('F Y') }}</h1>
            <div style="display:flex; gap:.4rem;">
                <a class="btn btn-outline btn-sm" href="{{ route('activities.calendar', ['month' => $prevMonth, 'category' => $selCategory, 'search' => $selSearch]) }}">&laquo; {{ $prevLabel }}</a>
                <a class="btn btn-outline btn-sm" href="{{ route('activities.calendar', ['month' => $nextMonth, 'category' => $selCategory, 'search' => $selSearch]) }}">{{ $nextLabel }} &raquo;</a>
            </div>
        </div>

        <form method="GET" action="{{ route('activities.calendar', ['month' => $month->format('Y-m')]) }}"
              style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.75rem;">
            <select name="category" style="max-width:180px;">
                <option value="">ทุกหมวด</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($selCategory == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $selSearch }}" placeholder="ค้นหาชื่อ/สถานที่..." style="flex:1; min-width:140px;">
            <button class="btn btn-primary btn-sm">กรอง</button>
            @if($selCategory || $selSearch !== '')
                <a class="btn btn-outline btn-sm" href="{{ route('activities.calendar', ['month' => $month->format('Y-m')]) }}">ล้าง</a>
            @endif
        </form>

        <span class="text-muted" style="font-size:.78rem;">
            กิจกรรมในเดือนนี้: {{ number_format($totalInMonth) }} รายการ
        </span>

        @php
            $weekHead = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
            $firstDay = $month->copy()->startOfMonth();
            $offset = $firstDay->dayOfWeek;            // 0 = อาทิตย์
            $daysInMonth = $firstDay->daysInMonth;
            $today = now()->toDateString();
            $cells = [];
            for ($i = 0; $i < $offset; $i++) { $cells[] = null; }
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $cells[] = $firstDay->copy()->addDays($d - 1);
            }
            while (count($cells) % 7 !== 0) { $cells[] = null; }
        @endphp

        <div style="overflow-x:auto; margin-top:.5rem;">
            <table style="width:100%; border-collapse:collapse; min-width:640px;" >
                <thead>
                    <tr>
                        @foreach($weekHead as $w)
                            <th style="font-size:.72rem; color:#64748b; padding:.35rem; text-align:center;">{{ $w }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_chunk($cells, 7) as $week)
                        <tr>
                            @foreach($week as $cell)
                                <td style="vertical-align:top; padding:.25rem; min-width:84px;">
                                    @if($cell)
                                        @php $key = $cell->toDateString(); @endphp
                                        <div style="border:1px solid {{ $key === $today ? '#0ea5e9' : '#f1f5f9' }}; border-radius:10px; padding:.35rem; min-height:88px; background:{{ $key === $today ? '#f0f9ff' : '#fff' }};">
                                            <div style="font-size:.72rem; color:#334155; text-align:center; font-weight:700;">{{ $cell->day }}</div>
                                            @if(!empty($byDate[$key]))
                                                <div style="display:flex; flex-direction:column; gap:.2rem; margin-top:.25rem;">
                                                    @foreach(array_slice($byDate[$key], 0, 3) as $act)
                                                        <a href="{{ route('activities.show', $act) }}" style="display:block; font-size:.7rem; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:8px; padding:2px 4px; text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $act->title }}">{{ $act->title }}</a>
                                                    @endforeach
                                                    @if(count($byDate[$key]) > 3)
                                                        <a href="{{ route('activities.calendar', ['month' => $month->format('Y-m'), 'category' => $selCategory, 'search' => $selSearch]) }}" style="font-size:.68rem; color:#64748b; text-align:center;">+{{ count($byDate[$key]) - 3 }} เพิ่มเติม</a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
