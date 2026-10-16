{{-- Excel View: ประวัติการเข้าร่วมกิจกรรมของนักศึกษา --}}
<table>
    <thead>
        <tr>
            <th colspan="9">{{ __('ประวัติการเข้าร่วมกิจกรรม -') }}{{ $student->full_name }} ({{ $student->student_id }})</th>
        </tr>
        <tr>
            <th>{{ __('รหัสกิจกรรม') }}</th>
            <th>{{ __('ชื่อกิจกรรม') }}</th>
            <th>{{ __('หมวดหมู่') }}</th>
            <th>{{ __('วันที่จัด') }}</th>
            <th>{{ __('ชั่วโมง') }}</th>
            <th>{{ __('เวลาเช็คอิน') }}</th>
            <th>{{ __('สถานะ') }}</th>
            <th>{{ __('หมายเหตุ') }}</th>
            <th>{{ __('บันทึกเมื่อ') }}</th>
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
                            {{ __('อนุมัติ') }}
                            @break
                        @case('pending')
                            {{ __('รออนุมัติ') }}
                            @break
                        @case('rejected')
                            {{ __('ปฏิเสธ') }}
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
            <td colspan="4"><strong>{{ __('รวมทั้งหมด') }}</strong></td>
            <td><strong>{{ $attendances->sum('activity.activity_hours') }} {{ __('ชั่วโมง') }}</strong></td>
            <td colspan="4"><strong>{{ $attendances->count() }} {{ __('กิจกรรม') }}</strong></td>
        </tr>
    </tfoot>
</table>
