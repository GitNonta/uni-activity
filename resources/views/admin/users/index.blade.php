{{-- หน้าจัดการผู้ใช้งานทั้งหมด (Admin): นักศึกษา + เจ้าหน้าที่ --}}
@extends('layouts.admin')
@section('title', __('จัดการผู้ใช้งาน'))

@section('content')
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.75rem;">
    <h1 class="font-bold" style="font-size:1.4rem;">{{ __('จัดการผู้ใช้งาน') }}</h1>
    <div style="display:flex;gap:.5rem;">
        <a href="{{ route('admin.users.create', ['type' => 'student']) }}" class="btn btn-primary btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>{{ __('สร้างนักศึกษา') }}        </a>
        <a href="{{ route('admin.users.create', ['type' => 'staff']) }}" class="btn btn-sm" style="background:#b91c1c;color:#fff;line-height:1.5;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>{{ __('สร้างเจ้าหน้าที่') }}        </a>
    </div>
</div>

{{-- สรุปจำนวน --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1rem;">
    <div class="card" style="padding:.875rem 1rem;text-align:center;">
        <div class="text-xs text-muted">{{ __('ผู้ใช้ทั้งหมด') }}</div>
        <div class="font-bold" style="font-size:1.5rem;color:#1e40af;">{{ $counts['total'] }}</div>
    </div>
    <div class="card" style="padding:.875rem 1rem;text-align:center;">
        <div class="text-xs text-muted">{{ __('นักศึกษา') }}</div>
        <div class="font-bold" style="font-size:1.5rem;color:#166534;">{{ $counts['students'] }}</div>
    </div>
    <div class="card" style="padding:.875rem 1rem;text-align:center;">
        <div class="text-xs text-muted">{{ __('เจ้าหน้าที่') }}</div>
        <div class="font-bold" style="font-size:1.5rem;color:#b91c1c;">{{ $counts['staff'] }}</div>
    </div>
</div>

{{-- ฟิลเตอร์ --}}
<form method="GET" action="{{ route('admin.users.index') }}" class="card mb-4">
    <div class="card-body" style="padding:.875rem 1rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:180px;">
                <label class="form-label" style="font-size:.8rem;">{{ __('ค้นหา') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('ชื่อ, รหัสนักศึกษา, อีเมล...') }}">
            </div>
            <div style="min-width:130px;">
                <label class="form-label" style="font-size:.8rem;">{{ __('บทบาท') }}</label>
                <select name="role" class="form-control">
                    <option value="">{{ __('ทั้งหมด') }}</option>
                    <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>{{ __('นักศึกษา') }}</option>
                    <option value="staff" {{ request('role') === 'staff' ? 'selected' : '' }}>{{ __('เจ้าหน้าที่') }}</option>
                </select>
            </div>
            <div style="min-width:120px;">
                <label class="form-label" style="font-size:.8rem;">{{ __('สถานะ') }}</label>
                <select name="status" class="form-control">
                    <option value="">{{ __('ทั้งหมด') }}</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('เปิดใช้งาน') }}</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('ปิดใช้งาน') }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('ค้นหา') }}</button>
            @if(request()->anyFilled(['search','role','status']))
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">{{ __('ล้าง') }}</a>
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
                    <th>{{ __('ชื่อ') }}</th>
                    <th>{{ __('บทบาท') }}</th>
                    <th>{{ __('อีเมล / รหัส') }}</th>
                    <th>{{ __('คณะ / สาขา') }}</th>
                    <th class="text-center">{{ __('สถานะ') }}</th>
                    <th class="text-right">{{ __('จัดการ') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td data-label="{{ __('ชื่อ') }}">
                        <div class="font-semi text-sm" title="{{ $user->displayName() }}">{{ Str::limit($user->displayName(), 35, '...') }}</div>
                    </td>
                    <td data-label="{{ __('บทบาท') }}">
                        @if($user->role === 'staff')
                            <span class="badge badge-orange">{{ __('เจ้าหน้าที่') }}</span>
                        @else
                            <span class="badge badge-orange">{{ __('นักศึกษา') }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __('อีเมล / รหัส') }}">
                        <div class="text-sm">{{ $user->email }}</div>
                        @if($user->student_id)
                            <div class="text-xs text-muted">{{ $user->student_id }}</div>
                        @endif
                    </td>
                    <td data-label="{{ __('คณะ / สาขา') }}">
                        <div class="text-sm">{{ $user->faculty ? __($user->faculty) : '-' }}</div>
                        <div class="text-xs text-muted line-clamp-1">{{ $user->department ? __($user->department) : '' }} @if($user->program) ({{ __($user->program) }}) @endif</div>
                    </td>
                    <td data-label="{{ __('สถานะ') }}" class="text-center">
                        @if($user->is_active)
                            <span class="badge badge-green">{{ __('เปิด') }}</span>
                        @else
                            <span class="badge badge-red">{{ __('ปิด') }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __('จัดการ') }}" class="text-right">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-outline btn-sm">{{ __('แก้ไข') }}</a>
                            <form method="POST" action="{{ route('admin.users.toggle-active', $user->id) }}" style="margin:0;">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline' : 'btn-primary' }}" style="font-size:.75rem;">
                                    {{ $user->is_active ? __('ปิด') : __('เปิด') }}
                                </button>
                            </form>
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" style="margin:0;" onsubmit="return confirm('{{ __('ยืนยันลบผู้ใช้') }} {{ $user->displayName() }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#b91c1c;font-size:.75rem;border:none;line-height:1.5;">{{ __('ลบ') }}</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state-row">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <div class="empty-state-row-title">{{ __('ไม่พบผู้ใช้งาน') }}</div>
                            <div class="empty-state-row-desc">{{ __('ลองเปลี่ยนเงื่อนไขการค้นหา') }}</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
