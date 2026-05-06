<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    @php
        $logoPath      = public_path('images/pkru-logo.jpg');
        $emblemPath    = public_path('images/--removebg-preview.png');
        $watermarkPath = public_path('images/sd-removebg-preview.png');
        $sig1Path      = public_path('images/signatures/signature1.png');
        $sig2Path      = public_path('images/signatures/signature2.png');
        $studentPhoto  = $user->profile_photo
            ? storage_path('app/public/' . $user->profile_photo)
            : null;

        $levelLabel  = 'ควรปรับปรุง';
        $pct = $totalRequired > 0 ? ($totalHours / $totalRequired) * 100 : 0;
        if ($pct >= 100)     $levelLabel = 'ดีเยี่ยม';
        elseif ($pct >= 80)  $levelLabel = 'ดีมาก';
        elseif ($pct >= 60)  $levelLabel = 'ดี';
        elseif ($pct >= 40)  $levelLabel = 'พอใช้';

        $docNumber = $user->student_id . now()->format('dmY') . rand(100,999);
    @endphp
    <style>
        @font-face {
            font-family: 'sarabun';
            src: url('{{ storage_path("fonts/Sarabun-Regular.ttf") }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'sarabun';
            src: url('{{ storage_path("fonts/Sarabun-Bold.ttf") }}') format('truetype');
            font-weight: bold;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'sarabun', 'TH Sarabun PSK', 'TH SarabunPSK', sans-serif;
            font-size: 13pt;
            color: #1a2744;
            line-height: 1.6;
            /* แก้ปัญหาสระซ้อน - เพิ่มระยะห่างตัวอักษร */
            letter-spacing: 0.03em;
            word-spacing: 0.08em;
        }
        .page {
            padding: 1.2cm 1.8cm 1cm;
            position: relative;
        }

        /* ─── Watermark ─── */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 230px;
            height: 330px;
            transform: translate(-50%, -50%);
            opacity: 0.06;
            z-index: -1;
        }

        /* ─── Header ─── */
        .header-table {
            width: 100%;
            border-collapse: collapse; 
            margin-bottom: 4px; 
        }
        .header-table td {
            vertical-align: middle;
            padding: 0;
        }
        .header-center {
            text-align: center;
        }
        .uni-en {
            font-size: 14pt;
            color: #000000;
             text-align:left;
            padding-left: 10px;
        }
        .uni-th {
            font-size: 14pt;
            color: #000000;
             text-align:left;
             padding-left: 10px;
        }
        .transcript-en {
            font-size: 13pt;
            color: #000000;
            margin-top: 6px;
        }
        .transcript-th {
            font-size: 13pt;
            color: #000000;
            margin-top: 6px;
        }

        .blue-line {
            border: none;
            border-top: 2.5px solid none;
            margin: 6px 0 10px;
        }

        /* ─── Student Info ─── */
        .info-table {
            width: 100%;
            border-collapse: collapse; 
            margin-bottom: 8px;
        }
        .info-table td {
            font-size: 10.5pt;
            padding: 1.5px 3px;
            vertical-align: top; 
        }
        .info-label {
            font-weight: 10px;
            white-space: nowrap;
            color: #000000;
        }
        

        /* ─── Category Table ─── */
        .cat-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .cat-table th {
            font-size: 11pt;
            font-weight: bold;
            color: #000000;
            padding: 5px 8px;
            border-bottom: 1.5px solid #1a2744;
            text-align: left;
        }
        .cat-table th.r {
            text-align: right;
        }
        .cat-table td {
            font-size: 10.5pt;
            padding: 3.5px 8px;
            border-bottom: none;
            color: #000000;
        }
        .cat-table td.r {
            text-align: right;
        }
        .cat-table .indent {
            padding-left: 20px;
        }
        .cat-table tr.total-row td {
            font-weight: bold;
            font-size: 11pt;
            color: #1a2744;
            border-top: 1.5px solid #1a2744;
            border-bottom: 1.5px solid #1a2744;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        /* ─── Result / Certificate ─── */
        .result-box {
            margin: 10px 0;
            padding: 0 5px;
            font-size: 10.5pt;
            line-height: 1.6;
        }
        .result-box .level {
            font-weight: bold;
            text-decoration: underline;
        }
        .cert-text {
            text-align: center;
            font-size: 11.5pt;
            font-weight: bold;
            color: #1a2744;
            margin: 18px 0 5px;
            line-height: 1.7;
        }
        .cert-detail {
            text-align: center;
            font-size: 10pt;
            color: #333;
            line-height: 1.6;
        }

        /* ─── Signature Area ─── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .sig-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 10pt;
            padding: 0 10px;
            color: #333;
        }
        .sig-title {
            font-weight: bold;
            font-size: 10.5pt;
            color: #1a2744;
            margin-bottom: 2px;
        }
        .sig-img {
            height: 50px;
            margin: 2px auto;
        }
        .sig-name {
            font-size: 10pt;
            margin-top: 0;
        }
        .sig-pos {
            font-size: 9pt;
            font-weight: bold;
            color: #1a2744;
        }

        /* ─── Footer ─── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-top: 1px solid #999;
            padding-top: 4px;
        }
        .footer-table td {
            font-size: 8pt;
            color: #888;
            padding-top: 4px;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ═══════════════════ Watermark พื้นหลังโปร่งแสง ═══════════════════ --}}
    @if(file_exists($watermarkPath))
        <img src="{{ $watermarkPath }}" class="watermark">
    @endif

    {{-- ═══════════════════ ส่วนหัว: โลโก้ + ชื่อมหาวิทยาลัย ═══════════════════ --}}
    <table class="header-table">
        <tr>
            <td style="width:70px;">
                @if(file_exists($emblemPath))
                    <img src="{{ $emblemPath }}" style="width:100px;height:130px; margin-top: -50px; ">
                @endif
            </td>
            <td class="header-center">
                <div class="uni-en">Magic and Digital Technology University</div>
                <div class="uni-th">มหาวิทยาลัยเวทย์มนต์และเทคโนโลยีดิจิทัล</div>
                <div style="height:1px;"></div>
                <div class="transcript-en">Activity Transcript</div>
                <div class="transcript-th">ใบแสดงผลการเข้าร่วมกิจกรรมนักศึกษา</div>
            </td>
            <td style="width:75px;text-align:right;">
                @if($studentPhoto && file_exists($studentPhoto))
                    <img src="{{ $studentPhoto }}" style="width:100px;height:130px;object-fit:cover;border:1.5px solid #ccc;">
                @else
                    <div style="width:68px;height:85px;border:1.5px solid #ccc;background:#f1f5f9;text-align:center;line-height:85px;font-size:7pt;color:#94a3b8;">รูปนักศึกษา</div>
                @endif
            </td>
        </tr>
    </table>

    <hr class="blue-line">

    {{-- ═══════════════════ ข้อมูลนักศึกษา ═══════════════════ --}}
    <table class="info-table">
        <tr>
            <td class="info-label" style="width:90px;">รหัสนักศึกษา</td>
            <td class="info-val" style="width:130px;">{{ $user->student_id }}</td>
            <td class="info-label" style="width:65px;">ชื่อ-สกุล</td>
            <td class="info-val">{{ $user->full_name }}</td>
        </tr>
        <tr>
            <td class="info-label">คณะ</td>
            <td class="info-val" colspan="3">{{ $user->faculty ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">สาขาวิชา</td>
            <td class="info-val" colspan="3">{{ $user->department ?? '-' }}</td>
        </tr>
    </table>

    <hr style="border:none;border-top:1px solid #00000000;margin:6px 0 10px;">

    {{-- ═══════════════════ ตารางหมวดหมู่กิจกรรม + ชั่วโมง ═══════════════════ --}}
    <table class="cat-table" style="border-collapse: separate; border-spacing: 0; border: 1px solid #000;">
        <thead>
            <tr>
                <th style="border: 1px solid #000; padding: 5px 8px; text-align: center;">ประเภทกิจกรรม</th>
                <th class="r" style="width:80px; border: 1px solid #000; padding: 5px 8px; text-align: center;">ชั่วโมง</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byCategory as $cat)
            <tr>
                <td class="indent" style="border-top: none; border-bottom: none; border-left: 1px solid #000; border-right: 1px solid #000; padding: 0.5px 8px;">- {{ $cat['name'] }}</td>
                <td class="r" style="border-top: none; border-bottom: none; border-left: 1px solid #000; border-right: 1px solid #000;
                   padding: 0.5px 2px; text-align: center;">{{ $cat['hours'] > 0 ? number_format($cat['hours'], 0) : 0 }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td style="text-align:right;padding-right:30px; border: 1px solid #000; padding: 1px 8px; text-align: center">รวม</td>
                <td class="r" style="border-top: none; border-bottom: none; border-right: 1px solid #000; padding: 5px 8px; text-align: center; border-top: 1px solid #000;" >{{ number_format($totalHours, 0) }}</td>
            </tr>
            @if($totalHours > 0)
            <tr>
                <td colspan="2" style="border: 1px solid #000; padding: 1px; text-align: left; background: none; ">
                    <p style="margin: 0; ">ผลการเข้าร่วมกิจกรรมตามที่มหาวิทยาลัยกำหนด</p>
                    <p style="margin: 4px 0 0;">จำนวน <strong>{{ number_format($totalHours, 0) }}</strong> ชั่วโมง อยู่ในระดับ <span class="level" style="color: #000000;">{{ $levelLabel }}</span></p>
                </td>
            </tr>
            @endif
        </tbody>
    </table>

    

    {{-- ═══════════════════ ข้อความรับรอง ═══════════════════ --}}
    <div class="cert-text">
        ขอรับรองว่าตลอดระยะเวลาที่เคยได้ศึกษา
    </div>
    <div class="cert-detail">
        นักศึกษาเข้าร่วมกิจกรรมตามรายงานที่ได้บันทึกไว้ในระเบียนกิจกรรมนักศึกษาจริงทุกประการ
    </div>

    {{-- ═══════════════════ ลายเซ็น ═══════════════════ --}}
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-title">ผู้ตรวจสอบ</div>
                <div style="height:10px;"></div>
                @if(file_exists($sig1Path))
                    <img src="{{ $sig1Path }}" class="sig-img">
                @else
                    <div style="height:50px;"></div>
                @endif
                <div class="sig-name">( ................................................ )</div>
                <div class="sig-pos">ปฏิบัติหน้าที่ผู้อำนวยการกองพัฒนานักศึกษา</div>
            </td>
            <td>
                <div class="sig-title">ออกให้ ณ วันที่ {{ now()->addYears(543)->locale('th')->translatedFormat('d F') }} {{ now()->year + 543 }}</div>
                <div style="height:10px;"></div>
                @if(file_exists($sig2Path))
                    <img src="{{ $sig2Path }}" class="sig-img">
                @else
                    <div style="height:50px;"></div>
                @endif
                <div class="sig-name">( ................................................ )</div>
                <div class="sig-pos">รองอธิการบดี ปฏิบัติราชการแทน</div>
                <div class="sig-pos">อธิการบดีมหาวิทยาลัยเวทย์มนต์และเทคโนโลยีดิจิทัล </div>
            </td>
        </tr>
    </table>

    {{-- ═══════════════════ Footer ═══════════════════ --}}
    <table class="footer-table">
        <tr>
            <td style="text-align:left;">พิมพ์เมื่อวันที่ {{ now()->addYears(543)->locale('th')->translatedFormat('d F') }} {{ now()->year + 543 }}</td>
            <td style="text-align:right;">เลขที่เอกสาร {{ $docNumber }}</td>
        </tr>
    </table>

</div>
</body>
</html>
