@extends('layouts.admin')
@section('title', 'ส่งออกรายงาน')

@section('content')
<div class="flex flex-col mb-6">
    <h1 class="font-bold" style="font-size:1.5rem;">ส่งออกรายงาน</h1>
    <p class="text-muted text-sm">ส่งออกข้อมูลระบบเป็นไฟล์ Excel</p>
</div>

<div class="grid-2">
    <!-- ส่งออกรายชื่อนักศึกษา + เลือก Field -->
    <div class="card" style="grid-column:1/-1;">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;margin-right:8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                รายชื่อนักศึกษา
                <span id="field-count-badge" style="background:#6366f1;color:#fff;border-radius:999px;padding:2px 10px;font-size:.75rem;margin-left:8px;font-weight:600;">11 fields</span>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.exports.students') }}" id="students-export-form">
                @csrf
                {{-- Filters Row --}}
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
                    <div style="flex:1;min-width:130px;">
                        <label class="form-label">คณะ</label>
                        <select name="faculty" class="form-control">
                            <option value="">ทั้งหมด</option>
                            @foreach(array_keys(config('faculties', [])) as $faculty)
                                <option value="{{ $faculty }}">{{ $faculty }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:100px;">
                        <label class="form-label">ชั้นปี</label>
                        <select name="year" class="form-control">
                            <option value="">ทั้งหมด</option>
                            @for($i = 1; $i <= 6; $i++)
                                <option value="{{ $i }}">ปี {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div style="min-width:130px;">
                        <label class="form-label">ภาคเรียน</label>
                        <select name="program" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <option value="ปกติ">ปกติ (จ-ศ)</option>
                            <option value="กศ.บป.">กศ.บป. (ส-อ)</option>
                        </select>
                    </div>
                    <div style="min-width:120px;">
                        <label class="form-label">สถานะบัญชี</label>
                        <select name="status" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <option value="active">ใช้งานได้</option>
                            <option value="inactive">ระงับ</option>
                        </select>
                    </div>
                </div>
                {{-- Field Selector --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem;margin-bottom:1.25rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
                        <p class="font-semi text-sm" style="color:#334155;">เลือก Field ที่ต้องการ Export</p>
                        <div style="display:flex;gap:.5rem;">
                            <button type="button" onclick="toggleAllFields(true)"  class="btn btn-outline btn-sm" style="font-size:.75rem;">เลือกทั้งหมด</button>
                            <button type="button" onclick="toggleAllFields(false)" class="btn btn-outline btn-sm" style="font-size:.75rem;">ยกเลิกทั้งหมด</button>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.5rem;">
                        @php
                            $availableFields = [
                                'student_id'    => 'รหัสนักศึกษา',
                                'full_name'     => 'ชื่อ-นามสกุล',
                                'faculty'       => 'คณะ',
                                'department'    => 'สาขาวิชา',
                                'year'          => 'ชั้นปี',
                                'program'       => 'ภาคเรียน',
                                'email'         => 'อีเมล',
                                'is_active'     => 'สถานะบัญชี',
                                'total_hours'   => 'ชั่วโมงทั้งหมด',
                                'approved_count'=> 'จำนวนกิจกรรม',
                                'created_at'    => 'วันที่สมัคร',
                            ];
                        @endphp
                        @foreach($availableFields as $key => $label)
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.4rem .6rem;border-radius:8px;transition:background .15s;"
                               onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='transparent'">
                            <input type="checkbox" name="fields[]" value="{{ $key }}" checked
                                   class="field-checkbox"
                                   style="width:16px;height:16px;accent-color:#6366f1;cursor:pointer;"
                                   onchange="updateFieldCount()">
                            <span style="font-size:.85rem;color:#374151;">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;margin-right:6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    ส่งออก Excel (<span id="field-count-btn">11</span> columns)
                </button>
            </form>
        </div>
    </div>

    <!-- ส่งออกรายการกิจกรรม -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;margin-right:8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
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
                        <option value="done">เสร็จสิ้น</option>
                        <option value="cancelled">ยกเลิก</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">วันที่เริ่มต้น</label>
                        <input type="date" name="date_from" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" name="date_to" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;margin-right:6px;">
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
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;margin-right:8px;">
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
                    <small>ส่งออกสถิติทั้งหมด: นักศึกษา, กิจกรรม, Feedback, แบ่งตามคณะ/ปี</small>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;margin-right:6px;">
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
            <h3 class="card-title">ข้อมูลเฉพาะ</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.exports.student-attendances') }}" class="mb-4">
                @csrf
                <div class="form-group">
                    <label class="form-label">รหัสนักศึกษา</label>
                    <input type="text" name="student_id" class="form-control" placeholder="63012345" required>
                </div>
                <button type="submit" class="btn btn-outline btn-block">ประวัติการเข้าร่วม</button>
            </form>
            <form method="POST" action="{{ route('admin.exports.activity-details') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">รหัสกิจกรรม</label>
                    <input type="number" name="activity_id" class="form-control" placeholder="123" required>
                </div>
                <button type="submit" class="btn btn-outline btn-block">รายละเอียดกิจกรรม</button>
            </form>
        </div>
    </div>
</div>

<div class="alert alert-success mt-6">
    <h4 class="alert-title">เคล็ดลับ</h4>
    <ul class="alert-list">
        <li>ไฟล์ Excel จะมีการจัดรูปแบบสวยงามพร้อมสีและเส้นขอบ</li>
        <li>เลือก field เฉพาะที่ต้องการเพื่อลดขนาดไฟล์</li>
        <li>ชื่อไฟล์จะมีวันที่และเวลาเพื่อไม่ให้ซ้ำกัน</li>
        <li>รองรับข้อมูลภาษาไทยอย่างสมบูรณ์</li>
    </ul>
</div>

<script>
function updateFieldCount() {
    const checked = document.querySelectorAll('.field-checkbox:checked').length;
    document.getElementById('field-count-badge').textContent = checked + ' fields';
    document.getElementById('field-count-btn').textContent = checked;
}
function toggleAllFields(state) {
    document.querySelectorAll('.field-checkbox').forEach(cb => cb.checked = state);
    updateFieldCount();
}
// ป้องกัน submit ถ้าไม่ได้เลือก field
document.getElementById('students-export-form').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.field-checkbox:checked').length;
    if (checked === 0) {
        e.preventDefault();
        alert('กรุณาเลือก field อย่างน้อย 1 field');
    }
});
</script>
@endsection
