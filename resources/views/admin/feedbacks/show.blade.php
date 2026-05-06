{{-- หน้ารายละเอียด Feedback ของกิจกรรม (Admin) --}}
@extends('layouts.admin')
@section('title', 'Feedback: ' . $activity->title)

@section('content')
<div class="flex items-center gap-3 mb-4">
    <a href="{{ route('admin.feedbacks.index') }}" class="btn btn-outline btn-sm" style="font-size:.8rem;">&larr; กลับ</a>
    <h1 class="font-bold" style="font-size:1.3rem;">Feedback: {{ Str::limit($activity->title, 50) }}</h1>
</div>

{{-- ข้อมูลกิจกรรม --}}
<div class="card mb-4">
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">กิจกรรม</p>
                <p class="font-semi">{{ $activity->title }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">หมวดหมู่</p>
                <p class="font-semi">{{ $activity->category->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">วันที่จัด</p>
                <p class="font-semi">{{ $activity->activity_date->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">จำนวนผู้ประเมิน</p>
                <p class="font-semi">{{ $stats['total'] }} คน</p>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin-bottom:.15rem;">คะแนนเฉลี่ย</p>
                <p style="font-size:1.5rem;font-weight:700;color:#f59e0b;">
                    {{ $stats['average'] ?? '-' }} 
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-left: 4px; vertical-align: middle;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </p>
            </div>
        </div>
    </div>
</div>

{{-- กราฟแท่งคะแนน --}}
<div class="card mb-4">
    <div class="card-body">
        <h2 class="font-bold mb-3" style="font-size:1rem;">การกระจายคะแนน</h2>
        @php $maxCount = max($stats['rating_5'], $stats['rating_4'], $stats['rating_3'], $stats['rating_2'], $stats['rating_1'], 1); @endphp
        <div style="display:flex;flex-direction:column;gap:.5rem;">
            @foreach([5,4,3,2,1] as $r)
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:40px;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:4px;">
                    {{ $r }} 
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 12px; height: 12px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
                <div style="flex:1;background:#f1f5f9;border-radius:4px;height:24px;position:relative;overflow:hidden;">
                    @php 
                        $count = $stats["rating_$r"];
                        $percent = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                        $color = match($r) {
                            5 => '#16a34a',
                            4 => '#84cc16',
                            3 => '#eab308',
                            2 => '#f97316',
                            1 => '#dc2626',
                        };
                    @endphp
                    <div style="width:{{ $percent }}%;background:{{ $color }};height:100%;transition:width .3s;"></div>
                </div>
                <span style="width:50px;font-size:.85rem;font-weight:600;text-align:right;">{{ $count }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- คะแนนเฉลี่ยแยกตามหัวข้อ --}}
@if(array_sum($detailedAvg) > 0)
<div class="card mb-4">
    <div class="card-body">
        <h2 class="font-bold mb-3" style="font-size:1rem;">คะแนนเฉลี่ยแยกตามหัวข้อ</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">
            @if($detailedAvg['content'] > 0)
            <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:8px;">
                <p class="text-xs text-muted" style="margin-bottom:.25rem;">เนื้อหากิจกรรม</p>
                <p style="font-size:1.3rem;font-weight:700;color:#4f46e5;">
                    {{ $detailedAvg['content'] }} 
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-left: 4px; vertical-align: middle;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </p>
            </div>
            @endif
            @if($detailedAvg['speaker'] > 0)
            <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:8px;">
                <p class="text-xs text-muted" style="margin-bottom:.25rem;">วิทยากร/ผู้ดำเนินการ</p>
                <p style="font-size:1.3rem;font-weight:700;color:#4f46e5;">
                    {{ $detailedAvg['speaker'] }} 
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-left: 4px; vertical-align: middle;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </p>
            </div>
            @endif
            @if($detailedAvg['location'] > 0)
            <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:8px;">
                <p class="text-xs text-muted" style="margin-bottom:.25rem;">สถานที่/สิ่งอำนวยความสะดวก</p>
                <p style="font-size:1.3rem;font-weight:700;color:#4f46e5;">
                    {{ $detailedAvg['location'] }} 
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-left: 4px; vertical-align: middle;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </p>
            </div>
            @endif
            @if($detailedAvg['organization'] > 0)
            <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:8px;">
                <p class="text-xs text-muted" style="margin-bottom:.25rem;">การจัดการ/ประสานงาน</p>
                <p style="font-size:1.3rem;font-weight:700;color:#4f46e5;">
                    {{ $detailedAvg['organization'] }} 
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-left: 4px; vertical-align: middle;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </p>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- รายการความคิดเห็น --}}
<div class="card">
    <div class="card-body">
        <h2 class="font-bold mb-3" style="font-size:1rem;">ความคิดเห็นทั้งหมด ({{ $activity->feedbacks->count() }})</h2>
        <div style="display:flex;flex-direction:column;gap:1rem;">
            @forelse($activity->feedbacks->sortByDesc('created_at') as $fb)
            <div style="padding:1rem;background:#f8fafc;border-radius:8px;border-left:4px solid #4f46e5;">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.5rem;">
                    <div>
                        @if($fb->is_anonymous)
                            <span style="font-weight:600;color:#64748b;font-style:italic;">ไม่ระบุตัวตน</span>
                        @else
                            <span style="font-weight:600;">{{ $fb->user->full_name ?? '-' }}</span>
                        @endif
                        <div style="display:flex;align-items:center;gap:2px;margin-left:.5rem;">
                            @for($j = 1; $j <= $fb->rating; $j++)
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; color: #fbbf24;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            @endfor
                            <span style="font-size:0.85rem;margin-left:4px;">({{ $fb->rating }}/5)</span>
                        </div>
                    </div>
                    <span style="font-size:.75rem;color:#94a3b8;">{{ $fb->created_at->format('d/m/Y H:i') }}</span>
                </div>
                
                @if($fb->comment)
                <p style="color:#475569;line-height:1.6;margin-bottom:.75rem;">{{ $fb->comment }}</p>
                @endif

                @if($fb->ratings && is_array($fb->ratings))
                <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.8rem;color:#64748b;">
                    @if(isset($fb->ratings['content']))
                        <span>เนื้อหา: {{ $fb->ratings['content'] }} คะแนน</span>
                    @endif
                    @if(isset($fb->ratings['speaker']))
                        <span>วิทยากร: {{ $fb->ratings['speaker'] }} คะแนน</span>
                    @endif
                    @if(isset($fb->ratings['location']))
                        <span>สถานที่: {{ $fb->ratings['location'] }} คะแนน</span>
                    @endif
                    @if(isset($fb->ratings['organization']))
                        <span>การจัดการ: {{ $fb->ratings['organization'] }} คะแนน</span>
                    @endif
                </div>
                @endif
            </div>
            @empty
            <p style="text-align:center;padding:2rem;color:#94a3b8;">ยังไม่มีความคิดเห็น</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
