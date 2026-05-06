{{-- Excel View: ประวัติการเข้าร่วมกิจกรรมของนักศึกษา --}}
<table>
    <thead>
        <tr>
            <th colspan="9">ประวัติการเข้าร่วมกิจกรรม - {{ $student->full_name }} ({{ $student->student_id }})</th>
        </tr>
        <tr>
            <th>รหัสกิจกรรม</th>
            <th>ชื่อกิจกรรม</th>
            <th>หมวดหมู่</th>
            <th>วันที่จัด</th>
            <th>ชั่วโมง</th>
            <th>เวลาเช็คอิน</th>
            <th>สถานะ</th>
            <th>หมายเหตุ</th>
            <th>บันทึกเมื่อ</th>
        </tr>
    </thead>
    <tbody>
        @foreach($attendances as $attendance)
            <tr>
                <td>{{ $attendance->activity_id }}</td>
                <td>{{ $attendance->activity->title }}</td>
                <td>{{ $attendance->activity->category->name ?? '-' }}</td>
                <td>{{ $attendance->activity->activity_date->format('d/m/Y') }}</td>
                <td>{{ $attendance->activity->activity_hours }}</td>
                <td>{{ $attendance->checked_in_at ? $attendance->checked_in_at->format('H:i') : '-' }}</td>
                <td>
                    @switch($attendance->status)
                        @case('approved')
                            อนุมัติ
                            @break
                        @case('pending')
                            รออนุมัติ
                            @break
                        @case('rejected')
                            ปฏิเสธ
                            @break
                        @default
                            {{ $attendance->status }}
                    @endswitch
                </td>
                <td>{{ $attendance->remark ?? '-' }}</td>
                <td>{{ $attendance->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>รวมทั้งหมด</strong></td>
            <td><strong>{{ $attendances->sum('activity.activity_hours') }} ชั่วโมง</strong></td>
            <td colspan="4"><strong>{{ $attendances->count() }} กิจกรรม</strong></td>
        </tr>
    </tfoot>
</table>
