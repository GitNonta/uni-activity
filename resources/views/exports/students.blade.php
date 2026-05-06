{{-- Excel View: รายชื่อนักศึกษา --}}
<table>
    <thead>
        <tr>
            <th>รหัสนักศึกษา</th>
            <th>ชื่อ-นามสกุล</th>
            <th>คณะ</th>
            <th>สาขาวิชา</th>
            <th>ชั้นปี</th>
            <th>ภาคเรียน</th>
            <th>อีเมล</th>
            <th>สถานะ</th>
            <th>ชั่วโมงทั้งหมด</th>
            <th>กิจกรรมที่เข้าร่วม</th>
            <th>สร้างเมื่อ</th>
        </tr>
    </thead>
    <tbody>
        @foreach($students as $student)
            <tr>
                <td>{{ $student->student_id }}</td>
                <td>{{ $student->full_name }}</td>
                <td>{{ $student->faculty }}</td>
                <td>{{ $student->department }}</td>
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
