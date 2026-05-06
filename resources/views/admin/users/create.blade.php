{{-- หน้าสร้างผู้ใช้ใหม่ (Admin): นักศึกษา หรือ เจ้าหน้าที่ --}}
@extends('layouts.admin')
@section('title', $type === 'staff' ? 'สร้างเจ้าหน้าที่' : 'สร้างนักศึกษา')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.4rem;">{{ $type === 'staff' ? 'สร้างเจ้าหน้าที่ (ผู้สร้างกิจกรรม)' : 'สร้างนักศึกษา' }}</h1>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm">← กลับ</a>
</div>

<div class="card">
    <div class="card-body" style="padding:1.25rem;">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <input type="hidden" name="role" value="{{ $type }}">

            {{-- ชื่อ-สกุล --}}
            <div class="mb-3">
                <label class="form-label">ชื่อ-สกุล <span style="color:#dc2626;">*</span></label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" class="form-control" required>
                @error('full_name') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
            </div>

            {{-- อีเมล --}}
            <div class="mb-3" id="email-field-container">
                <label class="form-label">อีเมล <span style="color:#dc2626;">*</span></label>
                @if($type === 'student')
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="จะถูกสร้างอัตโนมัติจากรหัสนักศึกษา" readonly style="background:#f8fafc;color:#64748b;">
                    <div class="text-xs text-muted mt-1">อีเมลจะถูกตั้งค่าเป็น: s[รหัสนักศึกษา]@pkru.ac.th โดยอัตโนมัติ</div>
                @else
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                @endif
                @error('email') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
            </div>

            @if($type === 'student')
                {{-- รหัสนักศึกษา --}}
                <div class="mb-3">
                    <label class="form-label">รหัสนักศึกษา <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="student_id" value="{{ old('student_id') }}" class="form-control" required>
                    @error('student_id') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    {{-- คณะ --}}
                    <div class="mb-3">
                        <label class="form-label">คณะ</label>
                        <select id="faculty" name="faculty" class="form-control" onchange="updateDepartments()">
                            <option value="">เลือกคณะ</option>
                            @foreach(config('faculties') as $faculty => $deps)
                                <option value="{{ $faculty }}" {{ old('faculty') == $faculty ? 'selected' : '' }}>{{ $faculty }}</option>
                            @endforeach
                        </select>
                        @error('faculty') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                    </div>

                    {{-- สาขา --}}
                    <div class="mb-3">
                        <label class="form-label">สาขาวิชา</label>
                        <select id="department" name="department" class="form-control" disabled>
                            <option value="">เลือกสาขาวิชา</option>
                        </select>
                        @error('department') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    {{-- ชั้นปี --}}
                    <div class="mb-3">
                        <label class="form-label">ชั้นปี</label>
                        <select name="year" class="form-control">
                            <option value="">ไม่ระบุ</option>
                            @for($i = 1; $i <= 6; $i++)
                                <option value="{{ $i }}" {{ old('year') == $i ? 'selected' : '' }}>ปี {{ $i }}</option>
                            @endfor
                        </select>
                        @error('year') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                    </div>

                    {{-- ภาคเรียน --}}
                    <div class="mb-3">
                        <label class="form-label">ภาคเรียน</label>
                        <select name="program" class="form-control">
                            <option value="">เลือกภาคเรียน</option>
                            <option value="ปกติ" {{ old('program') == 'ปกติ' ? 'selected' : '' }}>ปกติ (จันทร์-ศุกร์)</option>
                            <option value="กศ.บป." {{ old('program') == 'กศ.บป.' ? 'selected' : '' }}>กศ.บป. (เสาร์-อาทิตย์)</option>
                        </select>
                        @error('program') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- รหัสผ่าน (ถ้าไม่กรอกจะใช้รหัสนักศึกษาเป็นรหัสผ่าน) --}}
                <div class="mb-3">
                    <label class="form-label">รหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" placeholder="ถ้าไม่กรอกจะใช้รหัสนักศึกษาเป็นรหัสผ่าน">
                    @error('password') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                    <div class="text-xs text-muted" style="margin-top:.25rem;">หากไม่กรอก ระบบจะใช้รหัสนักศึกษาเป็นรหัสผ่านเริ่มต้น</div>
                </div>
            @else
                {{-- รหัสผ่าน สำหรับเจ้าหน้าที่ --}}
                <div class="mb-3">
                    <label class="form-label">รหัสผ่าน <span style="color:#dc2626;">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                    @error('password') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">ยืนยันรหัสผ่าน <span style="color:#dc2626;">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            @endif

            {{-- สถานะ --}}
            <div class="mb-3">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} style="width:18px;height:18px;">
                    <span class="text-sm">เปิดใช้งานบัญชี</span>
                </label>
            </div>

            <hr style="margin:1rem 0;border-color:#e2e8f0;">

            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">ยกเลิก</a>
                <button type="submit" class="btn btn-primary">
                    {{ $type === 'staff' ? 'สร้างเจ้าหน้าที่' : 'สร้างนักศึกษา' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const facultyData = @json(config('faculties'));
    const facultySelect = document.getElementById('faculty');
    const departmentSelect = document.getElementById('department');
    const oldDepartment = "{{ old('department') }}";

    function updateDepartments() {
        if (!facultySelect || !departmentSelect) return;
        
        const selectedFaculty = facultySelect.value;
        departmentSelect.innerHTML = '<option value="">เลือกสาขาวิชา</option>';
        
        if (selectedFaculty && facultyData[selectedFaculty]) {
            departmentSelect.disabled = false;
            facultyData[selectedFaculty].forEach(dep => {
                const option = document.createElement('option');
                option.value = dep;
                option.textContent = dep;
                if (dep === oldDepartment) {
                    option.selected = true;
                }
                departmentSelect.appendChild(option);
            });
        } else {
            departmentSelect.disabled = true;
        }
    }

    if (facultySelect && facultySelect.value) {
        updateDepartments();
    }
</script>
@endsection
