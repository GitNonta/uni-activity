{{-- Excel View: รายละเอียดกิจกรรมและผู้เข้าร่วม --}}
<table>
    <thead>
        <tr>
            <th colspan="11">รายละเอียดกิจกรรม - {{ $activity->title }}</th>
        </tr>
        <tr>
            <th colspan="2">วันที่จัด</th>
            <th colspan="2">{{ $activity->activity_date->format('d/m/Y') }}</th>
            <th colspan="2">หมวดหมู่</th>
            <th colspan="3">{{ $activity->category->name ?? '-' }}</th>
            <th colspan="2">สถานะ</th>
        </tr>
        <tr>
            <th colspan="2">ชั่วโมง</th>
            <th colspan="2">{{ $activity->activity_hours }}</th>
            <th colspan="2">จำนวน</th>
            <th colspan="3">{{ $activity->registrations->count() }}/{{ $activity->max_participants }}</th>
            <th colspan="2">{{ $activity->status }}</th>
        </tr>
        <tr>
            <th>รหัสนักศึกษา</th>
            <th>ชื่อ-นามสกุล</th>
            <th>คณะ</th>
            <th>ชั้นปี</th>
            <th>วันที่ลงทะเบียน</th>
            <th>วันที่เข้าร่วม</th>
            <th>ชั่วโมง</th>
            <th>สถานะ</th>
            <th>เวลาเช็คอิน</th>
            <th>คะแนนประเมิน</th>
            <th>ความคิดเห็น</th>
        </tr>
    </thead>
    <tbody>
        @foreach($activity->registrations as $registration)
            @php
                $attendance = $activity->attendances->where('user_id', $registration->user_id)->first();
                $feedback = $activity->feedbacks->where('user_id', $registration->user_id)->first();
            @endphp
            <tr>
                <td>{{ $registration->user->student_id }}</td>
                <td>{{ $registration->user->full_name }}</td>
                <td>{{ $registration->user->faculty }}</td>
                <td>{{ $registration->user->year }}</td>
                <td>{{ $registration->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $attendance ? $attendance->created_at->format('d/m/Y') : '-' }}</td>
                <td>{{ $attendance ? $activity->activity_hours : '-' }}</td>
                <td>
                    @if($attendance)
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
                    @else
                        ไม่เข้าร่วม
                    @endif
                </td>
                <td>{{ $attendance && $attendance->checked_in_at ? $attendance->checked_in_at->format('H:i') : '-' }}</td>
                <td>{!! $feedback ? $feedback->rating . ' <svg style="width:12px;height:12px;display:inline;color:#eab308;vertical-align:-1px;" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' : '-' !!}</td>
                <td>{{ $feedback ? Str::limit($feedback->comment, 50) : '-' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>สรุป</strong></td>
            <td><strong>{{ $activity->registrations->count() }} คน</strong></td>
            <td><strong>{{ $activity->attendances->count() }} คน</strong></td>
            <td><strong>{{ $activity->attendances->sum('activity.activity_hours') }} ชม.</strong></td>
            <td><strong>{{ $activity->attendances->where('status', 'approved')->count() }} อนุมัติ</strong></td>
            <td><strong>{{ $activity->feedback_count }} Feedback</strong></td>
            <td><strong>{!! $activity->average_rating ? number_format($activity->average_rating, 1) . ' <svg style="width:12px;height:12px;display:inline;color:#eab308;vertical-align:-1px;" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' : '-' !!}</strong></td>
        </tr>
    </tfoot>
</table>
