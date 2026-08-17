@extends('layouts.admin')
@section('title', 'การประเมินกิจกรรม')

@section('content')
<div class="flex items-center justify-between mb-6 flex-wrap gap-4">
    <div>
        <h1 class="font-bold flex items-center gap-3" style="font-size:1.5rem; color:#f4f4f5; margin:0;">
            <svg style="width:26px; height:26px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            รายงานการประเมินความพึงพอใจกิจกรรม
        </h1>
        <p class="text-sm text-muted mt-1">รายงานสรุปผลการประเมินความพึงพอใจและข้อเสนอแนะของผู้เข้าร่วมกิจกรรม</p>
    </div>
    
    <div style="display:flex; gap:8px;">
        <span style="background:#141416; color:#f4f4f5; padding:6px 14px; border-radius:999px; font-size:0.75rem; font-weight:700; border:1px solid #27272a;">
            ทั้งหมด {{ number_format($stats['total']) }} รายการ
        </span>
        <span style="background:rgba(234, 88, 12, 0.15); color:#f97316; padding:6px 14px; border-radius:999px; font-size:0.75rem; font-weight:700; border:1px solid rgba(234, 88, 12, 0.3); display:inline-flex; align-items:center; gap:6px;">
            <svg width="14" height="14" fill="#fbbf24" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            คะแนนเฉลี่ย {{ number_format((float)($stats['average'] ?? 0), 2) }} / 5.0 คะแนน
        </span>
    </div>
</div>

{{-- สถิติการกระจายคะแนน (Rating Cards) --}}
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
    @foreach([
        ['rating' => 5, 'count' => $stats['rating_5'], 'color' => '#10b981', 'label' => 'ระดับ 5 (มากที่สุด)'],
        ['rating' => 4, 'count' => $stats['rating_4'], 'color' => '#84cc16', 'label' => 'ระดับ 4 (มาก)'],
        ['rating' => 3, 'count' => $stats['rating_3'], 'color' => '#eab308', 'label' => 'ระดับ 3 (ปานกลาง)'],
        ['rating' => 2, 'count' => $stats['rating_2'], 'color' => '#f97316', 'label' => 'ระดับ 2 (น้อย)'],
        ['rating' => 1, 'count' => $stats['rating_1'], 'color' => '#ef4444', 'label' => 'ระดับ 1 (น้อยที่สุด)']
    ] as $rc)
        <div class="card" style="border: 1px solid #27272a; background: #1c1c1f; text-align:center; border-radius:10px; padding: 1rem 0.5rem;">
            <p style="font-size:1.65rem; font-weight:800; color:{{ $rc['color'] }}; margin:0; line-height:1.1;">{{ number_format($rc['count']) }}</p>
            <div style="display:flex; align-items:center; justify-content:center; gap:4px; margin-top:6px;">
                <span class="text-xs font-bold" style="color:#d4d4d8;">{{ $rc['label'] }}</span>
            </div>
        </div>
    @endforeach
</div>

{{-- ตัวกรองสืบค้นแบบเป็นทางการ --}}
<form method="GET" action="{{ route('admin.feedbacks.index') }}" class="card mb-4" style="border: 1px solid #27272a; background: #1c1c1f; border-radius:12px;">
    <div class="card-body" style="padding: 1.25rem 1.5rem;">
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:2; min-width:240px;">
                <label class="form-label" style="font-weight:600; color:#a1a1aa; margin-bottom:0.4rem; font-size:0.8rem;">ค้นหาจากข้อคิดเห็น / ข้อเสนอแนะ</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="คำสำคัญหรือข้อความข้อเสนอแนะ..." class="form-control" style="font-size:.85rem; padding: 0.5rem 0.75rem; border-radius: 8px;">
            </div>
            
            <div style="flex:1.5; min-width:200px;">
                <label class="form-label" style="font-weight:600; color:#a1a1aa; margin-bottom:0.4rem; font-size:0.8rem;">คัดกรองตามกิจกรรม</label>
                <select name="activity_id" class="form-control" style="font-size:.85rem; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <option value="">-- ทุกกิจกรรม --</option>
                    @foreach($activities as $act)
                        <option value="{{ $act->id }}" {{ request('activity_id') == $act->id ? 'selected' : '' }}>
                            {{ Str::limit($act->title, 45) }} ({{ $act->activity_date->format('d/m/y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div style="width:140px;">
                <label class="form-label" style="font-weight:600; color:#a1a1aa; margin-bottom:0.4rem; font-size:0.8rem;">ระดับคะแนน</label>
                <select name="rating" class="form-control" style="font-size:.85rem; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <option value="">-- ทุกระดับ --</option>
                    @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ request('rating') == $i ? 'selected' : '' }}>{{ $i }} คะแนน</option>
                    @endfor
                </select>
            </div>
            
            <div style="display:flex; gap:6px;">
                <button type="submit" class="btn btn-primary" style="font-size:.85rem; padding:0.5rem 1.25rem; border-radius:8px; background:#ea580c; border:none; font-weight:600;">
                    กรองข้อมูล
                </button>
                <a href="{{ route('admin.feedbacks.index') }}" class="btn btn-outline" style="font-size:.85rem; padding:0.5rem 1rem; border-radius:8px; font-weight:600;">
                    ล้างตัวกรอง
                </a>
            </div>
        </div>
    </div>
</form>

{{-- ตารางแสดงผลรายงานประเมินความพึงพอใจ --}}
<div class="card" style="border: 1px solid #27272a; background: #1c1c1f; border-radius:12px; overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="background:#141416; border-bottom:1px solid #27272a; color:#a1a1aa; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:240px;">กิจกรรม</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:170px;">ผู้ประเมิน</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:160px;">ระดับคะแนน</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700;">ความคิดเห็น / ข้อเสนอแนะ</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:140px;">วันที่ส่ง</th>
                    <th style="padding:1rem 1.5rem; text-align:right; font-weight:700; width:150px;">รายงานสรุป</th>
                </tr>
            </thead>
            <tbody>
                @forelse($feedbacks as $fb)
                    <tr style="border-bottom:1px solid #27272a; transition: background 0.15s;">
                        <td style="padding:1.1rem 1.5rem;">
                            <a href="{{ route('admin.feedbacks.show', $fb->activity_id) }}" class="font-semi" style="color:#ea580c; text-decoration:none; display:block;">
                                {{ Str::limit($fb->activity->title, 40) }}
                            </a>
                        </td>
                        <td style="padding:1.1rem 1.5rem;">
                            @if($fb->is_anonymous)
                                <span class="text-muted" style="background:#141416; padding:3px 8px; border-radius:4px; font-size:0.75rem; border:1px solid #27272a; display:inline-flex; align-items:center; gap:4px;">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    ไม่เปิดเผยตัวตน
                                </span>
                            @else
                                <span class="font-semi" style="color:#f4f4f5;">{{ $fb->user->full_name ?? '-' }}</span>
                            @endif
                        </td>
                        <td style="padding:1.1rem 1.5rem;">
                            <div style="display:flex; align-items:center; gap:2px;">
                                @for($j = 1; $j <= 5; $j++)
                                    <svg width="13" height="13" fill="{{ $j <= $fb->rating ? '#fbbf24' : '#3f3f46' }}" viewBox="0 0 20 20" style="display:inline-block;">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                                <span style="font-size:0.8rem; margin-left:6px; font-weight:700; color:#d4d4d8;">({{ $fb->rating }}/5)</span>
                            </div>
                        </td>
                        <td style="padding:1.1rem 1.5rem; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#d4d4d8;">
                            {{ $fb->comment ?? '-' }}
                        </td>
                        <td style="padding:1.1rem 1.5rem; color:#a1a1aa; font-size:0.8rem;">
                            {{ $fb->created_at->translatedFormat('d M Y H:i น.') }}
                        </td>
                        <td style="padding:1.1rem 1.5rem; text-align:right;">
                            <a href="{{ route('admin.feedbacks.show', $fb->activity_id) }}" class="btn btn-sm" style="font-size:0.75rem; padding:0.4rem 0.85rem; border-radius:6px; background:#141416; border:1px solid #27272a; color:#f4f4f5; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-weight:600;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                ดูสรุปผลการประเมิน
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:3rem 1.5rem; color:#a1a1aa;">
                            <svg style="width:40px; height:40px; color:#52525b; margin-bottom:8px; display:inline-block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p style="margin:0; font-weight:600; font-size:0.9rem;">ไม่พบข้อมูลการประเมินกิจกรรม</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pagination --}}
@if($feedbacks->hasPages())
<div style="margin-top:1.5rem; display:flex; justify-content:center;">
    {{ $feedbacks->links() }}
</div>
@endif
@endsection
