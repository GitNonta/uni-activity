@extends('layouts.admin')
@section('title', 'ส่งออกรายงาน')

@section('content')
<div class="flex flex-col mb-6">
    <h1 class="font-bold" style="font-size:1.5rem;">ส่งออกรายงาน</h1>
    <p class="text-muted text-sm">ส่งออกข้อมูลระบบเป็นไฟล์ Excel</p>
</div>

<div class="grid-2">
    <!-- ส่งออกรายชื่อนักศึกษา -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                รายชื่อนักศึกษา
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.exports.students') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">คณะ</label>
                    <select name="faculty" class="form-control">
                        <option value="">ทั้งหมด</option>
                        @foreach(array_keys(config('faculties', [])) as $faculty)
                            <option value="{{ $faculty }}">{{ $faculty }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ชั้นปี</label>
                    <select name="year" class="form-control">
                        <option value="">ทั้งหมด</option>
                        @for($i = 1; $i <= 6; $i++)
                            <option value="{{ $i }}">ปี {{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ภาคเรียน</label>
                    <select name="program" class="form-control">
                        <option value="">ทั้งหมด</option>
                        <option value="ปกติ">ปกติ (จันทร์-ศุกร์)</option>
                        <option value="กศ.บป.">กศ.บป. (เสาร์-อาทิตย์)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">สถานะ</label>
                    <select name="status" class="form-control">
                        <option value="">ทั้งหมด</option>
                        <option value="active">ใช้งานได้</option>
                        <option value="inactive">ระงับ</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    ส่งออก Excel
                </button>
            </form>
        </div>
    </div>

    <!-- ส่งออกรายการกิจกรรม -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                รายการกิจกรรม
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.exports.activities') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">หมวดหมู่</label>
                    <select name="category" class="form-control">
                        <option value="">ทั้งหมด</option>
                        @foreach(\App\Models\ActivityCategory::all() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">สถานะ</label>
                    <select name="status" class="form-control">
                        <option value="">ทั้งหมด</option>
                        <option value="upcoming">จะมาถึง</option>
                        <option value="ongoing">กำลังดำเนินการ</option>
                        <option value="completed">เสร็จสิ้น</option>
                        <option value="cancelled">ยกเลิก</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">วันที่เริ่มต้น</label>
                    <input type="date" name="date_from" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">วันที่สิ้นสุด</label>
                    <input type="date" name="date_to" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    ส่งออก Excel
                </button>
            </form>
        </div>
    </div>

    <!-- ส่งออกสถิติระบบ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                สถิติระบบ
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.exports.statistics') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">วันที่เริ่มต้น</label>
                    <input type="date" name="date_from" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">วันที่สิ้นสุด</label>
                    <input type="date" name="date_to" class="form-control">
                </div>
                <div class="alert alert-info">
                    <small>
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 4px; vertical-align: middle;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        ส่งออกสถิติทั้งหมด: นักศึกษา, กิจกรรม, Feedback, แบ่งตามคณะ/ปี
                    </small>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    ส่งออก Excel
                </button>
            </form>
        </div>
    </div>

    <!-- ส่งออกข้อมูลเฉพาะ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                ข้อมูลเฉพาะ
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.exports.student-attendances') }}" class="mb-4">
                @csrf
                <div class="form-group">
                    <label class="form-label">รหัสนักศึกษา</label>
                    <input type="text" name="student_id" class="form-control" placeholder="63012345" required>
                </div>
                <button type="submit" class="btn btn-outline btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    ประวัติการเข้าร่วม
                </button>
            </form>

            <form method="POST" action="{{ route('admin.exports.activity-details') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">รหัสกิจกรรม</label>
                    <input type="number" name="activity_id" class="form-control" placeholder="123" required>
                </div>
                <button type="submit" class="btn btn-outline btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    รายละเอียดกิจกรรม
                </button>
            </form>
        </div>
    </div>
</div>

<div class="alert alert-success mt-6">
    <h4 class="alert-title">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px; margin-right: 8px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
        เคล็ดลับ
    </h4>
    <ul class="alert-list">
        <li>ไฟล์ Excel จะมีการจัดรูปแบบสวยงามพร้อมสีและเส้นขอบ</li>
        <li>สามารถกรองข้อมูลก่อนส่งออกเพื่อลดขนาดไฟล์</li>
        <li>ชื่อไฟล์จะมีวันที่และเวลาเพื่อไม่ให้ซ้ำกัน</li>
        <li>รองรับข้อมูลภาษาไทยอย่างสมบูรณ์</li>
    </ul>
</div>
@endsection
