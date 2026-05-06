{{-- Excel View: รายการกิจกรรม --}}
<table>
    <thead>
        <tr>
            <th>รหัสกิจกรรม</th>
            <th>ชื่อกิจกรรม</th>
            <th>หมวดหมู่</th>
            <th>วันที่จัด</th>
            <th>ชั่วโมง</th>
            <th>จำนวน</th>
            <th>เต็ม</th>
            <th>เหลือ</th>
            <th>สถานะ</th>
            <th>คะแนนเฉลี่ย</th>
            <th>Feedback</th>
            <th>สร้างเมื่อ</th>
        </tr>
    </thead>
    <tbody>
        @foreach($activities as $activity)
            <tr>
                <td>{{ $activity->id }}</td>
                <td>{{ $activity->title }}</td>
                <td>{{ $activity->category->name ?? '-' }}</td>
                <td>{{ $activity->activity_date->format('d/m/Y') }}</td>
                <td>{{ $activity->activity_hours }}</td>
                <td>{{ $activity->max_participants }}</td>
                <td>{{ $activity->registrations->count() }}</td>
                <td>{{ $activity->remainingSlots() }}</td>
                <td>
                    @switch($activity->status)
                        @case('upcoming')
                            จะมาถึง
                            @break
                        @case('ongoing')
                            กำลังดำเนินการ
                            @break
                        @case('completed')
                            เสร็จสิ้น
                            @break
                        @case('cancelled')
                            ยกเลิก
                            @break
                        @default
                            {{ $activity->status }}
                    @endswitch
                </td>
                <td>{{ $activity->average_rating ? number_format($activity->average_rating, 1) . ' ⭐' : '-' }}</td>
                <td>{{ $activity->feedback_count }}</td>
                <td>{{ $activity->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
