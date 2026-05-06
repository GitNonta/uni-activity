{{-- หน้าจัดการผู้ใช้งานทั้งหมด (Admin): นักศึกษา + เจ้าหน้าที่ --}}
@extends('layouts.admin')
@section('title', 'จัดการผู้ใช้งาน')

@section('content')
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.75rem;">
    <h1 class="font-bold" style="font-size:1.4rem;">จัดการผู้ใช้งาน</h1>
    <div style="display:flex;gap:.5rem;">
        <a href="{{ route('admin.users.create', ['type' => 'student']) }}" class="btn btn-primary btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            สร้างนักศึกษา
        </a>
        <a href="{{ route('admin.users.create', ['type' => 'staff']) }}" class="btn btn-sm" style="background:#7c3aed;color:#fff;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            สร้างเจ้าหน้าที่
        </a>
    </div>
</div>

{{-- สรุปจำนวน --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1rem;">
    <div class="card" style="padding:.875rem 1rem;text-align:center;">
        <div class="text-xs text-muted">ผู้ใช้ทั้งหมด</div>
        <div class="font-bold" style="font-size:1.5rem;color:#1e40af;">{{ $counts['total'] }}</div>
    </div>
    <div class="card" style="padding:.875rem 1rem;text-align:center;">
        <div class="text-xs text-muted">นักศึกษา</div>
        <div class="font-bold" style="font-size:1.5rem;color:#16a34a;">{{ $counts['students'] }}</div>
    </div>
    <div class="card" style="padding:.875rem 1rem;text-align:center;">
        <div class="text-xs text-muted">เจ้าหน้าที่</div>
        <div class="font-bold" style="font-size:1.5rem;color:#7c3aed;">{{ $counts['staff'] }}</div>
    </div>
</div>

{{-- ฟิลเตอร์ --}}
<form method="GET" action="{{ route('admin.users.index') }}" class="card mb-4">
    <div class="card-body" style="padding:.875rem 1rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:180px;">
                <label class="form-label" style="font-size:.8rem;">ค้นหา</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="ชื่อ, รหัสนักศึกษา, อีเมล...">
            </div>
            <div style="min-width:130px;">
                <label class="form-label" style="font-size:.8rem;">บทบาท</label>
                <select name="role" class="form-control">
                    <option value="">ทั้งหมด</option>
                    <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>นักศึกษา</option>
                    <option value="staff" {{ request('role') === 'staff' ? 'selected' : '' }}>เจ้าหน้าที่</option>
                </select>
            </div>
            <div style="min-width:120px;">
                <label class="form-label" style="font-size:.8rem;">สถานะ</label>
                <select name="status" class="form-control">
                    <option value="">ทั้งหมด</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>เปิดใช้งาน</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>ปิดใช้งาน</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">ค้นหา</button>
            @if(request()->anyFilled(['search','role','status']))
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">ล้าง</a>
            @endif
        </div>
    </div>
</form>

{{-- ตารางผู้ใช้ --}}
<div class="card">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>บทบาท</th>
                    <th>อีเมล / รหัส</th>
                    <th>คณะ / สาขา</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td data-label="ชื่อ">
                        <div class="font-semi text-sm">{{ $user->full_name }}</div>
                    </td>
                    <td data-label="บทบาท">
                        @if($user->role === 'staff')
                            <span class="badge badge-purple">เจ้าหน้าที่</span>
                        @else
                            <span class="badge badge-blue">นักศึกษา</span>
                        @endif
                    </td>
                    <td data-label="อีเมล / รหัส">
                        <div class="text-sm">{{ $user->email }}</div>
                        @if($user->student_id)
                            <div class="text-xs text-muted">{{ $user->student_id }}</div>
                        @endif
                    </td>
                    <td data-label="คณะ / สาขา">
                        <div class="text-sm">{{ $user->faculty ?? '-' }}</div>
                        <div class="text-xs text-muted line-clamp-1">{{ $user->department ?? '' }} @if($user->program) ({{ $user->program }}) @endif</div>
                    </td>
                    <td data-label="สถานะ" class="text-center">
                        @if($user->is_active)
                            <span class="badge badge-green">เปิด</span>
                        @else
                            <span class="badge badge-red">ปิด</span>
                        @endif
                    </td>
                    <td data-label="จัดการ" class="text-right">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-outline btn-sm">แก้ไข</a>
                            <form method="POST" action="{{ route('admin.users.toggle-active', $user->id) }}" style="margin:0;">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline' : 'btn-primary' }}" style="font-size:.75rem;">
                                    {{ $user->is_active ? 'ปิด' : 'เปิด' }}
                                </button>
                            </form>
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" style="margin:0;" onsubmit="return confirm('ยืนยันลบผู้ใช้ {{ $user->full_name }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;font-size:.75rem;border:none;">ลบ</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="empty-state">ไม่พบผู้ใช้งาน</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
