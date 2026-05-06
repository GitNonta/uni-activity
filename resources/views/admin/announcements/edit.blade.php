@extends('layouts.admin')
@section('title', 'แก้ไขประกาศ')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.4rem;">แก้ไขประกาศ</h1>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline btn-sm">← กลับ</a>
</div>

<div class="card">
    <div class="card-body" style="padding:1.25rem;">
        <form method="POST" action="{{ route('admin.announcements.update', $announcement->id) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            
            <div class="mb-4">
                <label class="form-label">หัวข้อประกาศ <span class="text-danger">*</span></label>
                <input type="text" name="title" value="{{ old('title', $announcement->title) }}" class="form-control" placeholder="ระบุหัวข้อที่ต้องการประกาศ" required>
                @error('title') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">เนื้อหา <span class="text-danger">*</span></label>
                <textarea name="content" rows="6" class="form-control" placeholder="ระบุเนื้อหาประกาศ..." required>{{ old('content', $announcement->content) }}</textarea>
                @error('content') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label">รูปภาพประกอบ (ถ้ามี)</label>
                @if($announcement->image_path)
                    <div class="mb-2">
                        <img src="{{ Storage::url($announcement->image_path) }}" alt="Current Image" style="max-width: 200px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div class="text-xs text-muted">รูปภาพปัจจุบัน</div>
                    </div>
                @endif
                <input type="file" name="image" class="form-control" accept="image/*">
                @error('image') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                <div class="text-xs text-muted mt-1">อัปโหลดใหม่เพื่อเปลี่ยนรูปเดิม ขนาดไม่เกิน 2MB</div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
                <div>
                    <label class="form-label">กลุ่มเป้าหมาย (คณะ)</label>
                    <select name="target_faculty" class="form-control">
                        <option value="">ส่งถึงทุกคน (นักศึกษาทุกคณะ)</option>
                        @foreach($faculties as $f)
                            <option value="{{ $f }}" {{ old('target_faculty', $announcement->target_faculty) == $f ? 'selected' : '' }}>เฉพาะคณะ{{ $f }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">ประเภท/ระดับความสำคัญ</label>
                    <select name="type" class="form-control">
                        <option value="info" {{ old('type', $announcement->type) == 'info' ? 'selected' : '' }}>ทั่วไป (Info)</option>
                        <option value="success" {{ old('type', $announcement->type) == 'success' ? 'selected' : '' }}>แจ้งความสำเร็จ (Success)</option>
                        <option value="warning" {{ old('type', $announcement->type) == 'warning' ? 'selected' : '' }}>แจ้งเตือน (Warning)</option>
                        <option value="danger" {{ old('type', $announcement->type) == 'danger' ? 'selected' : '' }}>ประกาศด่วน/อันตราย (Danger)</option>
                    </select>
                </div>
            </div>

            <div class="mb-5">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $announcement->is_active) ? 'checked' : '' }} style="width:18px;height:18px;">
                    <span class="text-sm">เปิดใช้งาน (แสดงให้นักศึกษาเห็น)</span>
                </label>
            </div>

            <hr class="mb-4">

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline">ยกเลิก</a>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>
@endsection
