{{-- หน้า Audit Log: แสดงประวัติการกระทำของ Admin ทั้งหมด --}}
@extends('layouts.admin')
@section('title', 'Audit Log')

@section('content')
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.5rem;">
    <h1 class="font-bold" style="font-size:1.5rem;">Audit Log</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <span class="badge" style="background:#e0f2fe;color:#0369a1;padding:6px 12px;font-size:.8rem;">ทั้งหมด {{ number_format($stats['total']) }}</span>
        <span class="badge" style="background:#dcfce7;color:#166534;padding:6px 12px;font-size:.8rem;">วันนี้ {{ $stats['today'] }}</span>
    </div>
</div>

{{-- สถิติสรุป --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.75rem;margin-bottom:1.25rem;">
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#16a34a;">{{ number_format($stats['creates']) }}</p>
            <p class="text-xs text-muted">สร้าง</p>
        </div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#ca8a04;">{{ number_format($stats['updates']) }}</p>
            <p class="text-xs text-muted">แก้ไข</p>
        </div>
    </div>
    <div class="card" style="text-align:center;">
        <div class="card-body" style="padding:.75rem;">
            <p style="font-size:1.5rem;font-weight:700;color:#dc2626;">{{ number_format($stats['deletes']) }}</p>
            <p class="text-xs text-muted">ลบ</p>
        </div>
    </div>
</div>

{{-- ตัวกรอง --}}
<form method="GET" action="{{ route('admin.audit-logs.index') }}" class="card mb-4">
    <div class="card-body" style="padding:.75rem 1rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;">
            <div style="flex:1;min-width:160px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">ค้นหา</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="คำอธิบาย..." class="form-control" style="font-size:.85rem;">
            </div>
            <div style="min-width:130px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">ผู้ดำเนินการ</label>
                <select name="user_id" class="form-control" style="font-size:.85rem;">
                    <option value="">ทั้งหมด</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" {{ request('user_id') == $admin->id ? 'selected' : '' }}>{{ $admin->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:110px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">การกระทำ</label>
                <select name="action" class="form-control" style="font-size:.85rem;">
                    <option value="">ทั้งหมด</option>
                    <option value="create" {{ request('action') === 'create' ? 'selected' : '' }}>สร้าง</option>
                    <option value="update" {{ request('action') === 'update' ? 'selected' : '' }}>แก้ไข</option>
                    <option value="delete" {{ request('action') === 'delete' ? 'selected' : '' }}>ลบ</option>
                    <option value="approve" {{ request('action') === 'approve' ? 'selected' : '' }}>อนุมัติ</option>
                    <option value="reject" {{ request('action') === 'reject' ? 'selected' : '' }}>ปฏิเสธ</option>
                    <option value="toggle" {{ request('action') === 'toggle' ? 'selected' : '' }}>สลับสถานะ</option>
                    <option value="login" {{ request('action') === 'login' ? 'selected' : '' }}>เข้าสู่ระบบ</option>
                </select>
            </div>
            <div style="min-width:130px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">จากวันที่</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="font-size:.85rem;">
            </div>
            <div style="min-width:130px;">
                <label class="text-xs text-muted" style="display:block;margin-bottom:.2rem;">ถึงวันที่</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" style="font-size:.85rem;">
            </div>
            <button type="submit" class="btn btn-primary" style="font-size:.85rem;padding:6px 16px;">กรอง</button>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline" style="font-size:.85rem;padding:6px 12px;">ล้าง</a>
        </div>
    </div>
</form>

{{-- ตาราง Log --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table class="admin-table" style="width:100%;font-size:.85rem;">
            <thead>
                <tr>
                    <th style="width:140px;">เวลา</th>
                    <th style="width:120px;">ผู้ดำเนินการ</th>
                    <th style="width:80px;">การกระทำ</th>
                    <th style="width:80px;">ประเภท</th>
                    <th>คำอธิบาย</th>
                    <th style="width:100px;">IP</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td style="white-space:nowrap;font-size:.8rem;color:#64748b;">
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </td>
                    <td>
                        <span class="font-semi">{{ $log->user->full_name ?? '-' }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $log->action_color }}" style="font-size:.7rem;">{{ $log->action_label }}</span>
                    </td>
                    <td style="font-size:.8rem;">{{ $log->model_label }}</td>
                    <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        {{ $log->description }}
                    </td>
                    <td style="font-size:.75rem;color:#94a3b8;">{{ $log->ip_address }}</td>
                    <td>
                        <a href="{{ route('admin.audit-logs.show', $log->id) }}" class="btn btn-sm btn-outline" style="font-size:.7rem;padding:2px 8px;">ดู</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">ยังไม่มีประวัติการกระทำ</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pagination --}}
@if($logs->hasPages())
<div style="margin-top:1rem;display:flex;justify-content:center;">
    {{ $logs->links() }}
</div>
@endif
@endsection
