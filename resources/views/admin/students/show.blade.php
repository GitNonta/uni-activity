{{-- หน้าจัดการโปรไฟล์และชั่วโมงนักศึกษา (Admin) --}}
@extends('layouts.admin')
@section('title', 'จัดการนักศึกษา: ' . $student->full_name)

@section('content')
<div class="flex items-center gap-2 mb-4" style="flex-wrap:wrap;">
    <a href="{{ route('admin.students.index') }}" class="text-sm text-muted" style="display:flex;align-items:center;gap:.25rem;">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        รายชื่อนักศึกษา
    </a>
    <span class="text-muted">/</span>
    <span class="text-sm font-semi">{{ $student->full_name }}</span>
</div>

{{-- Hero: ข้อมูลนักศึกษา + สรุปชั่วโมง --}}
<div style="background:linear-gradient(135deg,#1e40af,#4f46e5);border-radius:12px;padding:1.25rem 1.5rem;color:#fff;margin-bottom:1.25rem;">
    <div class="flex items-center justify-between" style="margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
        <div class="flex items-center gap-4">
            @if($student->profile_photo)
                <img src="{{ asset('storage/' . $student->profile_photo) }}" alt="profile"
                    style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.4);flex-shrink:0;">
            @else
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="30" height="30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
            @endif
            <div>
                <p style="font-size:.8rem;color:rgba(255,255,255,.7);margin-bottom:.1rem;">โปรไฟล์นักศึกษา</p>
                <h1 style="font-size:1.2rem;font-weight:700;margin:0;">{{ $student->full_name }}</h1>
                <p style="font-size:.85rem;color:rgba(255,255,255,.75);margin:.15rem 0 0;">{{ $student->student_id }}</p>
            </div>
        </div>
        <div>
            <button type="button" onclick="handleSendMessageClick()" class="btn" style="display:flex;align-items:center;gap:.35rem;padding:.45rem 1rem;border-radius:8px;font-weight:600;font-size:.8rem;background:#fff;color:#1e40af;border:none;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1),0 2px 4px -1px rgba(0,0,0,0.06);cursor:pointer;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='none'">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                ส่งข้อความ
            </button>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;">
        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:.65rem .75rem;text-align:center;">
            <p style="font-size:1.4rem;font-weight:700;line-height:1;">{{ number_format($totalHours, 1) }}</p>
            <p style="font-size:.7rem;color:rgba(255,255,255,.75);margin-top:.15rem;">ชั่วโมงรวม</p>
        </div>
        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:.65rem .75rem;text-align:center;">
            <p style="font-size:1.4rem;font-weight:700;line-height:1;">{{ $attendances->where('status','approved')->count() }}</p>
            <p style="font-size:.7rem;color:rgba(255,255,255,.75);margin-top:.15rem;">กิจกรรมที่ผ่าน</p>
        </div>
        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:.65rem .75rem;text-align:center;">
            <p style="font-size:1.4rem;font-weight:700;line-height:1;">{{ number_format($totalRequired, 0) }}</p>
            <p style="font-size:.7rem;color:rgba(255,255,255,.75);margin-top:.15rem;">เป้าหมาย ชม.</p>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
    {{-- ข้อมูลส่วนตัว --}}
    <div class="card">
        <div class="card-body">
            <h3 class="font-bold mb-3" style="font-size:.95rem;">ข้อมูลส่วนตัว</h3>
            <div style="display:grid;gap:.6rem;">
                <div class="flex justify-between">
                    <span class="text-xs text-muted">รหัสนักศึกษา</span>
                    <span class="text-sm font-semi">{{ $student->student_id ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs text-muted">คณะ</span>
                    <span class="text-sm">{{ $student->faculty ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs text-muted">สาขา</span>
                    <span class="text-sm">{{ $student->department ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs text-muted">ชั้นปี</span>
                    <span class="text-sm">{{ $student->year ? 'ปี '.$student->year : '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs text-muted">ภาคเรียน</span>
                    <span class="text-sm font-mini">{{ $student->program ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs text-muted">อีเมล</span>
                    <span class="text-sm">{{ $student->email ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ชั่วโมงแยกหมวด --}}
    <div class="card">
        <div class="card-body">
            <h3 class="font-bold mb-3" style="font-size:.95rem;">ชั่วโมงแยกตามหมวดหมู่</h3>
            @foreach($byCategory as $cat)
            <div style="margin-bottom:.6rem;">
                <div class="flex justify-between text-xs mb-1">
                    <span class="font-semi">{{ $cat['name'] }}</span>
                    <span style="color:{{ $cat['hours'] >= $cat['required'] ? '#16a34a' : '#64748b' }};">
                        {{ number_format($cat['hours'], 1) }}/{{ number_format($cat['required'], 0) }} ชม.
                    </span>
                </div>
                <div class="progress">
                    @php $p = $cat['required'] > 0 ? min(100, ($cat['hours']/$cat['required'])*100) : 0; @endphp
                    <div class="progress-bar {{ $p >= 100 ? 'green' : ($p >= 50 ? 'yellow' : 'primary') }}" style="width:{{ $p }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ฟอร์มเพิ่มบันทึกกิจกรรม --}}
<div class="card mb-4">
    <div class="card-body">
        <h3 class="font-bold mb-3" style="font-size:.95rem;display:flex;align-items:center;gap:.5rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            เพิ่มบันทึกกิจกรรม
        </h3>
        <form method="POST" action="{{ route('admin.students.attendances.add', $student->id) }}">
            @csrf
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:.5rem;align-items:end;flex-wrap:wrap;">
                <div>
                    <label class="form-label" style="font-size:.8rem;">กิจกรรม</label>
                    <select name="activity_id" class="form-control" required>
                        <option value="">-- เลือกกิจกรรม --</option>
                        @foreach($activities as $act)
                            <option value="{{ $act->id }}">{{ $act->title }} ({{ $act->activity_hours }} ชม. · {{ $act->activity_date->format('d/m/Y') }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.8rem;">วันที่เข้าร่วม</label>
                    <input type="datetime-local" name="checked_in_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                </div>
                <div>
                    <label class="form-label" style="font-size:.8rem;">สถานะ</label>
                    <select name="status" class="form-control">
                        <option value="approved">อนุมัติ</option>
                        <option value="pending">รออนุมัติ</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success btn-sm">เพิ่ม</button>
            </div>
        </form>
    </div>
</div>

{{-- ตารางบันทึกกิจกรรม --}}
<div class="card">
    <div class="card-body" style="padding-bottom:.5rem;">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold" style="font-size:.95rem;">บันทึกกิจกรรมทั้งหมด ({{ $attendances->count() }} รายการ)</h3>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>กิจกรรม</th>
                    <th>หมวดหมู่</th>
                    <th style="text-align:center;">ชั่วโมง</th>
                    <th>วันที่เข้าร่วม</th>
                    <th>วิธีเช็คอิน</th>
                    <th style="text-align:center;">สถานะ</th>
                    <th style="text-align:right;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $att)
                <tr>
                    <td>
                        <span class="text-sm font-semi">{{ $att->activity->title ?? '-' }}</span>
                    </td>
                    <td class="text-xs text-muted">{{ $att->activity->category->name ?? '-' }}</td>
                    <td style="text-align:center;">
                        @if($att->status === 'approved')
                            <span class="font-bold" style="color:#16a34a;">+{{ $att->activity->activity_hours }}</span>
                        @else
                            <span class="text-muted">{{ $att->activity->activity_hours }}</span>
                        @endif
                    </td>
                    <td class="text-xs">{{ $att->checked_in_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>
                        <span class="badge" style="font-size:.7rem;background:#f1f5f9;color:#475569;">{{ $att->method ?? '-' }}</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge {{ $att->status === 'approved' ? 'badge-green' : ($att->status === 'pending' ? 'badge-yellow' : 'badge-red') }}" style="font-size:.75rem;">
                            {{ $att->status === 'approved' ? 'สำเร็จ' : ($att->status === 'pending' ? 'รออนุมัติ' : 'ปฏิเสธ') }}
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:.25rem;justify-content:flex-end;">
                            @if(!auth()->user()->isStaff() || (isset($att->activity) && $att->activity->created_by === auth()->id()))
                                <button type="button" class="btn btn-outline btn-sm" onclick="openEditModal({{ $att->id }}, '{{ $att->status }}', '{{ $att->checked_in_at?->format('Y-m-d\TH:i') }}')" style="font-size:.75rem;padding:.25rem .5rem;">
                                    แก้ไข
                                </button>
                                <form method="POST" action="{{ route('admin.students.attendances.delete', [$student->id, $att->id]) }}" onsubmit="return confirm('ลบบันทึกกิจกรรมนี้?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm" style="font-size:.75rem;padding:.25rem .5rem;background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;">ลบ</button>
                                </form>
                            @else
                                <span class="text-xs text-muted" style="padding:.25rem .5rem;background:#f1f5f9;border-radius:4px;border:1px solid #e2e8f0;">ไม่อนุญาต</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">ยังไม่มีบันทึกกิจกรรม</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal แก้ไขบันทึก --}}
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.4);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:400px;margin:1rem;">
        <h3 class="font-bold mb-4" style="font-size:1rem;">แก้ไขบันทึกกิจกรรม</h3>
        <form id="editForm" method="POST">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">วันที่เข้าร่วม</label>
                <input type="datetime-local" name="checked_in_at" id="editCheckinAt" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">สถานะ</label>
                <select name="status" id="editStatus" class="form-control">
                    <option value="approved">อนุมัติ</option>
                    <option value="pending">รออนุมัติ</option>
                    <option value="rejected">ปฏิเสธ</option>
                </select>
            </div>
            <div class="flex gap-2 justify-end mt-4">
                <button type="button" onclick="closeEditModal()" class="btn btn-outline">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal เลือกเรื่องที่ต้องการสนทนา (เมื่อมีหลายหัวข้อ) --}}
<div id="chatSelectionModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.4);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:450px;margin:1rem;box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-800" style="font-size:1rem;margin:0;color:#1e293b;">เลือกเรื่องที่จะติดต่อกับ {{ $student->full_name }}</h3>
            <button type="button" onclick="closeChatSelectionModal()" style="background:none;border:none;color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        @php
            $chatJobsQuery = \App\Models\JobListing::orderBy('title');
            if (auth()->user()->isStaff()) {
                $chatJobsQuery->where('created_by', auth()->id());
            }
            $chatJobs = $chatJobsQuery->get(['id', 'title', 'position']);
        @endphp
        <div class="form-group mb-3">
            <label class="form-label" style="font-weight:600;font-size:.8rem;color:#475569;margin-bottom:.35rem;display:block;">เลือกเรื่องที่ต้องการติดต่อ</label>
            <select id="chatJobSelect" class="form-control" style="width:100%;">
                @if(!auth()->user()->isStaff())
                    <option value="0">ติดต่อสอบถามทั่วไป (General Inquiry)</option>
                @endif
                @foreach($chatJobs as $cj)
                    <option value="{{ $cj->id }}">งาน: {{ $cj->title }} ({{ $cj->position }})</option>
                @endforeach
            </select>
        </div>
        
        @if(auth()->user()->isStaff() && $chatJobs->isEmpty())
            <div style="background:#fee2e2;color:#991b1b;padding:.5rem;border-radius:6px;font-size:.75rem;margin-bottom:1rem;border:1px solid #fecaca;line-height:1.4;">
                ⚠️ คุณไม่มีประกาศงานที่สร้างขึ้น ไม่สามารถส่งข้อความหาผู้ใช้รายนี้ได้เนื่องจากระบบจำกัดให้ส่งข้อความเฉพาะในหัวข้องานที่สร้างโดยเจ้าหน้าที่เท่านั้น
            </div>
        @endif

        <div class="flex gap-2 justify-end">
            <button type="button" onclick="closeChatSelectionModal()" class="btn btn-outline" style="font-size:.8rem;padding:.4rem 1rem;">ยกเลิก</button>
            <button type="button" onclick="openChatWidget(document.getElementById('chatJobSelect').value)" class="btn btn-primary" style="font-size:.8rem;padding:.4rem 1rem;background:#1e40af;color:#fff;border:none;" {{ (auth()->user()->isStaff() && $chatJobs->isEmpty()) ? 'disabled' : '' }}>เริ่มต้นแชท</button>
        </div>
    </div>
</div>

{{-- Widget แชทแบบลอยตัว (Floating Chat Widget) --}}
<style>
@keyframes widget-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.widget-animate-spin {
    animation: widget-spin 1s linear infinite;
}
</style>
<div id="chatWidgetContainer" style="display:none;position:fixed;bottom:20px;right:20px;width:400px;height:600px;background:#fff;border-radius:16px;box-shadow:0 12px 28px rgba(0,0,0,0.15),0 8px 10px rgba(0,0,0,0.1);z-index:9999;flex-direction:column;overflow:hidden;border:1px solid #e2e8f0;">
    <!-- Widget Header -->
    <div style="background:linear-gradient(135deg,#4f46e5,#6366f1);padding:.75rem 1rem;color:#fff;display:flex;align-items:center;justify-content:space-between;cursor:pointer;flex-shrink:0;">
        <div style="display:flex;align-items:center;gap:.5rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-top:2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span style="font-weight:700;font-size:.95rem;">{{ $student->full_name }}</span>
        </div>
        <button onclick="closeChatWidget()" style="background:none;border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:4px;border-radius:50%;opacity:0.8;transition:opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <!-- Widget Body (IFrame) -->
    <div style="flex:1;position:relative;background:#f8fafc;">
        <div id="widgetLoader" style="position:absolute;inset:0;background:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#64748b;gap:.5rem;flex-direction:column;">
            <svg class="widget-animate-spin" style="width:28px;height:28px;color:#4f46e5;" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:0.25;"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity:0.75;"></path>
            </svg>
            <span>กำลังโหลดแชท...</span>
        </div>
        <iframe id="chatWidgetIframe" src="" style="width:100%;height:100%;border:none;display:none;" onload="document.getElementById('widgetLoader').style.display='none';this.style.display='block';"></iframe>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openEditModal(id, status, checkinAt) {
    var baseUrl = '{{ route('admin.students.show', $student->id) }}/attendances/' + id;
    document.getElementById('editForm').action = baseUrl;
    document.getElementById('editStatus').value = status;
    document.getElementById('editCheckinAt').value = checkinAt;
    var modal = document.getElementById('editModal');
    modal.style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

function handleSendMessageClick() {
    var optionsCount = {{ (!auth()->user()->isStaff() ? 1 : 0) + $chatJobs->count() }};
    if (optionsCount === 1) {
        // Only one option exists, open it immediately
        @if(!auth()->user()->isStaff())
            openChatWidget(0);
        @else
            openChatWidget({{ $chatJobs->first()?->id ?? 0 }});
        @endif
    } else {
        // Multiple options exist, show selection modal
        openChatSelectionModal();
    }
}

function openChatSelectionModal() {
    document.getElementById('chatSelectionModal').style.display = 'flex';
}
function closeChatSelectionModal() {
    document.getElementById('chatSelectionModal').style.display = 'none';
}
document.getElementById('chatSelectionModal').addEventListener('click', function(e) {
    if (e.target === this) closeChatSelectionModal();
});

function openChatWidget(jobId) {
    closeChatSelectionModal();

    var studentId = '{{ $student->id }}';
    var iframeUrl = '{{ route('admin.inbox.index') }}/' + jobId + '/' + studentId + '?widget=1';
    
    document.getElementById('chatWidgetIframe').src = iframeUrl;
    document.getElementById('widgetLoader').style.display = 'flex';
    document.getElementById('chatWidgetIframe').style.display = 'none';

    var widget = document.getElementById('chatWidgetContainer');
    widget.style.display = 'flex';
}
function closeChatWidget() {
    document.getElementById('chatWidgetContainer').style.display = 'none';
    document.getElementById('chatWidgetIframe').src = '';
}
</script>
@endsection
