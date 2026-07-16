@extends('layouts.admin')
@section('title', 'การประเมินกิจกรรม')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="font-bold flex items-center gap-3" style="font-size:1.5rem; color:#1e293b;">
            <svg style="width:28px; height:28px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
            การประเมินกิจกรรม
        </h1>
        <p class="text-sm text-muted mt-1">รายงานสรุปผลการประเมินความพึงพอใจและข้อเสนอแนะของนักศึกษาต่อกิจกรรมต่างๆ</p>
    </div>
    
    <div style="display:flex; gap:8px;">
        <span style="background:#e0f2fe; color:#0369a1; padding:6px 14px; border-radius:999px; font-size:0.75rem; font-weight:700; border:1px solid #bae6fd;">
            ทั้งหมด {{ number_format($stats['total']) }} รายการ
        </span>
        <span style="background:#fef3c7; color:#b45309; padding:6px 14px; border-radius:999px; font-size:0.75rem; font-weight:700; border:1px solid #fde68a; display:inline-flex; align-items:center; gap:4px;">
            คะแนนเฉลี่ย {{ number_format((float)($stats['average'] ?? 0), 1) }} / 5.0
            <svg style="width: 14px; height: 14px; color:#fbbf24;" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
            </svg>
        </span>
    </div>
</div>

{{-- สถิติการกระจายคะแนน (Rating Cards) --}}
<div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
    @foreach([
        ['rating' => 5, 'count' => $stats['rating_5'], 'color' => '#10b981', 'bg' => '#ecfdf5', 'border' => '#a7f3d0'],
        ['rating' => 4, 'count' => $stats['rating_4'], 'color' => '#84cc16', 'bg' => '#f7fee7', 'border' => '#d9f99d'],
        ['rating' => 3, 'count' => $stats['rating_3'], 'color' => '#eab308', 'bg' => '#fefce8', 'border' => '#fef08a'],
        ['rating' => 2, 'count' => $stats['rating_2'], 'color' => '#f97316', 'bg' => '#fff7ed', 'border' => '#fed7aa'],
        ['rating' => 1, 'count' => $stats['rating_1'], 'color' => '#ef4444', 'bg' => '#fef2f2', 'border' => '#fecaca']
    ] as $rc)
        <div class="card" style="border: 1px solid {{ $rc['border'] }}; background: {{ $rc['bg'] }}; box-shadow: 0 1px 2px rgba(0,0,0,0.02); text-align:center; border-radius:10px;">
            <div class="card-body" style="padding: 1rem 0.5rem;">
                <p style="font-size:1.75rem; font-weight:800; color:{{ $rc['color'] }}; margin:0; line-height:1.1;">{{ number_format($rc['count']) }}</p>
                <div style="display:flex; align-items:center; justify-content:center; gap:2px; margin-top:4px;">
                    <span class="text-xs font-bold" style="color:#475569;">{{ $rc['rating'] }}</span>
                    <svg style="width: 12px; height: 12px; color:#fbbf24;" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- ตัวกรองสืบค้นแบบเป็นทางการ --}}
<form method="GET" action="{{ route('admin.feedbacks.index') }}" class="card mb-4" style="border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius:12px;">
    <div class="card-body" style="padding: 1.25rem 1.5rem;">
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:2; min-width:240px;">
                <label class="form-label" style="font-weight:600; color:#475569; margin-bottom:0.4rem; font-size:0.8rem;">ค้นหาจากความคิดเห็น</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="คำสำคัญหรือข้อเสนอแนะ..." class="form-control" style="font-size:.85rem; padding: 0.5rem 0.75rem; border-radius: 8px;">
            </div>
            
            <div style="flex:1.5; min-width:200px;">
                <label class="form-label" style="font-weight:600; color:#475569; margin-bottom:0.4rem; font-size:0.8rem;">คัดกรองจากกิจกรรม</label>
                <select name="activity_id" class="form-control" style="font-size:.85rem; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <option value="">-- กิจกรรมทั้งหมด --</option>
                    @foreach($activities as $act)
                        <option value="{{ $act->id }}" {{ request('activity_id') == $act->id ? 'selected' : '' }}>
                            {{ Str::limit($act->title, 45) }} ({{ $act->activity_date->format('d/m/y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div style="width:130px;">
                <label class="form-label" style="font-weight:600; color:#475569; margin-bottom:0.4rem; font-size:0.8rem;">คะแนนประเมิน</label>
                <select name="rating" class="form-control" style="font-size:.85rem; padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <option value="">-- ทั้งหมด --</option>
                    @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ request('rating') == $i ? 'selected' : '' }}>{{ $i }} คะแนน</option>
                    @endfor
                </select>
            </div>
            
            <div style="display:flex; gap:6px;">
                <button type="submit" class="btn btn-primary" style="font-size:.85rem; padding:0.5rem 1.25rem; border-radius:8px; background:#4f46e5; border:none; font-weight:600;">
                    กรองข้อมูล
                </button>
                <a href="{{ route('admin.feedbacks.index') }}" class="btn btn-outline" style="font-size:.85rem; padding:0.5rem 1rem; border-radius:8px; background:#fff; font-weight:600;">
                    ล้างตัวกรอง
                </a>
            </div>
        </div>
    </div>
</form>

{{-- ตารางแสดงผลรายงานประเมินความพึงพอใจ --}}
<div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:220px;">กิจกรรม</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:150px;">ผู้ประเมิน</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:140px;">คะแนนรวม</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700;">ความคิดเห็น / ข้อเสนอแนะ</th>
                    <th style="padding:1rem 1.5rem; text-align:left; font-weight:700; width:130px;">วันที่ส่งประเมิน</th>
                    <th style="padding:1rem 1.5rem; text-align:right; font-weight:700; width:100px;">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($feedbacks as $fb)
                    <tr style="border-bottom:1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                        <td style="padding:1.1rem 1.5rem;">
                            <a href="{{ route('admin.feedbacks.show', $fb->activity_id) }}" class="font-semi" style="color:#4f46e5; text-decoration:none; display:block;">
                                {{ Str::limit($fb->activity->title, 40) }}
                            </a>
                        </td>
                        <td style="padding:1.1rem 1.5rem;">
                            @if($fb->is_anonymous)
                                <span class="text-muted" style="font-style:italic; background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.75rem;">ไม่ระบุตัวตน</span>
                            @else
                                <span class="font-semi" style="color:#1e293b;">{{ $fb->user->full_name ?? '-' }}</span>
                            @endif
                        </td>
                        <td style="padding:1.1rem 1.5rem;">
                            <div style="display:flex; align-items:center; gap:2px;">
                                @for($j = 1; $j <= 5; $j++)
                                    <svg style="width: 14px; height: 14px; color:{{ $j <= $fb->rating ? '#fbbf24' : '#e2e8f0' }};" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                    </svg>
                                @endfor
                                <span style="font-size:0.8rem; margin-left:4px; font-weight:700; color:#475569;">({{ $fb->rating }}/5)</span>
                            </div>
                        </td>
                        <td style="padding:1.1rem 1.5rem; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#334155;">
                            {{ $fb->comment ?? '-' }}
                        </td>
                        <td style="padding:1.1rem 1.5rem; color:#64748b; font-size:0.8rem;">
                            {{ $fb->created_at->translatedFormat('d M Y H:i') }}
                        </td>
                        <td style="padding:1.1rem 1.5rem; text-align:right;">
                            <a href="{{ route('admin.feedbacks.show', $fb->activity_id) }}" class="btn btn-sm btn-outline" style="font-size:0.75rem; padding:0.35rem 0.75rem; border-radius:6px; background:#fff; border:1px solid #cbd5e1; text-decoration:none; display:inline-block; font-weight:600;">
                                ดูผลสรุป
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:3rem 1.5rem; color:#94a3b8;">
                            <svg style="width:40px; height:40px; color:#cbd5e1; margin-bottom:8px; display:inline-block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
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
