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
                <td>{!! $activity->average_rating ? number_format($activity->average_rating, 1) . ' <svg style="width:12px;height:12px;display:inline;color:#eab308;vertical-align:-1px;" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' : '-' !!}</td>
                <td>{{ $activity->feedback_count }}</td>
                <td>{{ $activity->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
