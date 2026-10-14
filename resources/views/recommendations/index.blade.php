{{-- (7) หน้ากิจกรรมที่ระบบแนะนำ: จากผู้ที่ติดตาม + หมวดจากประวัติลงทะเบียน --}}
@extends('layouts.app')

@section('title', __('กิจกรรมที่แนะนำ'))

@section('content')
<div class="act-header-row">
    <div>
        <h1 class="text-2xl font-bold mb-1">{{ __('กิจกรรมที่แนะนำ') }}</h1>
        <p class="text-sm text-gray-500">{{ __('จากผู้ที่คุณติดตาม และหมวดกิจกรรมที่คุณเคยลงทะเบียน') }}</p>
    </div>
</div>

@forelse($activities as $activity)
    <a href="{{ route('activities.show', $activity) }}"
        class="block border rounded-lg p-4 mb-3 hover:bg-gray-50 transition">
        <div class="flex justify-between items-start gap-3">
            <div>
                <div class="font-semibold">{{ $activity->title }}</div>
                <div class="text-sm text-gray-600 mt-1">
                    {{ $activity->category->name ?? 'ไม่ระบุหมวดหมู่' }}
                    · {{ $activity->activity_date?->format('d M Y') ?? '' }}
                    · {{ $activity->creator->name ?? '' }}
                </div>
            </div>
            <span class="text-xs bg-green-100 text-green-700 rounded px-2 py-1 whitespace-nowrap">{{ __('เปิดรับสมัคร') }}</span>
        </div>
    </a>
@empty
    <div class="text-gray-500 py-8 text-center">{{ __('ยังไม่มีกิจกรรมที่แนะนำได้ — ลองติดตามมหาวิทยาลัย/มิติหรือลงทะเบียนกิจกรรมแรก แล้วระบบจะเริ่มแนะนำให้') }}    </div>
@endforelse
@endsection
