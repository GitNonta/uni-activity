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
                <div id="imageUploadWrap" style="display:none;margin-top:.5rem;">
                <div class="imgup-thumb" id="imageUploadThumb">
                    <img id="imageUploadPreview" src="" alt="ตัวอย่างรูป">
                    <span class="imgup-pct" id="imageUploadPct">0%</span>
                    <div class="imgup-progress"><span id="imageUploadBarFill"></span></div>
                </div>
                <div id="imageUploadStatus" style="display:flex;align-items:center;gap:.5rem;margin-top:.4rem;padding:.45rem .65rem;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;">
                    <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span id="imageUploadName" style="font-size:.78rem;font-weight:600;color:#c2410c;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
                    <span id="imageUploadSize" style="font-size:.72rem;color:#ea580c;flex-shrink:0;"></span>
                    <button type="button" onclick="clearImageSelection()" title="ลบรูปที่เลือก" style="background:transparent;border:none;color:#dc2626;cursor:pointer;font-size:.85rem;line-height:1;padding:2px 4px;border-radius:4px;">✕</button>
                </div>
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

<style>
    .imgup-thumb { position: relative; width: 180px; height: 130px; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,.08); background: #ffedd5; }
    .imgup-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; filter: blur(18px); opacity: .35; transform: scale(1.12); transition: filter .22s linear, opacity .22s linear, transform .22s linear; }
    .imgup-progress { position: absolute; left: 0; right: 0; bottom: 0; height: 5px; background: rgba(254, 215, 170, .6); transition: opacity .35s ease .25s; }
    .imgup-progress span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, #f97316, #ea580c); }
    .imgup-thumb:not(.done) .imgup-progress span {
        background-image: linear-gradient(45deg, rgba(255,255,255,.28) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.28) 50%, rgba(255,255,255,.28) 75%, transparent 75%), linear-gradient(90deg, #f97316, #ea580c);
        background-size: 16px 16px, 100% 100%;
        animation: imgup-stripes .5s linear infinite;
    }
    @keyframes imgup-stripes { from { background-position: 0 0, 0 0; } to { background-position: 32px 0, 0 0; } }
    .imgup-pct { position: absolute; top: 6px; right: 6px; background: rgba(234, 88, 12, .92); color: #fff; font-size: .68rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; transition: opacity .3s ease .2s; }
    .imgup-thumb.done .imgup-progress, .imgup-thumb.done .imgup-pct { opacity: 0; }
    .imgup-thumb.done img { filter: blur(0); opacity: 1; transform: scale(1); }
</style>
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

    var wrap = document.getElementById('imageUploadWrap');
    var preview = document.getElementById('imageUploadPreview');
    var thumb = document.getElementById('imageUploadThumb');
    var pctEl = document.getElementById('imageUploadPct');
    var barFill = document.getElementById('imageUploadBarFill');
    var objectUrl = null;
    var loadTimer = null;

    function stopLoad() {
        if (loadTimer) { clearTimeout(loadTimer); loadTimer = null; }
    }

    function resetVisual() {
        stopLoad();
        thumb.classList.remove('done');
        barFill.style.width = '0%';
        pctEl.textContent = '0%';
    }

    function resetPreview() {
        resetVisual();
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        preview.src = '';
        preview.style.filter = '';
        preview.style.opacity = '';
        preview.style.transform = '';
    }

    // Blur-up progressive reveal: the preview sharpens as the progress advances
    function playLoadAnimation() {
        resetVisual();
        var pct = 0;
        (function step() {
            pct += Math.max(2.5, (100 - pct) * 0.16 * (0.55 + Math.random() * 0.9));
            if (pct >= 100) pct = 100;
            var p = pct / 100;
            barFill.style.width = pct + '%';
            pctEl.textContent = Math.round(pct) + '%';
            preview.style.filter = 'blur(' + (18 * (1 - p)).toFixed(1) + 'px)';
            preview.style.opacity = (0.35 + 0.65 * p).toFixed(2);
            preview.style.transform = 'scale(' + (1.12 - 0.12 * p).toFixed(3) + ')';
            if (pct < 100) {
                loadTimer = setTimeout(step, 80 + Math.random() * 120);
            } else {
                thumb.classList.add('done');
            }
        })();
    }

    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (!f) { resetPreview(); wrap.style.display = 'none'; return; }
        resetPreview();
        objectUrl = URL.createObjectURL(f);
        preview.src = objectUrl;
        nameEl.textContent = f.name;
        sizeEl.textContent = fmtSize(f.size);
        wrap.style.display = 'block';
        playLoadAnimation();
    });

    window.clearImageSelection = function () {
        input.value = '';
        resetPreview();
        wrap.style.display = 'none';
    };
})();
</script>
@endsection
