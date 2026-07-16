@extends('layouts.admin')
@section('title', 'ผลการประเมิน: ' . $activity->title)

@section('content')
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.feedbacks.index') }}" class="btn btn-outline" style="font-size:.85rem; padding:0.5rem 1rem; background:#fff; border-radius:8px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
        <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        ย้อนกลับ
    </a>
    <div>
        <h1 class="font-bold" style="font-size:1.4rem; color:#1e293b; margin:0;">สรุปผลการประเมินกิจกรรม</h1>
        <p class="text-sm text-muted mt-1">{{ Str::limit($activity->title, 75) }}</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
    
    {{-- ═══ คอลัมน์ซ้าย: ข้อมูลและกราฟประเมิน (2 ส่วนบนหน้าจอใหญ่) ═══ --}}
    <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 1.5rem;">
        
        {{-- สรุปข้อมูลกิจกรรม --}}
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
            <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1.25rem 1.5rem;">
                <h3 class="font-semi flex items-center gap-2" style="font-size:1.05rem; color:#1e293b; margin:0;">
                    <svg style="width:20px; height:20px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    ข้อมูลกิจกรรมและคะแนนภาพรวม
                </h3>
            </div>
            <div class="card-body" style="padding:1.5rem;">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1.25rem;">
                    <div>
                        <span class="text-xs text-muted" style="display:block; margin-bottom:4px;">หัวข้อกิจกรรม</span>
                        <span class="font-bold" style="color:#1e293b; font-size:0.95rem; display:block; line-height:1.4;">{{ $activity->title }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-muted" style="display:block; margin-bottom:4px;">หมวดหมู่กิจกรรม</span>
                        <span class="font-semi" style="color:#475569; display:block;">{{ $activity->category->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-muted" style="display:block; margin-bottom:4px;">วันที่จัดกิจกรรม</span>
                        <span class="font-semi" style="color:#475569; display:block;">{{ $activity->activity_date->translatedFormat('d M Y') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-muted" style="display:block; margin-bottom:4px;">จำนวนผู้ร่วมตอบประเมิน</span>
                        <span class="font-bold" style="color:#4f46e5; font-size:1.1rem; display:block;">{{ number_format($stats['total']) }} คน</span>
                    </div>
                    <div>
                        <span class="text-xs text-muted" style="display:block; margin-bottom:4px;">คะแนนความพึงพอใจเฉลี่ย</span>
                        <div style="display:flex; align-items:baseline; gap:6px;">
                            <span style="font-size:1.75rem; font-weight:800; color:#f59e0b; line-height:1;">{{ number_format((float)($stats['average'] ?? 0), 1) }}</span>
                            <span style="font-size:0.85rem; color:#64748b; font-weight:600;">/ 5.0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- คะแนนการแจกแจงคะแนน (Score Distribution) --}}
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
            <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1.25rem 1.5rem;">
                <h3 class="font-semi flex items-center gap-2" style="font-size:1.05rem; color:#1e293b; margin:0;">
                    <svg style="width:20px; height:20px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    สัดส่วนคะแนนความพึงพอใจ
                </h3>
            </div>
            <div class="card-body" style="padding:1.5rem;">
                @php $maxCount = max($stats['rating_5'], $stats['rating_4'], $stats['rating_3'], $stats['rating_2'], $stats['rating_1'], 1); @endphp
                <div style="display:flex; flex-direction:column; gap:0.85rem;">
                    @foreach([5,4,3,2,1] as $r)
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:45px; font-size:.85rem; font-weight:700; display:flex; align-items:center; justify-content:flex-end; gap:3px; color:#334155;">
                                {{ $r }}
                                <svg style="width: 13px; height: 13px; color:#fbbf24;" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                </svg>
                            </div>
                            <div style="flex:1; background:#f1f5f9; border-radius:999px; height:12px; overflow:hidden;">
                                @php 
                                    $count = $stats["rating_$r"];
                                    $percent = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                                    $color = match($r) {
                                        5 => '#10b981',
                                        4 => '#84cc16',
                                        3 => '#eab308',
                                        2 => '#f97316',
                                        1 => '#ef4444',
                                    };
                                @endphp
                                <div style="width:{{ $percent }}%; background:{{ $color }}; height:100%; border-radius:999px; transition:width .4s ease-out;"></div>
                            </div>
                            <span style="width:55px; font-size:.825rem; font-weight:700; text-align:left; color:#475569;">{{ number_format($count) }} คน</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ความคิดเห็น / ข้อเสนอแนะของนักศึกษา --}}
        <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
            <div class="card-header" style="background:#fff; border-bottom:1px solid #f1f5f9; padding:1.25rem 1.5rem;">
                <h3 class="font-semi flex items-center gap-2" style="font-size:1.05rem; color:#1e293b; margin:0;">
                    <svg style="width:20px; height:20px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                    ความคิดเห็นและข้อเสนอแนะรายบุคคล ({{ $activity->feedbacks->count() }})
                </h3>
            </div>
            <div class="card-body" style="padding:1.5rem; display:flex; flex-direction:column; gap:1rem;">
                @forelse($activity->feedbacks->sortByDesc('created_at') as $fb)
                    @php
                        $accentColor = match($fb->rating) {
                            5 => '#10b981',
                            4 => '#84cc16',
                            3 => '#eab308',
                            2 => '#f97316',
                            1 => '#ef4444',
                            default => '#cbd5e1'
                        };
                    @endphp
                    <div style="padding:1.15rem; background:#f8fafc; border-radius:10px; border-left:4px solid {{ $accentColor }}; border-top:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.01);">
                        <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:0.6rem; flex-wrap:wrap; gap:8px;">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                @if($fb->is_anonymous)
                                    <span class="text-xs" style="color:#64748b; font-weight:600; font-style:italic; background:#e2e8f0; padding:2px 8px; border-radius:4px;">ไม่ระบุตัวตน</span>
                                @else
                                    <span style="font-weight:700; color:#1e293b; font-size:0.875rem;">{{ $fb->user->full_name ?? '-' }}</span>
                                @endif
                                
                                <div style="display:flex; align-items:center; gap:2px; margin-left:4px;">
                                    @for($j = 1; $j <= 5; $j++)
                                        <svg style="width: 13px; height: 13px; color:{{ $j <= $fb->rating ? '#fbbf24' : '#cbd5e1' }};" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                        </svg>
                                    @endfor
                                    <span style="font-size:0.775rem; margin-left:2px; font-weight:700; color:#475569;">({{ $fb->rating }}/5)</span>
                                </div>
                            </div>
                            <span style="font-size:0.75rem; color:#94a3b8; font-weight:500;">{{ $fb->created_at->translatedFormat('d M Y H:i') }}</span>
                        </div>
                        
                        @if($fb->comment)
                            <p style="color:#334155; line-height:1.55; font-size:0.875rem; margin:0 0 0.75rem 0; word-break:break-word;">
                                {{ $fb->comment }}
                            </p>
                        @else
                            <p style="color:#94a3b8; font-size:0.825rem; font-style:italic; margin:0 0 0.75rem 0;">
                                (ไม่มีข้อความเสนอแนะเพิ่มเติม)
                            </p>
                        @endif

                        @if($fb->ratings && is_array($fb->ratings))
                            <div style="display:flex; gap:12px; flex-wrap:wrap; font-size:0.75rem; color:#64748b; background:#fff; padding:6px 12px; border-radius:6px; border:1px solid #e2e8f0; display:inline-flex;">
                                @if(isset($fb->ratings['content']))
                                    <span>เนื้อหา: <strong>{{ $fb->ratings['content'] }}</strong>★</span>
                                @endif
                                @if(isset($fb->ratings['speaker']))
                                    <span>วิทยากร: <strong>{{ $fb->ratings['speaker'] }}</strong>★</span>
                                @endif
                                @if(isset($fb->ratings['location']))
                                    <span>สถานที่: <strong>{{ $fb->ratings['location'] }}</strong>★</span>
                                @endif
                                @if(isset($fb->ratings['organization']))
                                    <span>การจัดการ: <strong>{{ $fb->ratings['organization'] }}</strong>★</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <p style="text-align:center; padding:2rem; color:#94a3b8; margin:0; font-style:italic; font-size:0.875rem;">ยังไม่มีการตอบกลับหรือข้อเสนอแนะประเมินกิจกรรมนี้</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ═══ คอลัมน์ขวา: คะแนนเฉลี่ยตามหัวข้อประเมิน (1 ส่วนบนหน้าจอใหญ่) ═══ --}}
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        {{-- รายละเอียดคะแนนเฉลี่ยจำแนกรายหัวข้อ --}}
        @if(array_sum($detailedAvg) > 0)
            <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background:#fff; border-radius:12px;">
                <div class="card-header flex items-center gap-2" style="background:#f8fafc; border-bottom:1px solid #f1f5f9; padding:1rem 1.25rem;">
                    <svg style="width:20px; height:20px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span class="font-semi text-sm" style="color:#334155;">ความพึงพอใจแยกตามหัวข้อ</span>
                </div>
                <div class="card-body" style="padding:1.25rem; display:flex; flex-direction:column; gap:0.85rem;">
                    
                    @foreach([
                        ['key' => 'content', 'title' => 'เนื้อหากิจกรรมและการเรียนรู้', 'color' => '#6366f1'],
                        ['key' => 'speaker', 'title' => 'วิทยากร / ผู้บรรยาย / ผู้ดำเนินการ', 'color' => '#8b5cf6'],
                        ['key' => 'location', 'title' => 'สถานที่ / ระบบดิจิทัล / สิ่งอำนวยความสะดวก', 'color' => '#06b6d4'],
                        ['key' => 'organization', 'title' => 'การบริหารจัดการและการประสานงาน', 'color' => '#10b981']
                    ] as $item)
                        @if(($detailedAvg[$item['key']] ?? 0) > 0)
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                                <div style="flex:1; padding-right:8px;">
                                    <span class="text-xs text-muted" style="display:block; font-weight:500;">{{ $item['title'] }}</span>
                                </div>
                                <div style="text-align:right; flex-shrink:0; display:flex; align-items:baseline; gap:3px;">
                                    <span style="font-size:1.25rem; font-weight:800; color:{{ $item['color'] }};">{{ number_format((float)$detailedAvg[$item['key']], 1) }}</span>
                                    <span style="font-size:0.75rem; color:#94a3b8; font-weight:600;">/5.0</span>
                                </div>
                            </div>
                        @endif
                    @endforeach

                </div>
            </div>
        @endif

    </div>
</div>
@endsection
