{{-- Excel View: รายชื่อนักศึกษา --}}
<table>
    <thead>
        <tr>
            <th>{{ __('รหัสนักศึกษา') }}</th>
            <th>{{ __('ชื่อ-นามสกุล') }}</th>
            <th>{{ __('คณะ') }}</th>
            <th>{{ __('สาขาวิชา') }}</th>
            <th>{{ __('ชั้นปี') }}</th>
            <th>{{ __('ภาคเรียน') }}</th>
            <th>{{ __('อีเมล') }}</th>
            <th>{{ __('สถานะ') }}</th>
            <th>{{ __('ชั่วโมงทั้งหมด') }}</th>
            <th>{{ __('กิจกรรมที่เข้าร่วม') }}</th>
            <th>{{ __('สร้างเมื่อ') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($students as $student)
            <tr>
                <td>{{ $student->student_id }}</td>
                <td>{{ $student->full_name }}</td>
                <td>{{ __($student->faculty) }}</td>
                <td>{{ __($student->department) }}</td>
                <td>{{ $student->year }}</td>
                <td>{{ $student->program }}</td>
                <td>{{ $student->email }}</td>
                <td>{{ $student->is_active ? 'ใช้งาน' : 'ระงับ' }}</td>
                <td>{{ number_format($student->totalHours(), 1) }}</td>
                <td>{{ $student->attendances->count() }}</td>
                <td>{{ $student->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
