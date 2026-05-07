{{-- หน้า Walk-in Check-in: กรอกรหัสนักศึกษาเพื่อบันทึกการเข้าร่วมกิจกรรม + รายชื่อเรียลไทม์ --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in เช็คอิน — {{ $activity->title }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        /* Mobile-first responsive design */
        body { 
            background: #f1f5f9; 
            margin: 0; 
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        .walkin-container { 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 0.75rem; /* Reduced padding for mobile */
        }
        .walkin-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            border-radius: 16px;
            padding: 1.25rem; /* Slightly reduced */
            text-align: center;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }
        .walkin-header h1 { 
            font-size: 1.2rem; /* Slightly larger for mobile */
            font-weight: 700; 
            margin: 0 0 .25rem; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .walkin-header .activity-name { 
            font-size: 1.1rem; /* Slightly smaller for mobile */
            font-weight: 700; 
            margin: .5rem 0; 
            line-height: 1.3;
        }
        .walkin-header .meta { 
            font-size: .8rem; /* Smaller for mobile */
            opacity: .85; 
            line-height: 1.4;
        }
        .walkin-form-card {
            background: #fff;
            border-radius: 12px;
            padding: 1rem; /* Reduced for mobile */
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            margin-bottom: 1rem;
        }
        .walkin-form-card label { 
            font-weight: 600; 
            font-size: .95rem; /* Slightly larger */
            display: block; 
            margin-bottom: .75rem; /* More space */
            color: #374151;
        }
        .walkin-input-row { 
            display: flex; 
            gap: 0.75rem; /* More gap for mobile */
            flex-direction: column; /* Stack vertically on mobile */
        }
        .walkin-input-row input {
            flex: 1;
            padding: 1rem; /* Larger touch target */
            border: 2px solid #e2e8f0;
            border-radius: 12px; /* More rounded */
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: .5px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            text-align: center; /* Centered for mobile */
            background: #f8fafc;
        }
        .walkin-input-row input:focus { 
            border-color: #4f46e5; 
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            background: #fff;
        }
        .walkin-input-row button {
            padding: 1rem 1.5rem; /* Larger touch target */
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem; /* Slightly larger */
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
            min-height: 48px; /* Minimum touch target */
        }
        .walkin-input-row button:hover { 
            background: #4338ca; 
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }
        .walkin-input-row button:active {
            transform: translateY(0);
        }

        /* Attendees section */
        .attendees-section { 
            margin-top: 1rem; 
        }
        .attendees-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .attendees-header h2 { 
            font-size: 1.05rem; 
            font-weight: 700; 
            margin: 0; 
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .attendees-count {
            background: #4f46e5;
            color: #fff;
            padding: 4px 14px; /* Larger padding */
            border-radius: 20px;
            font-size: .85rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }
        .attendee-list { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        .attendee-item {
            background: #fff;
            border-radius: 12px; /* More rounded */
            padding: 1rem; /* More padding */
            margin-bottom: 0.75rem; /* More space */
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeInUp .3s ease;
            transition: transform .2s, box-shadow .2s;
        }
        .attendee-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,.12);
        }
        .attendee-item .info { 
            flex: 1; 
            min-width: 0; /* Prevent overflow */
        }
        .attendee-item .name { 
            font-weight: 600; 
            font-size: .95rem; /* Larger for mobile */
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .attendee-item .sid { 
            color: #64748b; 
            font-size: .85rem; /* Larger */
            margin-bottom: 0.25rem;
        }
        .attendee-item .faculty { 
            color: #94a3b8; 
            font-size: .8rem; 
            display: block;
            margin-top: 0.25rem;
        }
        .attendee-item .time {
            text-align: right;
            font-size: .8rem;
            color: #64748b;
            white-space: nowrap;
            flex-shrink: 0;
            margin-left: 0.75rem;
        }
        .attendee-item .order {
            width: 32px; /* Larger */
            height: 32px;
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem; /* Larger */
            font-weight: 700;
            color: #4f46e5;
            margin-right: 1rem; /* More space */
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .empty-attendees {
            text-align: center;
            padding: 3rem 1rem; /* More padding */
            color: #94a3b8;
            font-size: .95rem;
        }
        .live-dot {
            display: inline-block;
            width: 10px; /* Larger */
            height: 10px;
            background: #22c55e;
            border-radius: 50%;
            margin-right: 6px;
            animation: pulse 1.5s infinite;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .6; transform: scale(1.1); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Alert styles */
        .walkin-alert {
            padding: 1rem 1.25rem; /* Larger padding */
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: .95rem;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
            border-left: 4px solid;
        }
        .walkin-alert-success { 
            background: #dcfce7; 
            color: #166534; 
            border-left-color: #22c55e;
        }
        .walkin-alert-error { 
            background: #fee2e2; 
            color: #991b1b; 
            border-left-color: #ef4444;
        }

        /* Mobile optimizations */
        @media (max-width: 640px) {
            .walkin-container { padding: 0.5rem; }
            .walkin-header { padding: 1rem; }
            .walkin-header h1 { font-size: 1.1rem; }
            .walkin-header .activity-name { font-size: 1rem; }
            .walkin-header .meta { font-size: .75rem; }
            .walkin-form-card { padding: 0.875rem; }
            .walkin-input-row { gap: 0.625rem; }
            .attendee-item { 
                padding: 0.875rem; 
                margin-bottom: 0.625rem;
            }
            .attendee-item .name { font-size: .9rem; }
            .attendee-item .order { 
                width: 28px; 
                height: 28px;
                margin-right: 0.75rem;
            }
        }

        /* Tablet optimizations */
        @media (min-width: 641px) {
            .walkin-input-row {
                flex-direction: row; /* Horizontal on larger screens */
            }
            .walkin-input-row input {
                text-align: left; /* Left align on larger screens */
            }
        }

        /* Large screen optimizations */
        @media (min-width: 1024px) {
            .walkin-container { padding: 1.5rem; }
            .walkin-header { padding: 2rem; }
            .walkin-header h1 { font-size: 1.3rem; }
            .walkin-header .activity-name { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
<div class="walkin-container">
    {{-- Header --}}
    <div class="walkin-header">
        <h1>
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Walk-in เช็คอิน
        </h1>
        <div class="activity-name">{{ $activity->title }}</div>
        <div class="meta">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-right: 4px; vertical-align: middle;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            {{ $activity->activity_date->format('d/m/Y') }}
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-left: 8px; margin-right: 4px; vertical-align: middle;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{ $activity->location ?? '-' }}
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-left: 8px; margin-right: 4px; vertical-align: middle;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $activity->activity_hours }} ชม.
        </div>
        <a href="{{ route('activities.show', $activity->id) }}" 
           style="display:inline-flex;align-items:center;gap:0.5rem;margin-top:.75rem;padding:.5rem 1rem;background:rgba(255,255,255,.2);color:#fff;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:500;transition:all .2s;"
           onmouseover="this.style.background='rgba(255,255,255,.3);this.style.transform='translateY(-1px)'"
           onmouseout="this.style.background='rgba(255,255,255,.2);this.style.transform='translateY(0)'">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            ดูรายละเอียดกิจกรรม
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="walkin-alert walkin-alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="walkin-alert walkin-alert-error">{{ session('error') }}</div>
    @endif

    {{-- Form กรอกรหัสนักศึกษา --}}
    <div class="walkin-form-card">
        <label for="student_id">กรอกรหัสนักศึกษา</label>
        <form method="POST" action="{{ route('checkin.walkin.store', $token) }}" id="walkinForm">
            @csrf
            <div class="walkin-input-row">
                <input type="text" name="student_id" id="student_id"
                       placeholder="เช่น 65012345"
                       value="{{ old('student_id') }}"
                       autocomplete="off"
                       autofocus
                       inputmode="numeric">
                <button type="submit">เช็คอิน</button>
            </div>
        </form>
    </div>

    {{-- รายชื่อผู้เข้าร่วมแบบเรียลไทม์ (แสดงเฉพาะ Staff/Admin) --}}
    @if(auth()->check() && (auth()->user()->isStaff() || auth()->user()->isAdmin()))
    <div class="attendees-section">
        <div class="attendees-header">
            <h2><span class="live-dot"></span> ผู้เข้าร่วมกิจกรรม</h2>
            <span class="attendees-count" id="attendeeCount">{{ $attendances->count() }} คน</span>
        </div>
        <ul class="attendee-list" id="attendeeList">
            @forelse($attendances as $i => $att)
                <li class="attendee-item">
                    <span class="order">{{ $i + 1 }}</span>
                    <div class="info">
                        <div class="name">{{ $att->user->full_name }}</div>
                        <div class="sid">{{ $att->user->student_id }} &middot; <span class="faculty">{{ $att->user->faculty ?? '-' }}</span></div>
                    </div>
                    <div class="time">{{ $att->checked_in_at?->format('H:i:s') ?? $att->created_at->format('H:i:s') }}</div>
                </li>
            @empty
                <li class="empty-attendees" id="emptyMsg">ยังไม่มีผู้เข้าร่วม</li>
            @endforelse
        </ul>
    </div>
    @endif
</div>

{{-- Feedback Popup --}}
@if(session('checked_in_student'))
<div id="feedbackModal" style="display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:2000;justify-content:center;align-items:center;animation:fadeIn 0.3s ease;">
    <div style="background:white;padding:0;border-radius:16px;max-width:480px;width:95%;max-height:90vh;overflow-y:auto;animation:slideUp 0.3s ease;">
        <!-- Header -->
        <div style="background:linear-gradient(135deg, #4f46e5, #7c3aed);color:white;padding:1.5rem;border-radius:16px 16px 0 0;text-align:center;">
            <div style="width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:30px;height:30px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 style="margin:0;font-size:1.3rem;font-weight:700;">เช็คอินสำเร็จ!</h3>
            <p style="margin:0.5rem 0 0;font-size:0.9rem;opacity:0.9;">
                {{ session('checked_in_student')['name'] }} ({{ session('checked_in_student')['student_id'] }})
            </p>
        </div>

        <!-- Content -->
        <div style="padding:2rem;">
            <h4 style="margin:0 0 1rem;font-size:1.1rem;color:#1f2937;text-align:center;">
                ช่วยประเมินกิจกรรมหน่อยนะครับ/คะ
            </h4>
            <p style="margin:0 0 1.5rem;color:#6b7280;text-align:center;font-size:0.9rem;line-height:1.4;">
                ความคิดเห็นของคุณจะช่วยให้เราพัฒนากิจกรรมในครั้งต่อไป
            </p>

            <!-- Rating Stars -->
            <div style="text-align:center;margin-bottom:1.5rem;">
                <label style="display:block;margin-bottom:0.75rem;font-weight:600;color:#374151;">คะแนนความพึงพอใจ</label>
                <div id="starRating" style="display:flex;justify-content:center;gap:0.5rem;font-size:2rem;">
                    @for($i = 1; $i <= 5; $i++)
                        <span class="star" data-rating="{{ $i }}" style="cursor:pointer;color:#d1d5db;transition:all 0.2s;">
                            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:32px;height:32px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </span>
                    @endfor
                </div>
                <p id="ratingText" style="margin-top:0.5rem;color:#6b7280;font-size:0.85rem;">กรุณาเลือกคะแนน</p>
            </div>

            <!-- Comment -->
            <div style="margin-bottom:1.5rem;">
                <label for="feedbackComment" style="display:block;margin-bottom:0.5rem;font-weight:600;color:#374151;">ความคิดเห็นเพิ่มเติม (ไม่บังคับ)</label>
                <textarea id="feedbackComment" rows="3" placeholder="บอกเราว่าคุณคิดอย่างไรกับกิจกรรมนี้..."
                    style="width:100%;padding:0.75rem;border:2px solid #e5e7eb;border-radius:8px;font-size:0.9rem;font-family:inherit;resize:vertical;outline:none;transition:border-color 0.2s;"
                    onfocus="this.style.borderColor='#4f46e5'"
                    onblur="this.style.borderColor='#e5e7eb'"></textarea>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex;gap:0.75rem;">
                <button onclick="closeFeedbackModal()" style="flex:1;padding:0.75rem 1rem;border:2px solid #e5e7eb;background:white;color:#6b7280;border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.2s;">
                    ข้าม
                </button>
                <button onclick="submitFeedback()" style="flex:1;padding:0.75rem 1rem;background:#4f46e5;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.2s;">
                    ส่งประเมิน
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for feedback submission -->
<form id="feedbackForm" method="POST" action="{{ route('feedback.store', session('checked_in_student')['activity_id']) }}" style="display:none;">
    @csrf
    <input type="hidden" name="rating" id="hiddenRating" value="0">
    <input type="hidden" name="comment" id="hiddenComment" value="">
    <input type="hidden" name="is_anonymous" value="1">
</form>

<script>
// Star rating interaction
let selectedRating = 0;
const stars = document.querySelectorAll('.star');
const ratingText = document.getElementById('ratingText');
const ratingTexts = ['', 'ไม่พอใจมาก', 'ไม่พอใจ', 'ปานกลาง', 'พอใจ', 'พอใจมาก'];

stars.forEach((star, index) => {
    star.addEventListener('click', () => {
        selectedRating = index + 1;
        updateStars();
    });
    
    star.addEventListener('mouseenter', () => {
        const hoverRating = index + 1;
        stars.forEach((s, i) => {
            s.style.color = i < hoverRating ? '#fbbf24' : '#d1d5db';
        });
        ratingText.textContent = ratingTexts[hoverRating];
    });
});

document.getElementById('starRating').addEventListener('mouseleave', updateStars);

function updateStars() {
    stars.forEach((star, index) => {
        star.style.color = index < selectedRating ? '#fbbf24' : '#d1d5db';
    });
    ratingText.textContent = selectedRating > 0 ? ratingTexts[selectedRating] : 'กรุณาเลือกคะแนน';
}

function closeFeedbackModal() {
    const modal = document.getElementById('feedbackModal');
    modal.style.animation = 'fadeOut 0.3s ease';
    setTimeout(() => modal.remove(), 300);
}

function submitFeedback() {
    if (selectedRating === 0) {
        ratingText.textContent = 'กรุณาเลือกคะแนนก่อนส่ง';
        ratingText.style.color = '#ef4444';
        setTimeout(() => {
            ratingText.style.color = '#6b7280';
            ratingText.textContent = ratingTexts[selectedRating] || 'กรุณาเลือกคะแนน';
        }, 2000);
        return;
    }
    
    document.getElementById('hiddenRating').value = selectedRating;
    document.getElementById('hiddenComment').value = document.getElementById('feedbackComment').value;
    document.getElementById('feedbackForm').submit();
}

// Auto-close after 10 seconds if no interaction
let autoCloseTimer = setTimeout(() => {
    closeFeedbackModal();
}, 10000);

// Reset timer on any interaction
['click', 'touch', 'keypress'].forEach(event => {
    document.getElementById('feedbackModal').addEventListener(event, () => {
        clearTimeout(autoCloseTimer);
        autoCloseTimer = setTimeout(() => closeFeedbackModal(), 10000);
    });
});
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

@keyframes slideUp {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.star:hover {
    transform: scale(1.1);
}

.star:active {
    transform: scale(0.95);
}
</style>
@endif

<script>
@if(auth()->check() && (auth()->user()->isStaff() || auth()->user()->isAdmin()))
// Auto-refresh รายชื่อทุก 5 วินาที สำหรับ Admin/Staff
var refreshUrl = "{{ route('checkin.walkin.attendees', $token) }}";
var refreshInterval = 5000;

function refreshAttendees() {
    fetch(refreshUrl)
        .then(function(res) { 
            if(res.status === 403) throw new Error('Unauthorized');
            return res.json(); 
        })
        .then(function(data) {
            var countEl = document.getElementById('attendeeCount');
            if (countEl) countEl.textContent = data.count + ' คน';

            var list = document.getElementById('attendeeList');
            if(!list) return;
            
            if (data.attendances.length === 0) {
                list.innerHTML = '<li class="empty-attendees" id="emptyMsg">ยังไม่มีผู้เข้าร่วม</li>';
                return;
            }

            var html = '';
            data.attendances.forEach(function(att, i) {
                var timeOnly = att.checked_in_at.split(' ')[1] || att.checked_in_at;
                var isWalkIn = att.method === 'walk_in';
                html += '<li class="attendee-item">'
                    + '<span class="order">' + (i + 1) + '</span>'
                    + '<div class="info">'
                    + '<div class="name">' + escapeHtml(att.full_name) + (isWalkIn ? ' <span style="background:#f59e0b;color:white;padding:2px 6px;border-radius:4px;font-size:0.7rem;font-weight:600;margin-left:0.5rem;">Walk-in</span>' : '') + '</div>'
                    + '<div class="sid">' + escapeHtml(att.student_id) + ' &middot; <span class="faculty">' + escapeHtml(att.faculty) + '</span></div>'
                    + '</div>'
                    + '<div class="time">' + escapeHtml(timeOnly) + '</div>'
                    + '</li>';
            });
            list.innerHTML = html;
        })
        .catch(function() { /* silent fail */ });
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

setInterval(refreshAttendees, refreshInterval);
@endif

// หลังส่งฟอร์มสำเร็จ ให้เคลียร์ช่องกรอก และ focus กลับ
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('student_id');
    @if(session('success'))
        input.value = '';
        @if(auth()->check() && (auth()->user()->isStaff() || auth()->user()->isAdmin()))
        // Refresh attendees list immediately after successful check-in (only for staff)
        setTimeout(function() {
            if(typeof refreshAttendees === 'function') refreshAttendees();
        }, 500);
        @endif
    @endif
    input.focus();
    input.select();
});
</script>
</body>
</html>
