<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบใบรับรองกิจกรรม | PKRU Activity Certificate Verification</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Prompt', sans-serif; margin: 0; padding: 0; }
        body { background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #ffffff; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.03); max-width: 540px; width: 100%; overflow: hidden; border: 1px solid #e2e8f0; }
        .header-valid { background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #fff; padding: 2rem 1.5rem; text-align: center; }
        .header-invalid { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); color: #fff; padding: 2rem 1.5rem; text-align: center; }
        .icon-circle { width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem auto; }
        .content { padding: 1.75rem; }
        .row-item { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .row-label { color: #64748b; }
        .row-value { font-weight: 600; color: #0f172a; text-align: right; }
        .footer { text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: #94a3b8; }
    </style>
</head>
<body>

    <div class="card">
        @if($isValid && $certificate)
            <div class="header-valid">
                <div class="icon-circle">
                    <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 style="font-size:1.35rem; font-weight:700; margin-bottom:0.25rem;">ใบรับรองถูกต้องตามระบบ</h1>
                <p style="font-size:0.85rem; opacity:0.9;">ออกโดยกองพัฒนานักศึกษา มหาวิทยาลัยราชภัฏภูเก็ต</p>
            </div>

            <div class="content">
                <div class="row-item">
                    <span class="row-label">รหัสใบรับรอง</span>
                    <span class="row-value" style="font-family:monospace; color:#059669;">{{ $certificate->certificate_code }}</span>
                </div>
                <div class="row-item">
                    <span class="row-label">ชื่อผู้ถือใบรับรอง</span>
                    <span class="row-value">{{ $certificate->user->full_name ?? '-' }}</span>
                </div>
                <div class="row-item">
                    <span class="row-label">รหัสนักศึกษา</span>
                    <span class="row-value" style="font-family:monospace;">{{ $certificate->user->student_id ?? '-' }}</span>
                </div>
                <div class="row-item">
                    <span class="row-label">คณะ / สาขาวิชา</span>
                    <span class="row-value">{{ $certificate->user->faculty ?? '-' }} / {{ $certificate->user->department ?? '-' }}</span>
                </div>
                <div class="row-item">
                    <span class="row-label">หัวข้อใบรับรอง</span>
                    <span class="row-value" style="color:#0f172a;">{{ $certificate->title }}</span>
                </div>
                <div class="row-item">
                    <span class="row-label">ชั่วโมงกิจกรรมที่สะสม</span>
                    <span class="row-value" style="color:#059669; font-weight:700;">{{ number_format($certificate->hours_completed, 1) }} ชั่วโมง</span>
                </div>
                <div class="row-item">
                    <span class="row-label">วันที่ออกเอกสาร</span>
                    <span class="row-value">{{ $certificate->issued_at ? $certificate->issued_at->format('d/m/Y H:i น.') : '-' }}</span>
                </div>
            </div>
        @else
            <div class="header-invalid">
                <div class="icon-circle">
                    <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h1 style="font-size:1.35rem; font-weight:700; margin-bottom:0.25rem;">ไม่พบข้อมูลใบรับรอง</h1>
                <p style="font-size:0.85rem; opacity:0.9;">รหัสที่ระบุอาจไม่ถูกต้องหรือถูกเพิกถอน</p>
            </div>

            <div class="content" style="text-align:center; padding:2rem 1.5rem;">
                <p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
                    รหัส <code>{{ $code }}</code> ไม่ตรงกับบันทึกในฐานข้อมูลมหาวิทยาลัย กรุณาตรวจสอบรหัสหรือติดต่อกองพัฒนานักศึกษา
                </p>
            </div>
        @endif
    </div>

    <div class="footer">
        ระบบตรวจสอบความถูกต้องใบรับรองกิจกรรมนักศึกษา &copy; {{ date('Y') }} Phuket Rajabhat University
    </div>

</body>
</html>
