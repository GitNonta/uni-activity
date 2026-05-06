@extends('layouts.app')
@section('title', 'สมัครสมาชิก')

@section('content')
<div class="container-sm" style="padding-top:2rem;">
    <div class="text-center mb-4">
        <h1 class="font-bold" style="font-size:1.5rem;">สมัครสมาชิก</h1>
        <p class="text-muted text-sm mt-1">กรอกข้อมูลเพื่อสร้างบัญชี</p>
    </div>

    {{-- ฟอร์มกรอกข้อมูลนักศึกษา --}}
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('register') }}">
                @csrf
                <div class="form-group">
                    <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                    <input id="student_id" type="text" name="student_id" value="{{ old('student_id') }}"
                        class="form-control" placeholder="6XXXXXXXXX" required>
                    @error('student_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="full_name" class="form-label">ชื่อ-นามสกุล</label>
                    <input id="full_name" type="text" name="full_name" value="{{ old('full_name') }}"
                        class="form-control" required>
                    @error('full_name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="faculty" class="form-label">คณะ</label>
                        <select id="faculty" name="faculty" class="form-control" required onchange="updateDepartments()">
                            <option value="">เลือกคณะ</option>
                            @foreach(config('faculties') as $faculty => $deps)
                                <option value="{{ $faculty }}" {{ old('faculty') == $faculty ? 'selected' : '' }}>{{ $faculty }}</option>
                            @endforeach
                        </select>
                        @error('faculty')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label for="department" class="form-label">สาขาวิชา</label>
                        <select id="department" name="department" class="form-control" required disabled>
                            <option value="">เลือกสาขาวิชา</option>
                        </select>
                        @error('department')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="year" class="form-label">ชั้นปี</label>
                        <select id="year" name="year" class="form-control" required>
                            <option value="">เลือกชั้นปี</option>
                            @for($i = 1; $i <= 6; $i++)
                                <option value="{{ $i }}" {{ old('year') == $i ? 'selected' : '' }}>ปี {{ $i }}</option>
                            @endfor
                        </select>
                        @error('year')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label for="program" class="form-label">ภาคเรียน</label>
                        <select id="program" name="program" class="form-control" required>
                            <option value="">เลือกภาคเรียน</option>
                            <option value="ปกติ" {{ old('program') == 'ปกติ' ? 'selected' : '' }}>ปกติ (จันทร์-ศุกร์)</option>
                            <option value="กศ.บป." {{ old('program') == 'กศ.บป.' ? 'selected' : '' }}>กศ.บป. (เสาร์-อาทิตย์)</option>
                        </select>
                        @error('program')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">สมัครสมาชิก</button>
            </form>
        </div>
    </div>

    <p class="text-center text-sm text-muted mt-4">
        มีบัญชีอยู่แล้ว? <a href="{{ route('login') }}" class="font-semi">เข้าสู่ระบบ</a>
    </p>
</div>
@endsection

@section('scripts')
<script>
    const facultyData = @json(config('faculties'));
    const facultySelect = document.getElementById('faculty');
    const departmentSelect = document.getElementById('department');
    const oldDepartment = "{{ old('department') }}";

    function updateDepartments() {
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

    // Trigger on load for validation errors retaining old value
    if (facultySelect.value) {
        updateDepartments();
    }
</script>
@endsection
