{{-- คอมโพเนนต์แสดงป้ายสถานะกิจกรรม: แปลงสถานะ (upcoming, open, full, ...) เป็นสี + ข้อความไทย --}}
@php
    $map = [
        'upcoming' => ['badge-orange', __('st.upcoming')],
        'open' => ['badge-green', __('st.open')],
        'full' => ['badge-yellow', __('st.full')],
        'ongoing' => ['badge-orange', __('st.ongoing')],
        'done' => ['badge-gray', __('st.done')],
        'cancelled' => ['badge-red', __('st.cancelled')],
    ];
    $info = $map[$status] ?? ['badge-gray', $status];
@endphp
<span class="badge {{ $info[0] }}">{{ $info[1] }}</span>
