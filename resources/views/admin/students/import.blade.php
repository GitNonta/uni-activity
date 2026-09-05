@extends('layouts.admin')

@section('title', 'นำเข้านักศึกษาแบบชุด (Bulk Student Import)')

@section('content')
<div class="container-fluid" style="max-width:1000px; margin:0 auto; padding-bottom:3rem;">

    <!-- Top Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:0.5rem;">
                <svg style="width:26px; height:26px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                นำเข้านักศึกษาแบบกลุ่ม (Bulk Import)
            </h1>
            <p style="color:#64748b; font-size:0.9rem; margin:0.25rem 0 0 0;">
                เพิ่มหรืออัปเดตข้อมูลนักศึกษาจำนวนมากในคราวเดียวด้วยไฟล์ CSV หรือ Excel สำหรับช่วงเปิดภาคเรียน
            </p>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem;">
            <a href="{{ route('admin.students.import.template') }}" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:6px; background:#fff; font-size:0.875rem; border-radius:8px; padding:0.5rem 1rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                ดาวน์โหลดแม่แบบ CSV (Template)
            </a>
            <a href="{{ route('admin.students.index') }}" class="btn btn-outline" style="background:#fff; font-size:0.875rem; border-radius:8px; padding:0.5rem 1rem;">
                กลับหน้ารายชื่อ
            </a>
        </div>
    </div>

    @if(session('import_error'))
        <div style="background:#fee2e2; border:1px solid #fca5a5; color:#b91c1c; padding:1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem; display:flex; align-items:flex-start; gap:.5rem; line-height:1.6;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:2px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            <span><strong>เกิดข้อผิดพลาดในการนำเข้า:</strong> {{ session('import_error') }}</span>
        </div>
    @endif

    <div style="display:grid; grid-template-columns:1fr 340px; gap:1.5rem; align-items:start;">
        
        <!-- Upload Form -->
        <div style="background:#fff; border-radius:14px; padding:1.75rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="font-size:1.1rem; font-weight:700; color:#0f172a; margin-bottom:1rem;">อัปโหลดไฟล์ข้อมูลนักศึกษา</h2>

            <form action="{{ route('admin.students.import.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div id="dropZone" style="border:2px dashed #cbd5e1; border-radius:12px; padding:2.5rem 1.5rem; text-align:center; background:#f8fafc; cursor:pointer; transition:all .2s; margin-bottom:1.5rem;" onclick="document.getElementById('fileInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#4f46e5'; this.style.background='#eef2ff';" ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';" ondrop="handleDrop(event)">
                    <div style="width:48px; height:48px; background:#e0e7ff; color:#4f46e5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 0.75rem auto;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    </div>
                    <div id="filePrompt" style="font-weight:600; color:#1e293b; font-size:0.95rem; margin-bottom:0.25rem;">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</div>
                    <div id="fileNameDisplay" style="color:#64748b; font-size:0.8rem;">รองรับไฟล์ .CSV หรือ .XLSX (ขนาดสูงสุด 20MB)</div>
                    <input type="file" name="file" id="fileInput" accept=".csv,.txt,.xlsx,.xls" style="display:none;" onchange="updateFileName(this)">
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" style="background:#4f46e5; border-color:#4f46e5; padding:0.6rem 1.5rem; font-weight:600; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        เริ่มประมวลผลการนำเข้า
                    </button>
                </div>
            </form>
        </div>

        <!-- Instructions & Column Mapping -->
        <div>
            <div style="background:#fff; border-radius:14px; padding:1.5rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:1.5rem;">
                <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:0.75rem; display:flex; align-items:center; gap:6px;">
                    <svg width="18" height="18" color="#3b82f6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    โครงสร้างคอลัมน์ในไฟล์
                </h3>
                <ul style="margin:0; padding-left:1.25rem; font-size:0.825rem; color:#475569; line-height:1.7;">
                    <li><code style="color:#4f46e5; font-weight:700;">student_id</code> (จำเป็น) — รหัสนักศึกษา 10 หลัก</li>
                    <li><code style="color:#4f46e5; font-weight:700;">full_name</code> (จำเป็น) — ชื่อและนามสกุล</li>
                    <li><code>email</code> — ถ้าเว้นว่าง ระบบจะสร้าง <code style="font-size:0.75rem;">s{ID}@pkru.ac.th</code> ให้อัตโนมัติ</li>
                    <li><code>faculty</code> — คณะ</li>
                    <li><code>department</code> — สาขาวิชา</li>
                    <li><code>year</code> — ชั้นปี (1-4)</li>
                    <li><code>program</code> — ภาคปกติ / ภาคพิเศษ</li>
                </ul>
            </div>

            <div style="background:#f8fafc; border-radius:14px; padding:1.25rem; border:1px solid #e2e8f0;">
                <div style="font-weight:700; font-size:0.85rem; color:#0f172a; margin-bottom:0.25rem;">ระบบทำงานแบบ Upsert:</div>
                <div style="font-size:0.8rem; color:#64748b; line-height:1.5;">
                    ถ้ารหัสนักศึกษามีอยู่ในระบบแล้ว จะทำการ<strong>อัปเดตข้อมูลให้ทันสมัย</strong>โดยไม่ลบประวัติการเช็คอินเดิม ส่วนนักศึกษาใหม่จะถูก<strong>สร้างบัญชีพร้อมใช้งาน</strong>ทันที
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    function updateFileName(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            document.getElementById('filePrompt').textContent = 'เลือกไฟล์แล้ว: ' + file.name;
            document.getElementById('fileNameDisplay').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            document.getElementById('dropZone').style.borderColor = '#4f46e5';
            document.getElementById('dropZone').style.background = '#eef2ff';
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        const dropZone = document.getElementById('dropZone');
        dropZone.style.borderColor = '#cbd5e1';
        dropZone.style.background = '#f8fafc';

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            const fileInput = document.getElementById('fileInput');
            fileInput.files = e.dataTransfer.files;
            updateFileName(fileInput);
        }
    }
</script>
@endsection
