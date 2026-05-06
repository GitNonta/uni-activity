{{-- หน้าจัดการหมวดหมู่กิจกรรม (Admin): ดู, สร้าง, แก้ไขเกณฑ์ชั่วโมง, ลบ --}}
@extends('layouts.admin')
@section('title', 'จัดการหมวดหมู่กิจกรรม')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.4rem;">จัดการหมวดหมู่กิจกรรม</h1>
    <button type="button" onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary btn-sm">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        เพิ่มหมวดหมู่
    </button>
</div>

{{-- Card: เกณฑ์ชั่วโมงรวมทั้งระบบ (แก้ไขได้) --}}
<div class="card mb-4" style="border-left:4px solid {{ $isOverridden ? '#16a34a' : '#3b82f6' }};">
    <div class="card-body">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:.75rem;">
            <div>
                <p class="font-bold" style="font-size:.95rem;margin-bottom:.15rem;">
                    เกณฑ์ชั่วโมงรวมทั้งระบบ
                    @if($isOverridden)
                        <span class="badge badge-green" style="font-size:.7rem;margin-left:.4rem;">กำหนดเอง</span>
                    @else
                        <span class="badge" style="font-size:.7rem;margin-left:.4rem;background:#eff6ff;color:#1e40af;">คำนวณจากหมวดหมู่</span>
                    @endif
                </p>
                <p class="text-xs text-muted">ชั่วโมงขั้นต่ำรวมที่นักศึกษาต้องสะสมตลอดหลักสูตร</p>
                @if($isOverridden)
                    <p class="text-xs" style="color:#64748b;margin-top:.2rem;">ผลรวมจากหมวดหมู่: {{ number_format($categorySum, 1) }} ชม.</p>
                @endif
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                <form method="POST" action="{{ route('admin.categories.required-hours') }}" style="display:flex;align-items:center;gap:.5rem;">
                    @csrf
                    <div style="position:relative;">
                        <input type="number" name="total_required_hours"
                            value="{{ number_format($totalRequired, 1) }}"
                            step="0.5" min="1" max="9999"
                            class="form-control"
                            style="width:110px;font-size:1.25rem;font-weight:700;text-align:center;padding-right:2.5rem;"
                            id="totalHoursInput">
                        <span style="position:absolute;right:.65rem;top:50%;transform:translateY(-50%);font-size:.7rem;color:#94a3b8;pointer-events:none;">ชม.</span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">บันทึก</button>
                </form>
                @if($isOverridden)
                <form method="POST" action="{{ route('admin.categories.required-hours.reset') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-sm" style="color:#64748b;font-size:.75rem;"
                        onclick="return confirm('รีเซ็ตกลับไปใช้ผลรวมจากหมวดหมู่ ({{ number_format($categorySum, 1) }} ชม.)?')">
                        รีเซ็ต
                    </button>
                </form>
                @endif
            </div>
        </div>
        @if(session('success'))
        <p class="text-sm" style="color:#16a34a;margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">✓ {{ session('success') }}</p>
        @endif
    </div>
</div>

{{-- รายการหมวดหมู่ --}}
<div class="card">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>หมวดหมู่</th>
                    <th>คำอธิบาย</th>
                    <th class="text-center">เกณฑ์ชั่วโมง</th>
                    <th class="text-center">จำนวนกิจกรรม</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $cat)
                <tr>
                    <td data-label="หมวดหมู่">
                        <div class="flex items-center gap-2">
                            <div style="width:12px;height:12px;border-radius:50%;background:{{ $cat->color ?? '#3B82F6' }};flex-shrink:0;"></div>
                            <span class="font-semi">{{ $cat->name }}</span>
                        </div>
                    </td>
                    <td data-label="คำอธิบาย" class="text-sm text-muted line-clamp-1">{{ $cat->description ?? '-' }}</td>
                    <td data-label="เกณฑ์ชั่วโมง" class="text-center">
                        <span class="font-bold" style="color:#1e40af;font-size:1.1rem;">{{ number_format((float)$cat->required_hours, 1) }}</span>
                        <span class="text-xs text-muted"> ชม.</span>
                    </td>
                    <td data-label="จำนวนกิจกรรม" class="text-center text-sm">{{ $cat->activities_count }}</td>
                    <td data-label="จัดการ" class="text-right">
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn btn-outline btn-sm"
                                onclick="openEditModal({{ $cat->id }}, '{{ addslashes($cat->name) }}', '{{ addslashes($cat->description ?? '') }}', {{ (float)$cat->required_hours }}, '{{ $cat->color ?? '#3B82F6' }}', {{ json_encode($cat->options ?? []) }})"
                                style="font-size:.75rem;">
                                แก้ไข
                            </button>
                            @if($cat->activities_count === 0)
                            <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}" onsubmit="return confirm('ลบหมวดหมู่ {{ $cat->name }} ?')" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="font-size:.75rem;background:#fef2f2;color:#dc2626;border:none;">ลบ</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="empty-state">ยังไม่มีหมวดหมู่กิจกรรม</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal: เพิ่มหมวดหมู่ --}}
<div id="addModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:440px;margin:1rem;">
        <h3 class="font-bold mb-4" style="font-size:1rem;">เพิ่มหมวดหมู่กิจกรรม</h3>
        <form method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">ชื่อหมวดหมู่ <span style="color:#dc2626;">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="เช่น จิตอาสา, วิชาการ, กีฬา">
            </div>
            <div class="form-group">
                <label class="form-label">คำอธิบาย</label>
                <textarea name="description" rows="2" class="form-control" placeholder="คำอธิบายหมวดหมู่ (ไม่บังคับ)"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เกณฑ์ชั่วโมง <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="required_hours" class="form-control" step="0.5" min="0" value="6" required>
                    <p class="text-xs text-muted" style="margin-top:.25rem;">ชั่วโมงขั้นต่ำที่นักศึกษาต้องสะสม</p>
                </div>
                <div class="form-group">
                    <label class="form-label">สี</label>
                    <input type="color" name="color" class="form-control" value="#3B82F6" style="height:42px;padding:.2rem .4rem;">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ตัวเลือกเพิ่มเติม (Options)</label>
                <div id="addOptionsContainer" class="space-y-2"></div>
                <button type="button" onclick="addOptionRow('addOptionsContainer')" class="btn btn-outline btn-sm mt-2" style="font-size:.75rem;">+ เพิ่มตัวเลือก</button>
                <p class="text-xs text-muted" style="margin-top:.25rem;">ตัวเลือกเพิ่มเติมสำหรับหมวดหมู่ (เช่น การแจ้งเตือน, การแสดงผลพิเศษ)</p>
            </div>
            <div class="flex gap-2 justify-end mt-4">
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-outline">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">เพิ่มหมวดหมู่</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: แก้ไขหมวดหมู่ --}}
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:440px;margin:1rem;">
        <h3 class="font-bold mb-4" style="font-size:1rem;">แก้ไขหมวดหมู่กิจกรรม</h3>
        <form id="editForm" method="POST">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">ชื่อหมวดหมู่ <span style="color:#dc2626;">*</span></label>
                <input type="text" name="name" id="editName" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">คำอธิบาย</label>
                <textarea name="description" id="editDescription" rows="2" class="form-control"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;color:#1e40af;">เกณฑ์ชั่วโมง <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="required_hours" id="editHours" class="form-control" step="0.5" min="0" required style="border-color:#3b82f6;font-size:1.1rem;">
                    <p class="text-xs text-muted" style="margin-top:.25rem;">ชั่วโมงขั้นต่ำที่นักศึกษาต้องสะสม</p>
                </div>
                <div class="form-group">
                    <label class="form-label">สี</label>
                    <input type="color" name="color" id="editColor" class="form-control" style="height:42px;padding:.2rem .4rem;">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ตัวเลือกเพิ่มเติม (Options)</label>
                <div id="editOptionsContainer" class="space-y-2"></div>
                <button type="button" onclick="addOptionRow('editOptionsContainer')" class="btn btn-outline btn-sm mt-2" style="font-size:.75rem;">+ เพิ่มตัวเลือก</button>
                <p class="text-xs text-muted" style="margin-top:.25rem;">ตัวเลือกเพิ่มเติมสำหรับหมวดหมู่ (เช่น การแจ้งเตือน, การแสดงผลพิเศษ)</p>
            </div>
            <div class="flex gap-2 justify-end mt-4">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-outline">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
let optionIndex = 0;

function addOptionRow(containerId, key = '', value = '') {
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.className = 'flex gap-2 items-center option-row';
    row.innerHTML = `
        <input type="text" name="options[${optionIndex}][key]" value="${key}" placeholder="ชื่อตัวเลือก" class="form-control form-control-sm" style="flex:1;">
        <input type="text" name="options[${optionIndex}][value]" value="${value}" placeholder="ค่า" class="form-control form-control-sm" style="flex:1;">
        <button type="button" onclick="this.parentElement.remove()" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fca55a;padding:.3rem .5rem;">×</button>
    `;
    container.appendChild(row);
    optionIndex++;
}

function openEditModal(id, name, description, hours, color, options = {}) {
    document.getElementById('editForm').action = '/admin/categories/' + id;
    document.getElementById('editName').value = name;
    document.getElementById('editDescription').value = description;
    document.getElementById('editHours').value = hours;
    document.getElementById('editColor').value = color;

    // Clear and populate options
    const optionsContainer = document.getElementById('editOptionsContainer');
    optionsContainer.innerHTML = '';
    optionIndex = 0;

    if (options && typeof options === 'object') {
        Object.entries(options).forEach(([key, value]) => {
            if (key !== 'key' && typeof value !== 'object') {
                addOptionRow('editOptionsContainer', key, value);
            }
        });
    }

    document.getElementById('editModal').style.display = 'flex';
}

document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
        document.getElementById('addOptionsContainer').innerHTML = '';
        optionIndex = 0;
    }
});

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
        document.getElementById('editOptionsContainer').innerHTML = '';
        optionIndex = 0;
    }
});
</script>
@endsection
