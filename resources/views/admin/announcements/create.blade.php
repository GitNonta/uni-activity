@extends('layouts.admin')
@section('title', 'สร้างประกาศใหม่')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.4rem;">สร้างประกาศใหม่</h1>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline btn-sm">← กลับ</a>
</div>

<div class="card">
    <div class="card-body" style="padding:1.25rem;">
        <form method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data">
            @csrf
            
            <div class="mb-4">
                <label class="form-label">หัวข้อประกาศ <span class="text-danger">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" class="form-control" placeholder="ระบุหัวข้อที่ต้องการประกาศ" required>
                @error('title') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">เนื้อหา <span class="text-danger">*</span></label>
                <textarea name="content" rows="6" class="form-control" placeholder="ระบุเนื้อหาประกาศ..." required>{{ old('content') }}</textarea>
                @error('content') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">รูปภาพประกอบ (ถ้ามี)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                @error('image') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                <div class="text-xs text-muted mt-1">ขนาดไฟล์ไม่เกิน 2MB รองรับไฟล์ jpeg, png, jpg, webp</div>
                <div id="imageUploadStatus" style="display:none;align-items:center;gap:.5rem;margin-top:.5rem;padding:.45rem .65rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                    <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="#16a34a" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span id="imageUploadName" style="font-size:.78rem;font-weight:600;color:#15803d;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
                    <span id="imageUploadSize" style="font-size:.72rem;color:#16a34a;flex-shrink:0;"></span>
                    <button type="button" onclick="clearImageSelection()" title="ลบรูปที่เลือก" style="background:transparent;border:none;color:#dc2626;cursor:pointer;font-size:.85rem;line-height:1;padding:2px 4px;border-radius:4px;">✕</button>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
                <div>
                    <label class="form-label">กลุ่มเป้าหมาย (คณะ)</label>
                    <select name="target_faculty" class="form-control">
                        <option value="">ส่งถึงทุกคน (นักศึกษาทุกคณะ)</option>
                        @foreach($faculties as $f)
                            <option value="{{ $f }}" {{ old('target_faculty') == $f ? 'selected' : '' }}>เฉพาะคณะ{{ $f }}</option>
                        @endforeach
                    </select>
                    <div class="text-xs text-muted mt-1">หากเลือกคณะ ประกาศจะแสดงเฉพาะนักศึกษาในคณะนั้น</div>
                </div>
                <div>
                    <label class="form-label">ประเภท/ระดับความสำคัญ</label>
                    <select name="type" class="form-control">
                        <option value="info" {{ old('type') == 'info' ? 'selected' : '' }}>ทั่วไป (Info)</option>
                        <option value="success" {{ old('type') == 'success' ? 'selected' : '' }}>แจ้งความสำเร็จ (Success)</option>
                        <option value="warning" {{ old('type') == 'warning' ? 'selected' : '' }}>แจ้งเตือน (Warning)</option>
                        <option value="danger" {{ old('type') == 'danger' ? 'selected' : '' }}>ประกาศด่วน/อันตราย (Danger)</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">กำหนดเวลาเผยแพร่ (ไม่บังคับ)</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at') }}" class="form-control">
                <div class="text-xs text-muted mt-1">เว้นว่างไว้เพื่อเผยแพร่ทันทีเมื่อเปิดใช้งาน</div>
                @error('published_at') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-5">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} style="width:18px;height:18px;">
                    <span class="text-sm">เปิดใช้งานทันที (แสดงให้นักศึกษาเห็น)</span>
                </label>
            </div>

            <hr class="mb-4">

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline">ยกเลิก</a>
                <button type="submit" class="btn btn-primary">สร้างประกาศ</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var input = document.querySelector('input[type="file"][name="image"]');
    var status = document.getElementById('imageUploadStatus');
    var nameEl = document.getElementById('imageUploadName');
    var sizeEl = document.getElementById('imageUploadSize');
    if (!input || !status) return;

    function fmtSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
        return bytes + ' B';
    }

    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (!f) { status.style.display = 'none'; return; }
        nameEl.textContent = f.name;
        sizeEl.textContent = fmtSize(f.size);
        status.style.display = 'flex';
    });

    window.clearImageSelection = function () {
        input.value = '';
        status.style.display = 'none';
    };
})();
</script>
@endsection
