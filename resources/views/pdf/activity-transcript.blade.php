<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Activity Transcript - {{ $user->student_id }}</title>
    @php
        $toBase64 = function (?string $path): ?string {
            if (!$path || !file_exists($path)) {
                return null;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'svg'         => 'image/svg+xml',
                default       => 'image/png',
            };
            $content = file_get_contents($path);
            return $content !== false ? 'data:' . $mime . ';base64,' . base64_encode($content) : null;
        };

        $emblemPath   = file_exists(public_path('images/pkru-emblem.png')) ? public_path('images/pkru-emblem.png') : public_path('images/--removebg-preview.png');
        $emblemBase64 = $toBase64($emblemPath);
        $sig1Path     = public_path('images/signatures/signature1.png');
        $sig1Base64   = $toBase64($sig1Path);
        $sig2Path     = public_path('images/signatures/signature2.png');
        $sig2Base64   = $toBase64($sig2Path);
        $studentPhoto = $user->profile_photo && file_exists(storage_path('app/public/' . $user->profile_photo))
            ? $toBase64(storage_path('app/public/' . $user->profile_photo))
            : null;

        $levelLabel  = 'ควรปรับปรุง';
        $pct = $totalRequired > 0 ? ($totalHours / $totalRequired) * 100 : 0;
        if ($pct >= 100)     $levelLabel = 'ดีเยี่ยม';
        elseif ($pct >= 80)  $levelLabel = 'ดีมาก';
        elseif ($pct >= 60)  $levelLabel = 'ดี';
        elseif ($pct >= 40)  $levelLabel = 'พอใช้';

        $docNumber = 'AT-' . ($user->student_id ?: $user->id) . '-' . now()->format('Ymd') . '-' . rand(1000, 9999);
    @endphp
    <style>
        @page {
            size: A4 portrait;
            margin: 16mm 18mm 14mm 18mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'sarabun', 'thsarabun', sans-serif;
            font-size: 10pt;
            color: #0f172a;
            line-height: 1.35;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .uni-th {
            font-size: 14pt;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.25;
        }
        .uni-en {
            font-size: 9pt;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 3px;
        }
        .doc-title-th {
            font-size: 13pt;
            font-weight: bold;
            color: #1e3a8a;
            line-height: 1.25;
        }
        .doc-title-en {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 0.06em;
        }

        .header-line {
            border-top: 2px solid #1e3a8a;
            border-bottom: 0.5px solid #94a3b8;
            height: 3px;
            margin: 5px 0 8px 0;
        }

        /* ── Student Information Box ── */
        .info-wrap-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            font-size: 9.5pt;
            padding: 2px 4px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #334155;
            white-space: nowrap;
        }
        .info-val {
            color: #0f172a;
        }

        /* ── Summary & Detail Tables ── */
        .table-section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 8px 0 4px;
            border-left: 3px solid #1e3a8a;
            padding-left: 6px;
            line-height: 1.2;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            table-layout: fixed;
        }
        .data-table th {
            font-size: 9pt;
            font-weight: bold;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            text-align: center;
            vertical-align: middle;
        }
        .data-table td {
            font-size: 8.5pt;
            padding: 3.5px 6px;
            border: 1px solid #cbd5e1;
            color: #1e293b;
            vertical-align: middle;
        }
        .data-table tr.total-row td {
            font-weight: bold;
            background-color: #f1f5f9;
        }

        /* ── Result Box ── */
        .result-box {
            padding: 6px 12px;
            border-radius: 4px;
            margin-bottom: 8px;
            background-color: #ecfdf5;
            border: 1px solid #059669;
            color: #065f46;
            text-align: center;
        }
        .result-box-title {
            font-size: 10pt;
            font-weight: bold;
        }
        .result-box-desc {
            font-size: 8.5pt;
            margin-top: 1px;
        }

        /* ── Certification Text ── */
        .cert-block {
            text-align: center;
            margin: 10px 0 6px 0;
            font-size: 9.5pt;
            color: #1e293b;
            line-height: 1.4;
        }

        /* ── Signatures ── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .sig-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .sig-title {
            font-size: 9pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 2px;
        }
        .sig-img {
            height: 38px;
            max-width: 120px;
            margin: 1px auto;
        }
        .sig-placeholder {
            height: 38px;
        }
        .sig-name {
            font-size: 9pt;
            color: #334155;
            font-weight: bold;
        }
        .sig-pos {
            font-size: 8pt;
            color: #64748b;
        }

        /* ── Footer ── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
        }
        .footer-table td {
            font-size: 7.5pt;
            color: #64748b;
        }
    </style>
</head>
<body>

    {{-- ส่วนหัวทางการ: ตราสัญลักษณ์มหาวิทยาลัย + หัวเรื่องสองภาษา --}}
    <table class="header-table">
        <tr>
            <td style="width: 72px; text-align: left;">
                @if(!empty($emblemBase64))
                    <img src="{{ $emblemBase64 }}" style="width: 66px; height: auto;">
                @endif
            </td>
            <td style="text-align: center; padding: 0 8px;">
                <div class="uni-th">มหาวิทยาลัยเวทย์มนต์และเทคโนโลยีดิจิทัล</div>
                <div class="uni-en">Magic and Digital Technology University</div>
                <div class="doc-title-th">ใบแสดงผลการเข้าร่วมกิจกรรมนักศึกษา</div>
                <div class="doc-title-en">ACTIVITY TRANSCRIPT</div>
            </td>
            <td style="width: 72px; text-align: right;">
                @if(!empty($studentPhoto))
                    <img src="{{ $studentPhoto }}" style="width: 58px; height: 72px; object-fit: cover; border: 1px solid #cbd5e1; border-radius: 2px;">
                @else
                    <div style="width: 58px; height: 72px; border: 1px dashed #cbd5e1; background: #f8fafc; text-align: center; line-height: 72px; font-size: 7pt; color: #94a3b8;">รูปนักศึกษา</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="header-line"></div>

    {{-- ข้อมูลนักศึกษา --}}
    <table class="info-wrap-table">
        <tr>
            <td>
                <table class="info-table">
                    <tr>
                        <td class="info-label" style="width: 110px;">รหัสนักศึกษา:</td>
                        <td class="info-val" style="width: 140px; font-weight: bold;">{{ $user->student_id }}</td>
                        <td class="info-label" style="width: 95px;">ชื่อ-นามสกุล:</td>
                        <td class="info-val" style="font-weight: bold;">{{ $user->full_name }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">คณะ (Faculty):</td>
                        <td class="info-val">{{ $user->faculty ?? '-' }}</td>
                        <td class="info-label">สาขาวิชา:</td>
                        <td class="info-val">{{ $user->department ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">ระดับการศึกษา:</td>
                        <td class="info-val" colspan="3">ปริญญาตรี (ชั้นปีที่ {{ $user->year ?? '-' }})</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ผลการประเมินกิจกรรมรวม --}}
    <div class="result-box">
        <div class="result-box-title">ผลการเข้าร่วมกิจกรรมพัฒนานักศึกษาตามเกณฑ์มหาวิทยาลัย</div>
        <div class="result-box-desc">
            สะสมรวม <strong>{{ number_format($totalHours, 1) }}</strong> ชั่วโมง / เกณฑ์ขั้นต่ำ {{ number_format($totalRequired, 1) }} ชั่วโมง
            (ระดับผลการประเมิน: <strong>{{ $levelLabel }}</strong>)
        </div>
    </div>

    {{-- ตารางหมวดหมู่กิจกรรม + ชั่วโมง --}}
    <div class="table-section-title">สรุปชั่วโมงกิจกรรมจำแนกตามหมวดหมู่ (Activity Hours by Category)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 50%;">หมวดหมู่กิจกรรม</th>
                <th style="width: 25%;">เกณฑ์ที่กำหนด (ชม.)</th>
                <th style="width: 25%;">ชั่วโมงที่สะสมได้ (ชม.)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byCategory as $cat)
            <tr>
                <td>{{ $cat['name'] }}</td>
                <td style="text-align: center;">{{ number_format($cat['required'], 1) }}</td>
                <td style="text-align: center; font-weight: bold;">{{ number_format($cat['hours'], 1) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td style="font-weight: bold;">รวมชั่วโมงกิจกรรมทั้งหมด</td>
                <td style="text-align: center; font-weight: bold;">{{ number_format($totalRequired, 1) }}</td>
                <td style="text-align: center; font-weight: bold; color: #1e3a8a;">{{ number_format($totalHours, 1) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ข้อความรับรองทางการ --}}
    <div class="cert-block">
        ขอรับรองว่าตลอดระยะเวลาการศึกษา นักศึกษาได้เข้าร่วมกิจกรรมตามรายงานที่บันทึกไว้ในระเบียนกิจกรรมนักศึกษาจริงทุกประการ
    </div>

    {{-- ส่วนลงนามทางการ (2 คอลัมน์) --}}
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-title">ผู้ตรวจสอบข้อมูลกิจกรรม</div>
                @if(!empty($sig1Base64))
                    <img src="{{ $sig1Base64 }}" class="sig-img">
                @else
                    <div class="sig-placeholder"></div>
                @endif
                <div class="sig-name">( อาจารย์ ดร.สมชาย ใจดี )</div>
                <div class="sig-pos">ผู้อำนวยการกองพัฒนานักศึกษา</div>
            </td>
            <td>
                <div class="sig-title">ผู้อนุมัติเอกสารและรับรองผล</div>
                @if(!empty($sig2Base64))
                    <img src="{{ $sig2Base64 }}" class="sig-img">
                @else
                    <div class="sig-placeholder"></div>
                @endif
                <div class="sig-name">( ผู้ช่วยศาสตราจารย์ ดร.วิภาดา วิจิตรศิลป์ )</div>
                <div class="sig-pos">รองอธิการบดีฝ่ายพัฒนานักศึกษาและศิษย์เก่าสัมพันธ์</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <table class="footer-table">
        <tr>
            <td style="text-align: left;">
                ออก ณ วันที่ {{ now()->addYears(543)->locale('th')->translatedFormat('d F') }} {{ now()->year + 543 }} | ระบบระเบียนกิจกรรมนักศึกษา
            </td>
            <td style="text-align: right;">
                Document Ref: <strong>{{ $docNumber }}</strong>
            </td>
        </tr>
    </table>

</body>
</html>
