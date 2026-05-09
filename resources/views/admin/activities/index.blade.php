@extends('layouts.admin')
@section('title', 'จัดการกิจกรรม')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.5rem;">จัดการกิจกรรม</h1>
    <a href="{{ route('admin.activities.create') }}" class="btn btn-primary btn-sm">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        สร้างใหม่
    </a>
</div>

{{-- ฟอร์มค้นหาและกรองตามสถานะ --}}
<form method="GET" action="{{ route('admin.activities.index') }}" class="flex gap-2 mb-6 items-end" style="flex-wrap:wrap;">
    <div style="flex:1; min-width:200px;">
        <label class="form-label">ค้นหา</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="ชื่อกิจกรรม..." class="form-control">
    </div>
    <div style="width:180px;">
        <label class="form-label">สถานะ</label>
        <select name="status" class="form-control">
            <option value="">ทุกสถานะ</option>
            @php
                $statusMap = [
                    'upcoming' => 'เร็วๆ นี้',
                    'open' => 'เปิดรับสมัคร', 
                    'full' => 'เต็ม',
                    'ongoing' => 'กำลังดำเนินการ',
                    'done' => 'เสร็จสิ้น',
                    'cancelled' => 'ยกเลิก'
                ];
            @endphp
            @foreach($statusMap as $value => $label)
                <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-primary" style="height:42px;">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        กรอง
    </button>
</form>

{{-- ตารางกิจกรรม: ชื่อ, วันที่, สถานะ, ผู้สมัคร, ปุ่มจัดการ (ผู้เข้าร่วม / แก้ไข / ลบ) --}}
<div class="card">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th class="text-center">วันที่</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-center">ผู้สมัคร</th>
                    <th class="text-center">คำขออนุมัติ</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $act)
                <tr>
                    <td data-label="ชื่อ">
                        <a href="{{ route('admin.activities.show', $act->id) }}" class="font-semi">{{ $act->title }}</a>
                        <p class="text-xs text-muted">{{ $act->category->name ?? '-' }}</p>
                    </td>
                    <td data-label="วันที่" class="text-center text-muted">{{ $act->activity_date->format('d/m/Y') }}</td>
                    <td data-label="สถานะ" class="text-center">@include('components.status-badge', ['status' => $act->computed_status])</td>
                    <td data-label="ผู้สมัคร" class="text-center">{{ $act->getRegisteredCount() }}/{{ $act->max_participants }}</td>
                    <td data-label="คำขออนุมัติ" class="text-center">
                        @php $totalPending = $act->pending_registrations_count + $act->pending_attendances_count; @endphp
                        @if($totalPending > 0)
                            <button type="button" onclick="loadPendingRequests({{ $act->id }})" class="badge badge-yellow" style="border:none; cursor:pointer; font-size:.7rem; animation: pulse 2s infinite;">
                                {{ $totalPending }} คำขอ
                            </button>
                        @else
                            <span class="text-xs text-muted">-</span>
                        @endif
                    </td>
                    <td data-label="จัดการ" class="text-right">
                        <div class="flex justify-end gap-2" style="justify-content:flex-end;">
                            <a href="{{ route('admin.activities.participants', $act->id) }}" class="btn btn-outline btn-sm">ผู้เข้าร่วม</a>
                            <a href="{{ route('admin.activities.edit', $act->id) }}" class="btn btn-outline btn-sm">แก้ไข</a>
                            <form method="POST" action="{{ route('admin.activities.destroy', $act->id) }}" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('ยืนยันลบ?')">ลบ</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">ไม่พบกิจกรรม</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $activities->links() }}</div>

{{-- Modal สำหรับแสดงคำขออนุมัติ --}}
<div id="pendingModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; padding:1rem;">
    <div class="card" style="width:100%; max-width:600px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <div class="card-body" style="padding:1.25rem; border-bottom:1px solid #f1f5f9; flex-shrink:0;">
            <div class="flex items-center justify-between">
                <h3 class="font-bold" style="font-size:1.1rem;">คำขออนุมัติ: <span id="modalActivityTitle" style="color:#1e40af;"></span></h3>
                <button type="button" onclick="closePendingModal()" class="text-muted" style="background:none; border:none; cursor:pointer;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div id="modalContent" style="padding:1.25rem; overflow-y:auto; flex:1;">
            {{-- รายการจะถูกโหลดมาใส่ตรงนี้ด้วย JS --}}
            <div class="text-center py-8">
                <div class="spinner"></div>
                <p class="text-sm text-muted mt-2">กำลังโหลดข้อมูล...</p>
            </div>
        </div>
        <div class="card-body" style="padding:1rem; border-top:1px solid #f1f5f9; text-align:right; flex-shrink:0;">
            <button type="button" onclick="closePendingModal()" class="btn btn-outline btn-sm">ปิด</button>
        </div>
    </div>
</div>

@endsection

@section('styles')
<style>
@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
    100% { opacity: 1; transform: scale(1); }
}
.spinner { border: 3px solid #f3f3f3; border-top: 3px solid #3b82f6; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
@endsection

@section('scripts')
<script>
function loadPendingRequests(activityId) {
    const modal = document.getElementById('pendingModal');
    const content = document.getElementById('modalContent');
    const title = document.getElementById('modalActivityTitle');
    
    modal.style.display = 'flex';
    content.innerHTML = '<div class="text-center py-8"><div class="spinner"></div><p class="text-sm text-muted mt-2">กำลังโหลดข้อมูล...</p></div>';
    
    fetch(`/admin/activities/${activityId}/pending-requests`)
        .then(res => res.json())
        .then(data => {
            title.innerText = data.activity_title;
            if (data.items.length === 0) {
                content.innerHTML = '<div class="text-center py-8 text-muted">ไม่พบคำขอที่ค้างอยู่</div>';
                return;
            }
            
            let html = '<div class="space-y-3">';
            data.items.forEach(item => {
                const typeLabel = item.type === 'registration' ? 'ขอร่วมกิจกรรม' : 'ขออนุมัติเช็คอิน';
                const typeColor = item.type === 'registration' ? '#3b82f6' : '#f59e0b';
                
                html += `
                    <div style="background:#f8fafc; border-radius:10px; padding:1rem; margin-bottom:0.75rem; border-left:4px solid ${typeColor}">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semi text-sm" style="margin:0;">${item.name}</p>
                                <p class="text-xs text-muted" style="margin:2px 0;">รหัส: ${item.student_id} | ${item.faculty || '-'}</p>
                                <p class="text-xs font-medium" style="color:${typeColor}; margin:4px 0;">${typeLabel}: ${item.details}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-muted">${item.time}</span>
                                <div class="flex gap-1 mt-2">
                                    <form method="POST" action="/admin/${item.type}s/${item.id}/approve">
                                        <input type="hidden" name="_token" value="${document.querySelector('meta[name=\"csrf-token\"]').content}">
                                        <button type="submit" class="btn btn-success" style="padding:2px 10px; font-size:.7rem;">อนุมัติ</button>
                                    </form>
                                    <form method="POST" action="/admin/${item.type}s/${item.id}/reject">
                                        <input type="hidden" name="_token" value="${document.querySelector('meta[name=\"csrf-token\"]').content}">
                                        <button type="submit" class="btn btn-danger" style="padding:2px 10px; font-size:.7rem; background:#fee2e2; color:#dc2626; border-color:#fca5a5;">ปฏิเสธ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<div class="text-center py-8 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
        });
}

function handleAction(e) {
    // ปิด Modal เมื่อกดอนุมัติ/ปฏิเสธ เพื่อแสดงผลหน้าใหม่
    // หรือจะใช้ AJAX ก็ได้ แต่เบื้องต้นให้ Submit ปกติเพื่อให้หน้า Refresh
    // closePendingModal();
    return true;
}

function closePendingModal() {
    document.getElementById('pendingModal').style.display = 'none';
}

// ปิดเมื่อคลิกนอก Modal
document.getElementById('pendingModal').addEventListener('click', function(e) {
    if (e.target === this) closePendingModal();
});
</script>
@endsection
