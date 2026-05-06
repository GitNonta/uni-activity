{{-- หน้าแก้ไขผู้ใช้ (Admin): นักศึกษา หรือ เจ้าหน้าที่ --}}
@extends('layouts.admin')
@section('title', 'แก้ไขผู้ใช้: ' . $user->full_name)

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.4rem;">
        แก้ไข{{ $user->role === 'staff' ? 'เจ้าหน้าที่' : 'นักศึกษา' }}: {{ $user->full_name }}
    </h1>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm">← กลับ</a>
</div>

<div class="card">
    <div class="card-body" style="padding:1.25rem;">
        <form method="POST" action="{{ route('admin.users.update', $user->id) }}">
            @csrf
            @method('PATCH')

            {{-- บทบาท (แสดงอย่างเดียว) --}}
            <div class="mb-3">
                <label class="form-label">บทบาท</label>
                <div>
                    @if($user->role === 'staff')
                        <span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:.85rem;background:#ede9fe;color:#7c3aed;">เจ้าหน้าที่</span>
                    @else
                        <span style="display:inline-block;padding:4px 12px;border-radius:12px;font-size:.85rem;background:#dbeafe;color:#1e40af;">นักศึกษา</span>
                    @endif
                </div>
            </div>

            {{-- ชื่อ-สกุล --}}
            <div class="mb-3">
                <label class="form-label">ชื่อ-สกุล <span style="color:#dc2626;">*</span></label>
                <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" class="form-control" required>
                @error('full_name') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
            </div>

            {{-- อีเมล --}}
            <div class="mb-3">
                <label class="form-label">อีเมล <span style="color:#dc2626;">*</span></label>
                @if($user->role === 'student')
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" readonly style="background:#f8fafc;color:#64748b;">
                    <div class="text-xs text-muted mt-1">อีเมลนักศึกษาถูกตั้งค่าอัตโนมัติตามรหัสนักศึกษา</div>
                @else
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                @endif
                @error('email') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
            </div>

            @if($user->role === 'student')
                {{-- รหัสนักศึกษา --}}
                <div class="mb-3">
                    <label class="form-label">รหัสนักศึกษา <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="student_id" value="{{ old('student_id', $user->student_id) }}" class="form-control" required>
                    @error('student_id') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    {{-- คณะ --}}
                    <div class="mb-3">
                        <label class="form-label">คณะ</label>
                        <select id="faculty" name="faculty" class="form-control" onchange="updateDepartments()">
                            <option value="">เลือกคณะ</option>
                            @foreach(config('faculties') as $faculty => $deps)
                                <option value="{{ $faculty }}" label="{{ $faculty }}" {{ old('faculty', $user->faculty) == $faculty ? 'selected' : '' }}>{{ $faculty }}</option>
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

                {{-- ชั้นปี และ ภาคเรียน --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="mb-3">
                        <label class="form-label">ชั้นปี</label>
                        <select name="year" class="form-control">
                            <option value="">ไม่ระบุ</option>
                            @for($i = 1; $i <= 6; $i++)
                                <option value="{{ $i }}" {{ old('year', $user->year) == $i ? 'selected' : '' }}>ปี {{ $i }}</option>
                            @endfor
                        </select>
                        @error('year') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ภาคเรียน</label>
                        <select name="program" class="form-control">
                            <option value="">เลือกภาคเรียน</option>
                            <option value="ปกติ" {{ old('program', $user->program) == 'ปกติ' ? 'selected' : '' }}>ปกติ (จันทร์-ศุกร์)</option>
                            <option value="กศ.บป." {{ old('program', $user->program) == 'กศ.บป.' ? 'selected' : '' }}>กศ.บป. (เสาร์-อาทิตย์)</option>
                        </select>
                        @error('program') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                    </div>
                </div>
            @endif

            {{-- รหัสผ่าน (เปลี่ยนรหัสผ่าน - ไม่บังคับ) --}}
            <div class="mb-3">
                <label class="form-label">รหัสผ่านใหม่</label>
                <input type="password" name="password" class="form-control" placeholder="เว้นว่างถ้าไม่ต้องการเปลี่ยน">
                @error('password') <div class="text-xs" style="color:#dc2626;margin-top:.25rem;">{{ $message }}</div> @enderror
                <div class="text-xs text-muted" style="margin-top:.25rem;">เว้นว่างถ้าไม่ต้องการเปลี่ยนรหัสผ่าน</div>
            </div>

            {{-- สถานะ --}}
            <div class="mb-3">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} style="width:18px;height:18px;">
                    <span class="text-sm">เปิดใช้งานบัญชี</span>
                </label>
            </div>

            <hr style="margin:1rem 0;border-color:#e2e8f0;">

            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">ยกเลิก</a>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

{{-- ข้อมูลเพิ่มเติม --}}
<div class="card mt-4">
    <div class="card-body" style="padding:1rem;">
        <h3 class="font-semi text-sm mb-2">ข้อมูลเพิ่มเติม</h3>
        <div class="text-xs text-muted">
            <p>สร้างเมื่อ: {{ $user->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
            <p>แก้ไขล่าสุด: {{ $user->updated_at?->format('d/m/Y H:i') ?? '-' }}</p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const facultyData = @json(config('faculties'));
    const facultySelect = document.getElementById('faculty');
    const departmentSelect = document.getElementById('department');
    const oldDepartment = "{{ old('department', $user->department) }}";

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
