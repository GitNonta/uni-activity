<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificate->title }} - {{ $user->full_name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: 'Garuda', 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            color: #1e293b;
            height: 100%;
        }
        .cert-container {
            position: relative;
            margin: 25px;
            height: 91%;
            border: 4px solid #1e3a8a;
            padding: 25px 35px;
            box-sizing: border-box;
            background: #ffffff;
            text-align: center;
        }
        .cert-inner-border {
            border: 1.5px solid #d97706;
            height: 94%;
            padding: 20px 30px;
            box-sizing: border-box;
            position: relative;
        }
        .cert-header {
            margin-top: 5px;
            margin-bottom: 12px;
        }
        .cert-org {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        .cert-suborg {
            font-size: 13px;
            color: #64748b;
        }
        .cert-title {
            font-size: 24px;
            font-weight: bold;
            color: #b45309;
            margin: 15px 0 10px 0;
            letter-spacing: 0.5px;
        }
        .cert-subtitle {
            font-size: 13px;
            color: #475569;
            margin-bottom: 15px;
        }
        .student-name {
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
            margin: 10px 0;
            border-bottom: 1px dashed #cbd5e1;
            display: inline-block;
            padding: 0 30px 4px 30px;
        }
        .student-meta {
            font-size: 13px;
            color: #334155;
            margin-top: 6px;
            margin-bottom: 16px;
        }
        .cert-body {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            max-width: 680px;
            margin: 0 auto 20px auto;
        }
        .cert-footer {
            margin-top: 20px;
            width: 100%;
        }
        .sign-table {
            width: 100%;
            margin-top: 15px;
        }
        .sign-table td {
            vertical-align: bottom;
            text-align: center;
        }
        .sign-line {
            width: 180px;
            border-bottom: 1px solid #94a3b8;
            margin: 0 auto 5px auto;
        }
        .sign-name {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
        }
        .sign-title {
            font-size: 10px;
            color: #64748b;
        }
        .cert-meta-bottom {
            position: absolute;
            bottom: 15px;
            left: 30px;
            right: 30px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 5px;
        }
        .code-box {
            font-family: monospace;
            font-weight: bold;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="cert-container">
        <div class="cert-inner-border">
            
            <div class="cert-header">
                <div class="cert-org">มหาวิทยาลัยราชภัฏภูเก็ต (PKRU)</div>
                <div class="cert-suborg">กองพัฒนานักศึกษา · กิจกรรมเสริมหลักสูตรและการพัฒนานักศึกษา</div>
            </div>

            <div class="cert-title">ใบรับรองการเข้าร่วมกิจกรรมพัฒนานักศึกษา</div>
            <div class="cert-subtitle">CERTIFICATE OF PARTICIPATION AND ACTIVITY COMPLETION</div>

            <div style="font-size:13px; color:#64748b; margin-top:5px;">ขอมอบใบรับรองฉบับนี้เพื่อแสดงว่า</div>

            <div class="student-name">{{ $user->full_name }}</div>

            <div class="student-meta">
                รหัสนักศึกษา: <strong>{{ $user->student_id }}</strong> | 
                คณะ: <strong>{{ $user->faculty ?? 'มหาวิทยาลัยราชภัฏภูเก็ต' }}</strong> | 
                สาขาวิชา: <strong>{{ $user->department ?? '-' }}</strong>
            </div>

            <div class="cert-body">
                ได้เข้าร่วมและผ่านการประเมินชั่วโมงกิจกรรมเสริมหลักสูตร<br>
                <strong>{{ $certificate->title }}</strong><br>
                รวมทั้งสิ้น <strong>{{ number_format($certificate->hours_completed, 1) }}</strong> ชั่วโมงกิจกรรม 
                ประจำปีการศึกษา {{ $certificate->academic_year ?? date('Y') }}
            </div>

            <table class="sign-table">
                <tr>
                    <td style="width:33%; text-align:left; font-size:10px; color:#64748b;">
                        <div class="code-box">รหัสใบรับรอง: {{ $certificate->certificate_code }}</div>
                        <div>ออกให้ ณ วันที่: {{ $certificate->issued_at ? $certificate->issued_at->format('d/m/Y') : date('d/m/Y') }}</div>
                        <div style="margin-top:2px;">ตรวจสอบออนไลน์: {{ route('certificates.verify', $certificate->certificate_code) }}</div>
                        <div style="margin-top:6px;">{!! $qrCodeSvg !!}</div>
                    </td>
                    <td style="width:33%;">
                        <div class="sign-line"></div>
                        <div class="sign-name">ผู้อำนวยการกองพัฒนานักศึกษา</div>
                        <div class="sign-title">มหาวิทยาลัยราชภัฏภูเก็ต</div>
                    </td>
                    <td style="width:33%;">
                        <div class="sign-line"></div>
                        <div class="sign-name">อธิการบดีมหาวิทยาลัยราชภัฏภูเก็ต</div>
                        <div class="sign-title">Phuket Rajabhat University</div>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</body>
</html>
