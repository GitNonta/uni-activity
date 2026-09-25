<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Official Activity Transcript - {{ $student->student_id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Garuda', 'DejaVu Sans', 'TH Sarabun PSK', sans-serif;
            font-size: 10.5pt;
            color: #0f172a;
            line-height: 1.4;
            letter-spacing: 0.02em;
        }
        .page {
            padding: 1.2cm 1.5cm 1cm;
            position: relative;
        }

        /* ── Watermark ── */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 280px;
            height: 380px;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            z-index: -1;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .uni-en {
            font-size: 12pt;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .uni-th {
            font-size: 12pt;
            font-weight: bold;
            color: #1e293b;
        }
        .doc-title-en {
            font-size: 14pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 4px;
            letter-spacing: 0.08em;
        }
        .doc-title-th {
            font-size: 13pt;
            font-weight: bold;
            color: #1e3a8a;
        }

        .header-line {
            border: none;
            border-top: 2px solid #1e3a8a;
            margin: 8px 0 10px;
        }

        /* ── Student Info ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .info-table td {
            font-size: 10pt;
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

        /* ── Audit Status Box ── */
        .audit-badge-box {
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            text-align: center;
        }
        .audit-pass {
            background-color: #ecfdf5;
            border: 1.5px solid #059669;
            color: #065f46;
        }
        .audit-fail {
            background-color: #fef2f2;
            border: 1.5px solid #dc2626;
            color: #991b1b;
        }
        .audit-badge-title {
            font-size: 12pt;
            font-weight: bold;
        }
        .audit-badge-desc {
            font-size: 9.5pt;
            margin-top: 2px;
        }

        /* ── Summary & Detail Tables ── */
        .table-section-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 10px 0 4px;
            border-left: 3px solid #1e3a8a;
            padding-left: 6px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th {
            font-size: 9.5pt;
            font-weight: bold;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-align: center;
        }
        .data-table td {
            font-size: 9pt;
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            color: #1e293b;
        }
        .data-table tr.total-row td {
            font-weight: bold;
            background-color: #f1f5f9;
        }

        .badge-pass {
            color: #059669;
            font-weight: bold;
        }
        .badge-fail {
            color: #dc2626;
            font-weight: bold;
        }

        /* ── Signatures ── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .sig-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .sig-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .sig-img {
            height: 45px;
            margin: 2px auto;
        }
        .sig-name {
            font-size: 9.5pt;
            color: #334155;
        }
        .sig-pos {
            font-size: 8.5pt;
            color: #64748b;
        }

        /* ── Footer ── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
<div class="page">

    {{-- Watermark --}}
    @if(!empty($watermarkPath) && file_exists($watermarkPath))
        <img src="{{ $watermarkPath }}" class="watermark">
    @endif

    {{-- ส่วนหัวทางการ: ตราสัญลักษณ์มหาวิทยาลัย + หัวเรื่องสองภาษา + QR Code --}}
    <table class="header-table">
        <tr>
            <td style="width:85px;text-align:left;">
                @if(!empty($emblemPath) && file_exists($emblemPath))
                    <img src="{{ $emblemPath }}" style="width:80px;height:auto;max-height:100px;">
                @endif
            </td>
            <td style="text-align:center;padding:0 10px;">
                <div class="uni-en">Magic and Digital Technology University</div>
                <div class="uni-th">มหาวิทยาลัยเวทย์มนต์และเทคโนโลยีดิจิทัล</div>
                <div class="doc-title-en">OFFICIAL ACTIVITY TRANSCRIPT</div>
                <div class="doc-title-th">ใบแสดงผลการเข้าร่วมกิจกรรมพัฒนานักศึกษาตลอดหลักสูตร</div>
            </td>
            <td style="width:85px;text-align:right;">
                @if(!empty($qrCodeBase64))
                    <img src="data:image/svg+xml;base64,{{ $qrCodeBase64 }}" style="width:75px;height:75px;border:1px solid #e2e8f0;padding:2px;">
                    <div style="font-size:6.5pt;color:#64748b;text-align:center;margin-top:2px;">สแกนตรวจสอบ</div>
                @endif
            </td>
        </tr>
    </table>

    <hr class="header-line">

    {{-- ข้อมูลนักศึกษา --}}
    <table class="info-table">
        <tr>
            <td class="info-label" style="width:85px;">รหัสนักศึกษา:</td>
            <td class="info-val" style="width:130px;font-weight:bold;">{{ $student->student_id }}</td>
            <td class="info-label" style="width:80px;">ชื่อ-นามสกุล:</td>
            <td class="info-val" style="font-weight:bold;">{{ $student->full_name }}</td>
            <td rowspan="3" style="width:70px;text-align:right;vertical-align:middle;">
                @if(!empty($studentPhoto) && file_exists($studentPhoto))
                    <img src="{{ $studentPhoto }}" style="width:65px;height:80px;object-fit:cover;border:1px solid #cbd5e1;border-radius:2px;">
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-label">Student Name:</td>
            <td class="info-val" colspan="3">{{ $student->english_name ?: '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">คณะ (Faculty):</td>
            <td class="info-val">{{ $student->faculty ?: '-' }}</td>
            <td class="info-label">สาขาวิชา:</td>
            <td class="info-val">{{ $student->department ?: '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">ระดับการศึกษา:</td>
            <td class="info-val">ปริญญาตรี (ชั้นปีที่ {{ $student->year ?: '-' }})</td>
            <td class="info-label">เกณฑ์หลักสูตร:</td>
            <td class="info-val" colspan="2">{{ $audit['criteria']->name ?? 'เกณฑ์มาตรฐานมหาวิทยาลัย' }}</td>
        </tr>
    </table>

    {{-- ผลการตรวจสอบการสำเร็จการศึกษา (Graduation Audit Stamp) --}}
    @if($audit['is_eligible'])
        <div class="audit-badge-box audit-pass">
            <div class="audit-badge-title">✓ ผ่านเกณฑ์กิจกรรมพัฒนานักศึกษาเพื่อการสำเร็จการศึกษา (GRADUATION REQUIREMENT MET)</div>
            <div class="audit-badge-desc">นักศึกษามีชั่วโมงกิจกรรมครบถ้วนตามข้อบังคับมหาวิทยาลัยและโครงสร้างหลักสูตร (สะสมได้ {{ number_format($audit['total_hours'], 1) }} / เกณฑ์ {{ number_format($audit['min_total_hours'], 1) }} ชม.)</div>
        </div>
    @else
        <div class="audit-badge-box audit-fail">
            <div class="audit-badge-title">! อยู่ระหว่างการสะสมชั่วโมงกิจกรรม (IN PROGRESS - REQUIREMENT NOT YET MET)</div>
            <div class="audit-badge-desc">
                ชั่วโมงสะสมรวม {{ number_format($audit['total_hours'], 1) }} / {{ number_format($audit['min_total_hours'], 1) }} ชม.
                @if(!empty($audit['missing_requirements']))
                    ({{ implode(', ', $audit['missing_requirements']) }})
                @endif
            </div>
        </div>
    @endif

    {{-- 1. ตารางสรุปการประเมินตามโครงสร้างเกณฑ์การสำเร็จการศึกษา (Summary by Criteria) --}}
    <div class="table-section-title">1. สรุปผลการเข้าร่วมกิจกรรมตามเกณฑ์โครงสร้างหลักสูตร (Curriculum Activity Summary)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="text-align:left;width:40%;">ประเภท / หมวดหมู่กิจกรรมตามเกณฑ์</th>
                <th style="width:20%;">เกณฑ์ที่กำหนด (ชม.)</th>
                <th style="width:20%;">ชั่วโมงที่สะสมได้ (ชม.)</th>
                <th style="width:20%;">ผลการประเมิน</th>
            </tr>
        </thead>
        <tbody>
            {{-- ขอบเขต: มหาวิทยาลัย --}}
            @foreach($audit['scope_audit'] as $scopeKey => $scope)
            <tr>
                <td>{{ $scope['name'] }}</td>
                <td style="text-align:center;">{{ number_format($scope['required'], 1) }}</td>
                <td style="text-align:center;font-weight:bold;">{{ number_format($scope['earned'], 1) }}</td>
                <td style="text-align:center;">
                    @if($scope['passed'])
                        <span class="badge-pass">ผ่าน (PASS)</span>
                    @else
                        <span class="badge-fail">ขาด {{ number_format($scope['deficit'], 1) }} ชม.</span>
                    @endif
                </td>
            </tr>
            @endforeach

            {{-- หมวดหมู่: จิตอาสา หรืออื่นๆ --}}
            @foreach($audit['category_audit'] as $catKey => $cat)
            <tr>
                <td>{{ $cat['name'] }}</td>
                <td style="text-align:center;">{{ number_format($cat['required'], 1) }}</td>
                <td style="text-align:center;font-weight:bold;">{{ number_format($cat['earned'], 1) }}</td>
                <td style="text-align:center;">
                    @if($cat['passed'])
                        <span class="badge-pass">ผ่าน (PASS)</span>
                    @else
                        <span class="badge-fail">ขาด {{ number_format($cat['deficit'], 1) }} ชม.</span>
                    @endif
                </td>
            </tr>
            @endforeach

            <tr class="total-row">
                <td style="font-weight:bold;">รวมชั่วโมงกิจกรรมตลอดหลักสูตร</td>
                <td style="text-align:center;font-weight:bold;">{{ number_format($audit['min_total_hours'], 1) }}</td>
                <td style="text-align:center;font-weight:bold;color:#1e3a8a;">{{ number_format($audit['total_hours'], 1) }}</td>
                <td style="text-align:center;">
                    @if($audit['is_eligible'])
                        <span class="badge-pass">สมบูรณ์ (PASSED)</span>
                    @else
                        <span class="badge-fail">ยังไม่ครบเกณฑ์</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    {{-- 2. ประวัติการเข้าร่วมกิจกรรมตลอดหลักสูตร (Detailed Activity History) --}}
    <div class="table-section-title">2. บัญชีรายการกิจกรรมที่ได้รับการรับรอง (Certified Activity Records)</div>
    @if($attendances->isEmpty())
        <div style="font-size:9pt;color:#64748b;padding:8px;border:1px dashed #cbd5e1;text-align:center;">
            ยังไม่มีบันทึกประวัติการเข้าร่วมกิจกรรมที่ได้รับการอนุมัติในระบบ
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:12%;">วันที่</th>
                    <th style="text-align:left;width:48%;">ชื่อกิจกรรม</th>
                    <th style="width:22%;">หมวดหมู่ / ระดับ</th>
                    <th style="width:8%;">ชั่วโมง</th>
                    <th style="width:10%;">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($attendances as $att)
                <tr>
                    <td style="text-align:center;">{{ $att->checked_in_at ? $att->checked_in_at->format('d/m/Y') : ($att->activity?->activity_date ? $att->activity->activity_date->format('d/m/Y') : '-') }}</td>
                    <td>{{ $att->activity?->title ?? '-' }}</td>
                    <td style="font-size:8.5pt;">
                        {{ $att->activity?->category?->name ?? 'ทั่วไป' }}
                        <span style="color:#64748b;">({{ $att->activity?->scope === 'university' ? 'มหาวิทยาลัย' : 'คณะ' }})</span>
                    </td>
                    <td style="text-align:center;font-weight:bold;">{{ number_format((float) ($att->activity?->activity_hours ?? 0), 1) }}</td>
                    <td style="text-align:center;color:#059669;font-weight:bold;font-size:8pt;">อนุมัติแล้ว</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ════════ ลายมือชื่อทางการ ════════ --}}
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-title">ผู้ตรวจสอบข้อมูลกิจกรรม</div>
                <div style="height:6px;"></div>
                @if(!empty($sig1Path) && file_exists($sig1Path))
                    <img src="{{ $sig1Path }}" class="sig-img">
                @else
                    <div style="height:45px;"></div>
                @endif
                <div class="sig-name">( อาจารย์ ดร.สมชาย ใจดี )</div>
                <div class="sig-pos">ผู้อำนวยการกองพัฒนานักศึกษา</div>
            </td>
            <td>
                <div class="sig-title">ผู้อนุมัติเอกสารและรับรองผล</div>
                <div style="height:6px;"></div>
                @if(!empty($sig2Path) && file_exists($sig2Path))
                    <img src="{{ $sig2Path }}" class="sig-img">
                @else
                    <div style="height:45px;"></div>
                @endif
                <div class="sig-name">( ผู้ช่วยศาสตราจารย์ ดร.วิภาดา วิจิตรศิลป์ )</div>
                <div class="sig-pos">รองอธิการบดีฝ่ายพัฒนานักศึกษาและศิษย์เก่าสัมพันธ์</div>
            </td>
        </tr>
    </table>

    {{-- Footer ประทับเวลาและเลขที่เอกสาร --}}
    <table class="footer-table">
        <tr>
            <td style="text-align:left;">
                เอกสารทางการออก ณ วันที่ {{ $issueDate->addYears(543)->locale('th')->translatedFormat('d F') }} {{ $issueDate->year + 543 }} | ตรวจสอบผ่านระบบระเบียนกิจกรรมนักศึกษา
            </td>
            <td style="text-align:right;">
                Document Ref: <strong>{{ $docNumber }}</strong>
            </td>
        </tr>
    </table>

</div>
</body>
</html>
