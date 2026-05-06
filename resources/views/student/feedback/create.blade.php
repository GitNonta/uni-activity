{{-- หน้าฟอร์มประเมินกิจกรรม --}}
@extends('layouts.app')
@section('title', 'ประเมินกิจกรรม')

@section('content')
<div class="container" style="max-width:700px;margin:0 auto;padding:1.5rem;">
    <div class="card">
        <div class="card-body" style="padding:1.5rem;">
            <div style="text-align:center;margin-bottom:1.5rem;">
                <h1 style="font-size:1.5rem;font-weight:700;color:#1e293b;margin-bottom:.5rem;">ประเมินกิจกรรม</h1>
                <p style="color:#64748b;font-size:.9rem;">{{ $activity->title }}</p>
                <p style="color:#94a3b8;font-size:.8rem;">{{ $activity->category->name ?? '-' }} | {{ $activity->activity_date->format('d/m/Y') }}</p>
            </div>

            <form method="POST" action="{{ route('feedback.store', $activity->id) }}">
                @csrf

                {{-- คะแนนรวม --}}
                <div style="margin-bottom:1.5rem;">
                    <label class="form-label" style="font-weight:600;margin-bottom:.5rem;">คะแนนโดยรวม <span style="color:#dc2626;">*</span></label>
                    <div style="display:flex;gap:.5rem;justify-content:center;margin-top:.75rem;">
                        @for($i = 1; $i <= 5; $i++)
                        <label style="cursor:pointer;">
                            <input type="radio" name="rating" value="{{ $i }}" required style="display:none;" class="rating-input">
                            <span class="star-icon" data-value="{{ $i }}" style="font-size:2.5rem;color:#cbd5e1;transition:color .2s;">
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 40px; height: 40px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </span>
                        </label>
                        @endfor
                    </div>
                    @error('rating')
                        <p style="color:#dc2626;font-size:.8rem;margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- คะแนนแยกตามหัวข้อ --}}
                <div style="background:#f8fafc;padding:1rem;border-radius:8px;margin-bottom:1.5rem;">
                    <p style="font-weight:600;margin-bottom:1rem;font-size:.9rem;">ประเมินรายละเอียด (ไม่บังคับ)</p>
                    
                    <div style="margin-bottom:.75rem;">
                        <label class="form-label" style="font-size:.85rem;">เนื้อหากิจกรรม</label>
                        <div style="display:flex;gap:.3rem;">
                            @for($i = 1; $i <= 5; $i++)
                            <label style="cursor:pointer;">
                                <input type="radio" name="rating_content" value="{{ $i }}" style="display:none;" class="rating-input-detail" data-group="content">
                                <span class="star-detail" data-group="content" data-value="{{ $i }}" style="font-size:1.5rem;color:#cbd5e1;transition:color .2s;">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </span>
                            </label>
                            @endfor
                        </div>
                    </div>

                    <div style="margin-bottom:.75rem;">
                        <label class="form-label" style="font-size:.85rem;">วิทยากร/ผู้ดำเนินการ</label>
                        <div style="display:flex;gap:.3rem;">
                            @for($i = 1; $i <= 5; $i++)
                            <label style="cursor:pointer;">
                                <input type="radio" name="rating_speaker" value="{{ $i }}" style="display:none;" class="rating-input-detail" data-group="speaker">
                                <span class="star-detail" data-group="speaker" data-value="{{ $i }}" style="font-size:1.5rem;color:#cbd5e1;transition:color .2s;">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </span>
                            </label>
                            @endfor
                        </div>
                    </div>

                    <div style="margin-bottom:.75rem;">
                        <label class="form-label" style="font-size:.85rem;">สถานที่/สิ่งอำนวยความสะดวก</label>
                        <div style="display:flex;gap:.3rem;">
                            @for($i = 1; $i <= 5; $i++)
                            <label style="cursor:pointer;">
                                <input type="radio" name="rating_location" value="{{ $i }}" style="display:none;" class="rating-input-detail" data-group="location">
                                <span class="star-detail" data-group="location" data-value="{{ $i }}" style="font-size:1.5rem;color:#cbd5e1;transition:color .2s;">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </span>
                            </label>
                            @endfor
                        </div>
                    </div>

                    <div style="margin-bottom:0;">
                        <label class="form-label" style="font-size:.85rem;">การจัดการ/ประสานงาน</label>
                        <div style="display:flex;gap:.3rem;">
                            @for($i = 1; $i <= 5; $i++)
                            <label style="cursor:pointer;">
                                <input type="radio" name="rating_organization" value="{{ $i }}" style="display:none;" class="rating-input-detail" data-group="organization">
                                <span class="star-detail" data-group="organization" data-value="{{ $i }}" style="font-size:1.5rem;color:#cbd5e1;transition:color .2s;">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </span>
                            </label>
                            @endfor
                        </div>
                    </div>
                </div>

                {{-- ความคิดเห็นเพิ่มเติม --}}
                <div style="margin-bottom:1.5rem;">
                    <label for="comment" class="form-label" style="font-weight:600;">ความคิดเห็นเพิ่มเติม</label>
                    <textarea name="comment" id="comment" rows="4" class="form-control" 
                        placeholder="แบ่งปันความคิดเห็น ข้อเสนอแนะ หรือสิ่งที่ต้องการให้ปรับปรุง..." 
                        style="resize:vertical;">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p style="color:#dc2626;font-size:.8rem;margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ไม่ระบุตัวตน --}}
                <div style="margin-bottom:1.5rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="checkbox" name="is_anonymous" value="1" {{ old('is_anonymous') ? 'checked' : '' }}>
                        <span style="font-size:.9rem;color:#64748b;">ประเมินแบบไม่ระบุตัวตน</span>
                    </label>
                </div>

                {{-- ปุ่ม --}}
                <div style="display:flex;gap:.75rem;justify-content:center;">
                    <a href="{{ route('activities.index') }}" class="btn btn-outline" style="padding:8px 24px;">ยกเลิก</a>
                    <button type="submit" class="btn btn-primary" style="padding:8px 32px;">ส่งการประเมิน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ระบบให้คะแนนดาว - คะแนนรวม
document.querySelectorAll('.star-icon').forEach(star => {
    star.addEventListener('click', function() {
        const value = parseInt(this.dataset.value);
        const input = this.previousElementSibling;
        input.checked = true;
        
        // อัปเดตสีดาว
        document.querySelectorAll('.star-icon').forEach((s, idx) => {
            s.style.color = (idx < value) ? '#fbbf24' : '#cbd5e1';
        });
    });
    
    star.addEventListener('mouseenter', function() {
        const value = parseInt(this.dataset.value);
        document.querySelectorAll('.star-icon').forEach((s, idx) => {
            s.style.color = (idx < value) ? '#fbbf24' : '#cbd5e1';
        });
    });
});

document.querySelector('form').addEventListener('mouseleave', function() {
    const checked = document.querySelector('input[name="rating"]:checked');
    if (checked) {
        const value = parseInt(checked.value);
        document.querySelectorAll('.star-icon').forEach((s, idx) => {
            s.style.color = (idx < value) ? '#fbbf24' : '#cbd5e1';
        });
    } else {
        document.querySelectorAll('.star-icon').forEach(s => s.style.color = '#cbd5e1');
    }
});

// ระบบให้คะแนนดาว - คะแนนรายละเอียด
document.querySelectorAll('.star-detail').forEach(star => {
    star.addEventListener('click', function() {
        const group = this.dataset.group;
        const value = parseInt(this.dataset.value);
        const input = this.previousElementSibling;
        input.checked = true;
        
        // อัปเดตสีดาวในกลุ่มเดียวกัน
        document.querySelectorAll(`.star-detail[data-group="${group}"]`).forEach((s, idx) => {
            s.style.color = (idx < value) ? '#fbbf24' : '#cbd5e1';
        });
    });
    
    star.addEventListener('mouseenter', function() {
        const group = this.dataset.group;
        const value = parseInt(this.dataset.value);
        document.querySelectorAll(`.star-detail[data-group="${group}"]`).forEach((s, idx) => {
            s.style.color = (idx < value) ? '#fbbf24' : '#cbd5e1';
        });
    });
});

// รีเซ็ตสีเมื่อเมาส์ออกจากกลุ่ม
document.querySelectorAll('.star-detail').forEach(star => {
    star.parentElement.parentElement.addEventListener('mouseleave', function() {
        const group = star.dataset.group;
        const checked = document.querySelector(`input[name="rating_${group}"]:checked`);
        if (checked) {
            const value = parseInt(checked.value);
            document.querySelectorAll(`.star-detail[data-group="${group}"]`).forEach((s, idx) => {
                s.style.color = (idx < value) ? '#fbbf24' : '#cbd5e1';
            });
        } else {
            document.querySelectorAll(`.star-detail[data-group="${group}"]`).forEach(s => s.style.color = '#cbd5e1');
        }
    });
});
</script>
@endsection
