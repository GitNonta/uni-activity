{{-- หน้ารายชื่อผู้ลงทะเบียน (Admin): ตารางผู้สมัคร + ปุ่มอนุมัติ/ปฏิเสธ --}}
@extends('layouts.admin')
@section('title', 'ผู้เข้าร่วม - ' . $activity->title)

@section('content')
<a href="{{ route('admin.activities.show', $activity->id) }}" class="text-sm text-primary">&larr; กลับ</a>
<h1 class="font-bold mt-2 mb-1" style="font-size:1.25rem;">ผู้เข้าร่วม: {{ $activity->title }}</h1>
<p class="text-muted text-sm mb-4">{{ $activity->getRegisteredCount() }}/{{ $activity->max_participants }} คน</p>

{{-- ตารางผู้ลงทะเบียน: รหัส, ชื่อ, คณะ, สถานะ, ปุ่มอนุมัติ/ปฏิเสธ (แสดงเฉพาะสถานะ pending) --}}
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>รหัสนักศึกษา</th>
                    <th>ชื่อ</th>
                    <th>คณะ</th>
                    <th>ภาคเรียน</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                {{-- ผู้ลงทะเบียนปกติ --}}
                @forelse($activity->registrations as $reg)
                <tr>
                    <td style="font-family:monospace;">{{ $reg->user->student_id }}</td>
                    <td>{{ $reg->user->full_name }}</td>
                    <td class="text-muted">{{ $reg->user->faculty }}</td>
                    <td class="text-sm">
                        @if($reg->user->program)
                            <span class="text-xs {{ $reg->user->program === 'กศ.บป.' ? 'text-purple-600' : 'text-blue-600' }}" style="font-weight:600;">{{ $reg->user->program }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">
                        @php
                            $sc = ['pending'=>'badge-yellow','approved'=>'badge-green','cancelled'=>'badge-gray','rejected'=>'badge-red'];
                            $statusLabels = ['pending'=>'รออนุมัติ','approved'=>'ลงทะเบียนแล้ว','cancelled'=>'ยกเลิก','rejected'=>'ปฏิเสธ'];
                            
                            // ตรวจสอบว่ามี attendance ที่ approved หรือไม่
                            $hasApprovedAttendance = $reg->user->attendances()
                                ->where('activity_id', $reg->activity_id)
                                ->where('status', 'approved')
                                ->exists();
                            
                            // ถ้าลงทะเบียนแล้วและมี attendance approved = สำเร็จ
                            if ($reg->status === 'approved' && $hasApprovedAttendance) {
                                $displayStatus = 'สำเร็จ';
                                $badgeClass = 'badge-blue';
                            } else {
                                $displayStatus = $statusLabels[$reg->status] ?? $reg->status;
                                $badgeClass = $sc[$reg->status] ?? 'badge-gray';
                            }
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $displayStatus }}</span>
                    </td>
                    <td class="text-right">
                        @if($reg->status === 'pending')
                        <form method="POST" action="{{ route('admin.registrations.approve', $reg->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">อนุมัติ</button>
                        </form>
                        <form method="POST" action="{{ route('admin.registrations.reject', $reg->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">ปฏิเสธ</button>
                        </form>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                {{-- ถ้าไม่มีการลงทะเบียน ให้แสดงเฉพาะ walk-in --}}
                @endphp
                @endphp
                @endforelse
                
                {{-- ผู้เข้าร่วมผ่าน Walk-in Check-in --}}
                @php
                    $walkInAttendances = \App\Models\Attendance::with('user')
                        ->where('activity_id', $activity->id)
                        ->where('method', 'walk_in')
                        ->whereNotIn('user_id', $activity->registrations->pluck('user_id'))
                        ->orderByDesc('created_at')
                        ->get();
                @endphp
                
                @forelse($walkInAttendances as $att)
                <tr style="background:#fef3c7;">
                    <td style="font-family:monospace;">{{ $att->user->student_id }}</td>
                    <td>
                        {{ $att->user->full_name }}
                        <span style="background:#f59e0b;color:white;padding:2px 6px;border-radius:4px;font-size:0.7rem;font-weight:600;margin-left:0.5rem;">Walk-in</span>
                    </td>
                    <td class="text-muted">{{ $att->user->faculty }}</td>
                    <td class="text-sm">
                        @if($att->user->program)
                            <span class="text-xs {{ $att->user->program === 'กศ.บป.' ? 'text-purple-600' : 'text-blue-600' }}" style="font-weight:600;">{{ $att->user->program }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">
                        @if($att->status === 'approved')
                            <span class="badge badge-green">สำเร็จ</span>
                        @elseif($att->status === 'pending')
                            <span class="badge badge-yellow">รอการอนุมัติ</span>
                        @elseif($att->status === 'rejected')
                            <span class="badge badge-red">ถูกปฏิเสธ</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($att->status === 'pending')
                        <form method="POST" action="{{ route('admin.attendances.approve', $att->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">อนุมัติ</button>
                        </form>
                        <form method="POST" action="{{ route('admin.attendances.reject', $att->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">ปฏิเสธ</button>
                        </form>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                    {{-- ถ้าไม่มี walk-in ก็ไม่ต้องแสดงอะไร --}}
                @endphp
                @endforelse
                
                {{-- ถ้าไม่มีผู้เข้าร่วมทั้งสองแบบ --}}
                @if($activity->registrations->count() === 0 && $walkInAttendances->count() === 0)
                <tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">ยังไม่มีผู้เข้าร่วม</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
