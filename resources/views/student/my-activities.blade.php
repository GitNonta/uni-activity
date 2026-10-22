{{-- หน้ากิจกรรมของฉัน: ภารกิจที่ต้องทำ + รายการลงทะเบียน (การ์ดมีรูปย่อ กดเข้าดูรายละเอียดได้) --}}
@extends('layouts.app')
@section('title', __('กิจกรรมของฉัน'))

@section('content')
<div class="page-head flex items-center justify-between mb-4">
    <h1 class="font-bold">{{ __('กิจกรรมของฉัน') }}</h1>
    <a href="{{ route('student.calendar') }}" class="btn btn-outline btn-sm">
        <svg style="width:15px;height:15px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>{{ __('ปฏิทิน') }}</a>
</div>

{{-- ── ภารกิจที่ต้องทำ ── --}}
@if($todos->isNotEmpty())
<div class="mb-5">
    <div class="flex items-center gap-2 mb-3">
        <svg style="width:18px;height:18px;color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 4h6m-6 4h4"/>
        </svg>
        <h2 class="font-bold" style="font-size:1rem; line-height:1.5;">{{ __('ภารกิจที่ต้องทำ') }}
            <span style="background:#b45309;color:#fff;border-radius:999px;padding:1px 8px;font-size:.75rem;margin-left:6px;line-height:1.5;">{{ $todos->count() }}</span>
        </h2>
    </div>

    <div class="todo-scroll">
        @foreach($todos as $todo)
            @php $tAct = $todo['activity']; @endphp
            <div class="todo-card"
                 style="--todo-color: {{ $todo['color'] }};--todo-glow: {{ $todo['color'] }}33;--todo-bg-soft: {{ $todo['color'] }}14;"
                 role="link" tabindex="0"
                 onclick="if(!event.target.closest('a,button,form')) window.location.href='{{ route('activities.show', $tAct) }}'"
                 onkeydown="if(event.key==='Enter' && !event.target.closest('a,button,form')){window.location.href='{{ route('activities.show', $tAct) }}'}">
                {{-- รูปย่อ --}}
                <div class="todo-cover">
                    @if($tAct->image_path)
                        <img src="{{ Storage::url($tAct->image_path) }}" alt="{{ $tAct->title }}" loading="lazy" decoding="async">
                    @else
                        <span class="todo-cover-ph">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                    @endif
                </div>

                <div class="todo-body">
                    <div class="todo-label">
                        <span class="todo-icon">
                            @if($todo['icon'] === 'check')
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            @elseif($todo['icon'] === 'star')
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            @else
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </span>
                        <span class="todo-label-text" style="color:{{ $todo['color'] }};">{{ $todo['label'] }}</span>
                    </div>

                    <p class="todo-title" title="{{ $tAct->title }}">{{ $tAct->title }}</p>

                    <p class="todo-meta">
                        <span class="mi-meta-item">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>{{ $tAct->activity_date->format('d/m/Y') }}</span>
                        </span>
                        @if($tAct->location)
                            <span class="mi-meta-item mi-meta-item-loc">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>{{ $tAct->location }}</span>
                            </span>
                        @endif
                    </p>

                    <a href="{{ $todo['action_url'] }}" class="btn btn-sm todo-cta" style="background:{{ $todo['color'] }};">{{ $todo['action_label'] }}</a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── กิจกรรมที่เข้าร่วมผ่าน Walk-in ── --}}
@forelse($walkInAttendances as $att)
    @php $a = $att->activity; @endphp
    <div class="card mi-card" role="link" tabindex="0"
         onclick="if(!event.target.closest('a,button,form')) window.location.href='{{ route('activities.show', $a) }}'"
         onkeydown="if(event.key==='Enter' && !event.target.closest('a,button,form')){window.location.href='{{ route('activities.show', $a) }}'}">
        <div class="mi-thumb">
            @if($a->image_path)
                <img src="{{ Storage::url($a->image_path) }}" alt="{{ $a->title }}" loading="lazy" decoding="async">
            @else
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            @endif
        </div>

        <div class="mi-body">
            <div class="mi-badges">
                @include('components.status-badge', ['status' => $a->computed_status])
                <span class="badge mi-badge-walkin">Walk-in</span>
                @if($att->status === 'approved')
                    <span class="badge badge-green">✓ {{ __('สำเร็จ') }}</span>
                @elseif($att->status === 'pending')
                    <span class="badge badge-yellow">{{ __('รออนุมัติ') }}</span>
                @elseif($att->status === 'rejected')
                    <span class="badge badge-red">{{ __('ถูกปฏิเสธ') }}</span>
                @endif
            </div>

            <h3 class="mi-title" title="{{ $a->title }}">{{ $a->title }}</h3>

            <p class="mi-meta">
                <span class="mi-meta-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>{{ $a->activity_date->format('d/m/Y') }}</span>
                </span>
                @if($a->location)
                    <span class="mi-meta-item mi-meta-item-loc">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ $a->location }}</span>
                    </span>
                @endif
            </p>

            <div class="mi-actions">
                @if($a->feedbacks->where('user_id', auth()->id())->count() > 0)
                    <span class="badge badge-orange">{{ __('ประเมินแล้ว') }}</span>
                @elseif($att->status === 'approved' && $a->computed_status === 'done')
                    <a href="{{ route('feedback.create', $att->activity_id) }}" class="btn btn-primary btn-sm">{{ __('ประเมิน') }}</a>
                @endif
                <span class="mi-more">{{ __('ดูรายละเอียด') }}
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </div>
        </div>
    </div>
@empty
    {{-- แสดงเฉพาะเมื่อไม่มีรายการลงทะเบียนเลย ไม่ให้กินพื้นที่หน้าเพจ --}}
    @if($registrations->isEmpty())
        <x-empty-state
            icon="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
            title="{{ __('ยังไม่มีกิจกรรม Walk-in') }}"
            description="{{ __('Walk-in คือการเช็คอินกิจกรรมโดยตรงโดยไม่ต้องลงทะเบียนล่วงหน้า') }}"
            size="sm"
        />
    @endif
@endforelse

{{-- ── กิจกรรมที่ลงทะเบียนปกติ ── --}}
@if($todos->isNotEmpty() && $registrations->isNotEmpty())
    <h2 class="font-bold mb-3 mt-2" style="font-size:.95rem;color:var(--text-muted);">{{ __('รายการลงทะเบียนทั้งหมด') }}</h2>
@endif

@forelse($registrations as $reg)
    @php
        $a = $reg->activity;
        $att = $attendanceMap->get($reg->activity_id);
        $hasFeedback = in_array($reg->activity_id, $feedbackDoneIds);
        $status = $a->computed_status;
        $checkinOpen = $a->allow_early_checkin ||
            (now() >= $a->checkin_open_at && now() <= $a->checkin_close_at);
        $sl = ['pending'=>__('รออนุมัติลงทะเบียน'),'approved'=>__('ลงทะเบียนสำเร็จ'),'cancelled'=>__('ยกเลิก'),'rejected'=>__('ปฏิเสธ'), 'waitlisted'=>__('รอคิว')];
        $sc = ['pending'=>'badge-yellow','approved'=>'badge-green','cancelled'=>'badge-gray','rejected'=>'badge-red','waitlisted'=>'badge-yellow'];
        $accent = (!$att && $reg->status === 'approved' && $checkinOpen) ? '#16a34a' : null;
    @endphp
    <div class="card mi-card" role="link" tabindex="0" @if($accent) style="border-left:3px solid {{ $accent }};" @endif
         onclick="if(!event.target.closest('a,button,form')) window.location.href='{{ route('activities.show', $a) }}'"
         onkeydown="if(event.key==='Enter' && !event.target.closest('a,button,form')){window.location.href='{{ route('activities.show', $a) }}'}">
        <div class="mi-thumb">
            @if($a->image_path)
                <img src="{{ Storage::url($a->image_path) }}" alt="{{ $a->title }}" loading="lazy" decoding="async">
            @else
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            @endif
        </div>

        <div class="mi-body">
            <div class="mi-badges">
                @include('components.status-badge', ['status' => $status])
                <span class="badge {{ $sc[$reg->status] ?? 'badge-gray' }}">{{ $sl[$reg->status] ?? $reg->status }}</span>
                @if($att && $att->status === 'approved')
                    <span class="badge badge-orange">✓ {{ __('เช็คอินแล้ว') }}</span>
                @elseif($att && $att->status === 'pending')
                    <span class="badge badge-yellow">{{ __('รออนุมัติเช็คอิน') }}</span>
                @endif
            </div>

            <h3 class="mi-title" title="{{ $a->title }}">{{ $a->title }}</h3>

            <p class="mi-meta">
                <span class="mi-meta-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>{{ $a->activity_date->format('d/m/Y') }}</span>
                </span>
                <span class="mi-meta-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ \Carbon\Carbon::parse($a->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($a->end_time)->format('H:i') }}</span>
                </span>
                @if($a->location)
                    <span class="mi-meta-item mi-meta-item-loc">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ $a->location }}</span>
                    </span>
                @endif
            </p>

            <div class="mi-actions">
                @if(!$att && $reg->status === 'approved' && $checkinOpen)
                    <span class="badge badge-orange">{{ __('สแกน QR หน้างาน') }}</span>
                @elseif($att && $att->status === 'approved' && !$hasFeedback && $status === 'done')
                    <a href="{{ route('feedback.create', $reg->activity_id) }}" class="btn btn-primary btn-sm">{{ __('ประเมิน') }}</a>
                @elseif($att && $att->status === 'approved' && $hasFeedback)
                    <span class="badge badge-orange">{{ __('ประเมินแล้ว') }}</span>
                @endif

                @if($reg->status === 'approved' && !$att && in_array($status, ['upcoming','open','ongoing']))
                    <form method="POST" action="{{ route('registrations.destroy', $reg->id) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-sm mi-btn-cancel" onclick="return confirm('{{ __('ยืนยันยกเลิก?') }}')">{{ __('ยกเลิก') }}</button>
                    </form>
                @endif

                <span class="mi-more">{{ __('ดูรายละเอียด') }}
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </div>
        </div>
    </div>
@empty
    <x-empty-state
        icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
        title="{{ __('ยังไม่มีกิจกรรมที่ลงทะเบียน') }}"
        description="{{ __('เริ่มต้นสะสมชั่วโมงกิจกรรมโดยการลงทะเบียนกิจกรรมที่สนใจ') }}"
        actionLabel="{{ __('ดูกิจกรรมทั้งหมด') }}"
        actionUrl="{{ route('activities.index') }}"
        size="lg"
    />
@endforelse
@endsection

@push('styles')
<style>
    /* ═══════════════════════════════════════════════════════════════
       กิจกรรมของฉัน — การ์ดกิจกรรมแบบมีรูปย่อ + กดทั้งใบเข้าดูรายละเอียด
       ═══════════════════════════════════════════════════════════════ */

    /* ── หัวเพจ ── */
    .page-head { flex-wrap: wrap; gap: .5rem .75rem; }
    .page-head h1 { font-size: 1.5rem; }
    @media (max-width: 480px) {
        .page-head h1 { font-size: 1.28rem; }
        .page-head .btn { padding: .4rem .7rem; font-size: .78rem; }
    }

    /* ── การ์ดกิจกรรม (ลงทะเบียน / walk-in) ── */
    .mi-card {
        flex-direction: row;
        align-items: flex-start;
        gap: .7rem;
        padding: .7rem;
        margin-bottom: .5rem;
        cursor: pointer;
        transition: transform .12s ease, box-shadow .12s ease;
    }
    .mi-card:active { transform: scale(.994); }
    .mi-card:focus-visible { outline: 2px solid var(--brand, #ea580c); outline-offset: 2px; }

    .mi-thumb {
        flex: 0 0 76px;
        width: 76px;
        height: 76px;
        border-radius: 12px;
        overflow: hidden;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .mi-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .mi-thumb svg { width: 26px; height: 26px; color: #94a3b8; }

    .mi-body { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: .2rem; }

    .mi-badges { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .mi-badges .badge { font-size: .68rem; padding: .15rem .5rem; border-radius: 999px; white-space: nowrap; }
    .mi-badge-walkin { background: #f59e0b; color: #fff; }

    .mi-title {
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.45;
        color: var(--text-main);
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        overflow-wrap: anywhere;
    }

    .mi-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px 5px;
        font-size: .75rem;
        color: var(--text-muted);
        margin: 0;
        overflow-wrap: anywhere;
    }
    .mi-meta svg { width: 13px; height: 13px; flex: 0 0 auto; }
    .mi-meta-item,
    .todo-meta .mi-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        max-width: 100%;
        white-space: nowrap;
    }
    .mi-meta-item > span { min-width: 0; }
    .mi-meta-item-loc { white-space: normal; overflow-wrap: anywhere; }

    .mi-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .4rem;
        margin-top: .3rem;
    }
    .mi-actions .btn { min-height: 34px; font-size: .74rem; padding: .3rem .7rem; }
    .mi-actions .badge { font-size: .7rem; padding: .28rem .6rem; }
    .mi-btn-cancel { color: var(--tint-red-text); border-color: var(--tint-red-border); }

    .mi-more {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        margin-left: auto;
        font-size: .72rem;
        font-weight: 600;
        color: var(--brand, #ea580c);
        white-space: nowrap;
    }
    .mi-more svg { width: 13px; height: 13px; }

    @media (min-width: 768px) {
        .mi-card { padding: .85rem; gap: .9rem; }
        .mi-thumb { flex: 0 0 96px; width: 96px; height: 96px; }
        .mi-title { font-size: 1.02rem; -webkit-line-clamp: 2; }
        .mi-meta { font-size: .8rem; }
    }
    @media (max-width: 380px) {
        .mi-thumb { flex: 0 0 62px; width: 62px; height: 62px; border-radius: 10px; }
        .mi-title { font-size: .9rem; }
    }

    /* ── การ์ดภารกิจ (carousel) ── */
    .todo-scroll {
        display: flex;
        gap: .75rem;
        overflow-x: auto;
        padding: .25rem .25rem .9rem;
        margin: 0 -.25rem;
        scroll-snap-type: x mandatory;
        scroll-padding-left: .25rem;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: none;
    }
    .todo-scroll::-webkit-scrollbar { display: none; }

    .todo-card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 250px;
        max-width: 300px;
        flex: 1 0 80%;
        scroll-snap-align: start;
        overflow: hidden;
        border-radius: 16px;
        background: #fff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(15,23,42,.06);
        cursor: pointer;
    }
    .todo-card:focus-visible { outline: 2px solid var(--brand, #ea580c); outline-offset: 2px; }
    .todo-card:active { transform: scale(.99); }

    .todo-cover {
        position: relative;
        height: 92px;
        background: linear-gradient(135deg, var(--todo-bg-soft, #fff7ed), #eef2f7);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .todo-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .todo-cover-ph svg { width: 30px; height: 30px; color: var(--todo-color, #b45309); opacity: .45; }

    .todo-body {
        display: flex;
        flex-direction: column;
        gap: .25rem;
        padding: .7rem .85rem .85rem;
        min-width: 0;
    }
    .todo-label { display: flex; align-items: center; gap: .35rem; }
    .todo-icon {
        width: 22px; height: 22px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: var(--todo-color, #b45309);
        color: #fff;
        flex-shrink: 0;
    }
    .todo-icon svg { width: 12px; height: 12px; }
    .todo-label-text { font-size: .73rem; font-weight: 700; letter-spacing: .01em; }

    .todo-title {
        font-size: .92rem;
        font-weight: 700;
        line-height: 1.45;
        color: var(--text-main);
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        overflow-wrap: anywhere;
    }

    .todo-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px 5px;
        font-size: .73rem;
        color: var(--text-muted);
        margin: 0 0 .35rem;
        overflow-wrap: anywhere;
    }
    .todo-meta svg { width: 13px; height: 13px; flex: 0 0 auto; }
    .todo-meta-loc {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-width: 0;
    }

    .todo-cta {
        width: 100%;
        justify-content: center;
        color: #fff !important;
        border-radius: 10px;
        font-size: .78rem;
        font-weight: 600;
        padding: .45rem .75rem;
        min-height: 38px;
    }

    /* มือถือ: การ์ดเต็มความกว้างหน้าจอ 1 ใบต่อการปัด */
    @media (max-width: 767px) {
        .todo-scroll { margin: 0; padding: .2rem 0 .85rem; scroll-padding-left: 0; }
        .todo-card { flex: 0 0 100%; min-width: 100%; max-width: 100%; scroll-snap-stop: always; }
        .todo-cover { height: 108px; }
    }
    /* แท็บเล็ต: 2 ใบต่อการปัด */
    @media (min-width: 768px) and (max-width: 1024px) {
        .todo-card { flex: 0 0 calc(50% - .375rem); min-width: calc(50% - .375rem); max-width: calc(50% - .375rem); }
    }
    /* มีการ์ดเดียว: ไม่ต้องแบ่งครึ่งจอ เว้นที่ว่าง */
    @media (min-width: 768px) {
        .todo-card:only-child { flex: 0 0 420px; min-width: 0; max-width: 100%; }
    }

    /* โหมดมืด */
    html[data-theme="dark"] .todo-card { background: #1c1c1f; border-color: #333; }
    html[data-theme="dark"] .mi-thumb { background: #27272a; }
    html[data-theme="dark"] .mi-card { background: #1c1c1f; }
</style>
@endpush

@section('scripts')
{{-- ไม่ต้องใช้ QR Modal แล้ว --}}
@endsection
