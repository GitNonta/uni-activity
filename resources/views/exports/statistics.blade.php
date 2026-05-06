{{-- Excel View: สถิติระบบ --}}
<table>
    <thead>
        <tr>
            <th colspan="3">สถิติระบบจัดการกิจกรรมนักศึกษา</th>
        </tr>
        @if($dateFrom || $dateTo)
            <tr>
                <th colspan="3">
                    ช่วงวันที่: {{ $dateFrom ? $dateFrom->format('d/m/Y') : 'เริ่มต้น' }} 
                    ถึง {{ $dateTo ? $dateTo->format('d/m/Y') : 'ปัจจุบัน' }}
                </th>
            </tr>
        @endif
    </thead>
    <tbody>
        <tr>
            <td><strong>นักศึกษาทั้งหมด</strong></td>
            <td>{{ number_format($totalStudents) }} คน</td>
            <td>{{ $totalStudents > 0 ? number_format(($activeStudents / $totalStudents) * 100, 1) : 0 }}%</td>
        </tr>
        <tr>
            <td><strong>นักศึกษาที่ใช้งานได้</strong></td>
            <td>{{ number_format($activeStudents) }} คน</td>
            <td>{{ $activeStudents > 0 ? number_format(($activeStudents / $totalStudents) * 100, 1) : 0 }}%</td>
        </tr>
        <tr>
            <td><strong>กิจกรรมทั้งหมด</strong></td>
            <td>{{ number_format($totalActivities) }} กิจกรรม</td>
            <td>-</td>
        </tr>
        <tr>
            <td><strong>กิจกรรมที่เสร็จสิ้น</strong></td>
            <td>{{ number_format($completedActivities) }} กิจกรรม</td>
            <td>{{ $totalActivities > 0 ? number_format(($completedActivities / $totalActivities) * 100, 1) : 0 }}%</td>
        </tr>
        <tr>
            <td><strong>ชั่วโมงกิจกรรมรวม</strong></td>
            <td>{{ number_format($totalHours, 1) }} ชั่วโมง</td>
            <td>-</td>
        </tr>
        <tr>
            <td><strong>การลงทะเบียนทั้งหมด</strong></td>
            <td>{{ number_format($totalRegistrations) }} ครั้ง</td>
            <td>-</td>
        </tr>
        <tr>
            <td><strong>การเข้าร่วมทั้งหมด</strong></td>
            <td>{{ number_format($totalAttendances) }} ครั้ง</td>
            <td>{{ $totalRegistrations > 0 ? number_format(($totalAttendances / $totalRegistrations) * 100, 1) : 0 }}%</td>
        </tr>
        <tr>
            <td><strong>Feedback ที่ได้รับ</strong></td>
            <td>{{ number_format($totalFeedbacks) }} รายการ</td>
            <td>-</td>
        </tr>
        <tr>
            <td><strong>คะแนนเฉลี่ย</strong></td>
            <td>{{ $averageRating ? number_format($averageRating, 1) . ' ⭐' : '-' }}</td>
            <td>-</td>
        </tr>
    </tbody>
</table>

@if($facultyStats->count() > 0)
<table style="margin-top: 20px;">
    <thead>
        <tr>
            <th colspan="3">สถิติตามคณะ</th>
        </tr>
    </thead>
    <tbody>
        @foreach($facultyStats as $stat)
            <tr>
                <td>{{ $stat->faculty }}</td>
                <td>{{ number_format($stat->count) }} คน</td>
                <td>{{ $activeStudents > 0 ? number_format(($stat->count / $activeStudents) * 100, 1) : 0 }}%</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

@if($yearStats->count() > 0)
<table style="margin-top: 20px;">
    <thead>
        <tr>
            <th colspan="3">สถิติตามชั้นปี</th>
        </tr>
    </thead>
    <tbody>
        @foreach($yearStats as $stat)
            <tr>
                <td>ปี {{ $stat->year }}</td>
                <td>{{ number_format($stat->count) }} คน</td>
                <td>{{ $activeStudents > 0 ? number_format(($stat->count / $activeStudents) * 100, 1) : 0 }}%</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
