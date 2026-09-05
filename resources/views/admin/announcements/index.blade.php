@extends('layouts.admin')
@section('title', 'จัดการประกาศ')

@section('content')
<div class="flex items-center justify-between flex-wrap gap-2 mb-4">
    <h1 class="font-bold" style="font-size:1.4rem; white-space: nowrap;">จัดการประกาศ/ข่าวสาร</h1>
    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary btn-sm" style="white-space: nowrap;">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        สร้างประกาศใหม่
    </a>
</div>

<form method="GET" action="{{ route('admin.announcements.index') }}" class="card mb-4">
    <div class="card-body" style="padding:.875rem 1rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="ค้นหาหัวข้อประกาศ...">
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">ค้นหา</button>
            @if(request('search'))
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline" style="white-space:nowrap;">ล้าง</a>
            @endif
        </div>
    </div>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>หัวข้อ</th>
                    <th>กลุ่มเป้าหมาย</th>
                    <th>ประเภท</th>
                    <th>ผู้สร้าง</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($announcements as $item)
                <tr>
                    <td data-label="หัวข้อ">
                        <span class="font-semi text-sm" style="display:block;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $item->title }}">{{ Str::limit($item->title, 40, '...') }}</span>
                        <div class="text-xs text-muted">{{ $item->created_at->format('d/m/Y H:i') }}</div>
                    </td>
                    <td data-label="กลุ่มเป้าหมาย">
                        @if($item->target_faculty)
                            <span class="badge badge-orange">{{ $item->target_faculty }}</span>
                        @else
                            <span class="badge badge-orange">ทุกคน</span>
                        @endif
                    </td>
                    <td data-label="ประเภท">
                        <span class="badge badge-{{ $item->type === 'danger' ? 'red' : ($item->type === 'warning' ? 'yellow' : ($item->type === 'success' ? 'green' : 'gray')) }}">
                            {{ ucfirst($item->type) }}
                        </span>
                    </td>
                    <td data-label="ผู้สร้าง" class="text-xs text-muted">{{ $item->creator->full_name ?? '-' }}</td>
                    <td data-label="สถานะ" class="text-center">
                        @if($item->published_at?->isFuture())
                            <div class="text-xs text-muted" style="margin-bottom:.35rem;">ตั้งเวลา {{ $item->published_at->format('d/m/Y H:i') }}</div>
                        @elseif($item->published_at)
                            <div class="text-xs text-muted" style="margin-bottom:.35rem;">เผยแพร่แล้ว</div>
                        @endif
                        <form method="POST" action="{{ route('admin.announcements.toggle-active', $item->id) }}" style="margin:0;">
                            @csrf @method('PATCH')
                            <button type="submit" class="badge {{ $item->is_active ? 'badge-green' : 'badge-gray' }}" style="cursor:pointer;border:none;">
                                {{ $item->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                            </button>
                        </form>
                    </td>
                    <td data-label="จัดการ" class="text-right">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.announcements.edit', $item->id) }}" class="btn btn-outline btn-sm">แก้ไข</a>
                            <form method="POST" action="{{ route('admin.announcements.destroy', $item->id) }}" onsubmit="return confirm('ยืนยันลบประกาศนี้?')" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626; border:none;">ลบ</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="empty-state">ยังไม่มีประกาศ</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $announcements->links() }}</div>
@endsection
