<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Official Activity Transcript - {{ $student->student_id }}</title>
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

        /* ── Audit Status Banner ── */
        .audit-badge-box {
            padding: 6px 12px;
            border-radius: 4px;
            margin-bottom: 8px;
            text-align: center;
        }
        .audit-pass {
            background-color: #ecfdf5;
            border: 1px solid #059669;
            color: #065f46;
        }
        .audit-fail {
            background-color: #fef2f2;
            border: 1px solid #dc2626;
            color: #991b1b;
        }
        .audit-badge-title {
            font-size: 10.5pt;
            font-weight: bold;
        }
        .audit-badge-desc {
            font-size: 8.5pt;
            margin-top: 1px;
        }

        /* ── Section Titles & Tables ── */
        .table-section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 6px 0 3px;
            border-left: 3px solid #1e3a8a;
            padding-left: 6px;
            line-height: 1.2;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
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

    {{-- ส่วนหัวทางการ: ตราสัญลักษณ์มหาวิทยาลัย + หัวเรื่องสองภาษา + QR Code --}}
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
                <div class="doc-title-th">ใบแสดงผลการเข้าร่วมกิจกรรมพัฒนานักศึกษาตลอดหลักสูตร</div>
                <div class="doc-title-en">OFFICIAL ACTIVITY TRANSCRIPT</div>
            </td>
            <td style="width: 72px; text-align: right;">
                @if(!empty($qrCodeBase64))
                    <img src="data:image/svg+xml;base64,{{ $qrCodeBase64 }}" style="width: 64px; height: 64px; border: 1px solid #cbd5e1; padding: 1px;">
                    <div style="font-size: 6.5pt; color: #64748b; text-align: center; margin-top: 1px;">สแกนตรวจสอบ</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="header-line"></div>

    {{-- ข้อมูลนักศึกษา (กรอบมาตรฐานข้อมูลประวัติ) --}}
    <table class="info-wrap-table">
        <tr>
            <td style="vertical-align: top;">
                <table class="info-table">
                    <tr>
                        <td class="info-label" style="width: 110px;">รหัสนักศึกษา:</td>
                        <td class="info-val" style="width: 140px; font-weight: bold;">{{ $student->student_id }}</td>
                        <td class="info-label" style="width: 95px;">ชื่อ-นามสกุล:</td>
                        <td class="info-val" style="font-weight: bold;">{{ $student->full_name }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Student Name:</td>
                        <td class="info-val">{{ $student->english_name ?: '-' }}</td>
                        <td class="info-label">ระดับการศึกษา:</td>
                        <td class="info-val">ปริญญาตรี (ชั้นปีที่ {{ $student->year ?: '-' }})</td>
                    </tr>
                    <tr>
                        <td class="info-label">คณะ (Faculty):</td>
                        <td class="info-val">{{ $student->faculty ?: '-' }}</td>
                        <td class="info-label">สาขาวิชา:</td>
                        <td class="info-val">{{ $student->department ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">เกณฑ์หลักสูตร:</td>
                        <td class="info-val" colspan="3">{{ $audit['criteria']->name ?? 'เกณฑ์มาตรฐานมหาวิทยาลัย' }}</td>
                    </tr>
                </table>
            </td>
            @if(!empty($studentPhotoBase64))
            <td style="width: 68px; text-align: right; vertical-align: top; padding-left: 8px;">
                <img src="{{ $studentPhotoBase64 }}" style="width: 60px; height: 75px; object-fit: cover; border: 1px solid #cbd5e1; border-radius: 2px;">
            </td>
            @endif
        </tr>
    </table>

    {{-- ผลการตรวจสอบการสำเร็จการศึกษา (Graduation Audit Stamp) --}}
    @if($audit['is_eligible'])
        <div class="audit-badge-box audit-pass">
            <div class="audit-badge-title">ผ่านเกณฑ์กิจกรรมพัฒนานักศึกษาเพื่อการสำเร็จการศึกษา (GRADUATION REQUIREMENT MET)</div>
            <div class="audit-badge-desc">นักศึกษามีชั่วโมงกิจกรรมครบถ้วนตามเกณฑ์หลักสูตร (สะสมได้ {{ number_format($audit['total_hours'], 1) }} / เกณฑ์ {{ number_format($audit['min_total_hours'], 1) }} ชม.)</div>
        </div>
    @else
        <div class="audit-badge-box audit-fail">
            <div class="audit-badge-title">อยู่ระหว่างการสะสมชั่วโมงกิจกรรม (IN PROGRESS - REQUIREMENT NOT YET MET)</div>
            <div class="audit-badge-desc">
                ชั่วโมงสะสมรวม {{ number_format($audit['total_hours'], 1) }} / เกณฑ์ {{ number_format($audit['min_total_hours'], 1) }} ชม.
                (ยังขาดอีก {{ number_format(max(0, $audit['min_total_hours'] - $audit['total_hours']), 1) }} ชม. ให้ครบตามโครงสร้างเกณฑ์หลักสูตร)
            </div>
        </div>
    @endif

    {{-- 1. ตารางสรุปการประเมินตามโครงสร้างเกณฑ์การสำเร็จการศึกษา (Summary by Criteria) --}}
    <div class="table-section-title">1. สรุปผลการเข้าร่วมกิจกรรมตามเกณฑ์โครงสร้างหลักสูตร (Curriculum Activity Summary)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 46%;">ประเภท / หมวดหมู่กิจกรรมตามเกณฑ์</th>
                <th style="width: 18%;">เกณฑ์ที่กำหนด (ชม.)</th>
                <th style="width: 18%;">ชั่วโมงสะสม (ชม.)</th>
                <th style="width: 18%;">ผลการประเมิน</th>
            </tr>
        </thead>
        <tbody>
            {{-- ขอบเขต: มหาวิทยาลัย --}}
            @foreach($audit['scope_audit'] as $scopeKey => $scope)
            <tr>
                <td>{{ $scope['name'] }}</td>
                <td style="text-align: center;">{{ number_format($scope['required'], 1) }}</td>
                <td style="text-align: center; font-weight: bold;">{{ number_format($scope['earned'], 1) }}</td>
                <td style="text-align: center;">
                    @if($scope['passed'])
                        <span class="badge-pass">ผ่านเกณฑ์</span>
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
                <td style="text-align: center;">{{ number_format($cat['required'], 1) }}</td>
                <td style="text-align: center; font-weight: bold;">{{ number_format($cat['earned'], 1) }}</td>
                <td style="text-align: center;">
                    @if($cat['passed'])
                        <span class="badge-pass">ผ่านเกณฑ์</span>
                    @else
                        <span class="badge-fail">ขาด {{ number_format($cat['deficit'], 1) }} ชม.</span>
                    @endif
                </td>
            </tr>
            @endforeach

            <tr class="total-row">
                <td style="font-weight: bold;">รวมชั่วโมงกิจกรรมตลอดหลักสูตร</td>
                <td style="text-align: center; font-weight: bold;">{{ number_format($audit['min_total_hours'], 1) }}</td>
                <td style="text-align: center; font-weight: bold; color: #1e3a8a;">{{ number_format($audit['total_hours'], 1) }}</td>
                <td style="text-align: center;">
                    @if($audit['is_eligible'])
                        <span class="badge-pass">ครบเกณฑ์สมบูรณ์</span>
                    @else
                        <span class="badge-fail">ยังไม่ครบเกณฑ์</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    {{-- 2. ประวัติการเข้าร่วมกิจกรรมตลอดหลักสูตร (Detailed Activity History) --}}
    <div class="table-section-title">2. บัญชีรายการกิจกรรมที่ได้รับการรับรอง (Certified Activity Records)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">วันที่</th>
                <th style="text-align: left; width: 48%;">ชื่อกิจกรรม / โครงการ</th>
                <th style="width: 24%;">หมวดหมู่ / ขอบเขต</th>
                <th style="width: 16%;">ชั่วโมงกิจกรรม</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $att)
            <tr>
                <td style="text-align: center;">{{ $att->checked_in_at ? $att->checked_in_at->format('d/m/Y') : ($att->activity?->activity_date ? $att->activity->activity_date->format('d/m/Y') : '-') }}</td>
                <td>{{ $att->activity?->title ?? '-' }}</td>
                <td style="font-size: 8pt;">
                    {{ $att->activity?->category?->name ?? 'ทั่วไป' }}
                    <span style="color: #64748b;">({{ $att->activity?->scope === 'university' ? 'ระดับมหาวิทยาลัย' : 'ระดับคณะ' }})</span>
                </td>
                <td style="text-align: center; font-weight: bold;">{{ number_format((float) ($att->activity?->activity_hours ?? 0), 1) }} ชม.</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center; color: #64748b; padding: 6px;">
                    ยังไม่มีบันทึกประวัติการเข้าร่วมกิจกรรมที่ได้รับการอนุมัติในระบบ
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ลายมือชื่อทางการ (2 คอลัมน์สมบูรณ์) --}}
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-title">ผู้ตรวจสอบข้อมูลกิจกรรม (Audited by)</div>
                @if(!empty($sig1Base64))
                    <img src="{{ $sig1Base64 }}" class="sig-img">
                @else
                    <div class="sig-placeholder"></div>
                @endif
                <div class="sig-name">( อาจารย์ ดร.สมชาย ใจดี )</div>
                <div class="sig-pos">ผู้อำนวยการกองพัฒนานักศึกษา</div>
            </td>
            <td>
                <div class="sig-title">ผู้อนุมัติเอกสารและรับรองผล (Approved by)</div>
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

    {{-- Footer ประทับเวลาและเลขที่เอกสาร --}}
    <table class="footer-table">
        <tr>
            <td style="text-align: left;">
                เอกสารทางการออก ณ วันที่ {{ $issueDateThai ?? $issueDate->format('d/m/Y') }} | ตรวจสอบความถูกต้องผ่านระบบระเบียนกิจกรรมนักศึกษาดิจิทัล
            </td>
            <td style="text-align: right;">
                Document Ref: <strong>{{ $docNumber }}</strong>
            </td>
        </tr>
    </table>

</body>
</html>
