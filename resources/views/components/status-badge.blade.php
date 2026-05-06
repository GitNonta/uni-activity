{{-- คอมโพเนนต์แสดงป้ายสถานะกิจกรรม: แปลงสถานะ (upcoming, open, full, ...) เป็นสี + ข้อความไทย --}}
@php
    $map = [
        'upcoming' => ['badge-blue', 'เร็วๆ นี้'],
        'open' => ['badge-green', 'เปิดรับสมัคร'],
        'full' => ['badge-yellow', 'เต็ม'],
        'ongoing' => ['badge-purple', 'กำลังดำเนินการ'],
        'done' => ['badge-gray', 'เสร็จสิ้น'],
        'cancelled' => ['badge-red', 'ยกเลิก'],
    ];
    $info = $map[$status] ?? ['badge-gray', $status];
@endphp
<span class="badge {{ $info[0] }}">{{ $info[1] }}</span>
