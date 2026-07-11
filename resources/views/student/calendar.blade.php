{{-- หน้าปฏิทินกิจกรรม — FullCalendar.js --}}
@extends('layouts.app')
@section('title', 'ปฏิทินกิจกรรม')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="font-bold" style="font-size:1.5rem;">ปฏิทินกิจกรรม</h1>
        <p class="text-sm text-muted">คลิกกิจกรรมเพื่อดูรายละเอียด</p>
    </div>
    <a href="{{ route('student.my') }}" class="btn btn-outline btn-sm">
        <svg style="width:14px;height:14px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        กิจกรรมของฉัน
    </a>
</div>

{{-- Legend --}}
<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
    <div style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:#475569;">
        <span style="width:12px;height:12px;border-radius:3px;background:#6366f1;display:inline-block;flex-shrink:0;"></span> ลงทะเบียนแล้ว
    </div>
    <div style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:#475569;">
        <span style="width:12px;height:12px;border-radius:3px;background:#16a34a;display:inline-block;flex-shrink:0;"></span> เช็คอินแล้ว
    </div>
    <div style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:#475569;">
        <span style="width:12px;height:12px;border-radius:3px;background:#0ea5e9;display:inline-block;flex-shrink:0;"></span> เปิดรับสมัคร
    </div>
    <div style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:#475569;">
        <span style="width:12px;height:12px;border-radius:3px;background:#94a3b8;display:inline-block;flex-shrink:0;"></span> อื่นๆ
    </div>
</div>

{{-- FullCalendar container --}}
<div class="card" style="padding:0;overflow:hidden;">
    <div id="activity-calendar" style="padding:1rem;min-height:500px;"></div>
</div>

{{-- Event Popup Modal --}}
<div id="eventModal" class="modal-overlay" onclick="if(event.target===this)closeEventModal()" style="display:none;opacity:0;transition:opacity .2s;">
    <div class="modal" style="max-width:380px;border-radius:16px;overflow:hidden;">
        <div id="eventModalHeader" style="padding:1rem 1.25rem .75rem;position:relative;">
            <div id="eventModalCategory" style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.25rem;opacity:.8;"></div>
            <h3 id="eventModalTitle" style="font-size:1.05rem;font-weight:700;margin:0;color:#1e293b;line-height:1.3;padding-right:2rem;"></h3>
            <button onclick="closeEventModal()" style="position:absolute;top:.9rem;right:1rem;background:none;border:none;font-size:1.25rem;cursor:pointer;color:#94a3b8;line-height:1;">✕</button>
        </div>
        <div class="modal-body" style="padding-top:.5rem;">
            <div style="display:grid;gap:.5rem;margin-bottom:1rem;">
                <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#374151;">
                    <svg style="width:15px;height:15px;color:#6366f1;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span id="eventModalDate"></span>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#374151;">
                    <svg style="width:15px;height:15px;color:#6366f1;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span id="eventModalLocation"></span>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#374151;">
                    <svg style="width:15px;height:15px;color:#6366f1;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span id="eventModalHours"></span>
                </div>
            </div>
            <div id="eventModalBadges" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem;"></div>
            <div class="flex gap-2">
                <a id="eventModalBtn" href="#" class="btn btn-primary" style="flex:1;justify-content:center;border-radius:10px;">ดูรายละเอียด</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- FullCalendar v6 (CDN) --}}
<link href="https://unpkg.com/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://unpkg.com/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://unpkg.com/@fullcalendar/core@6.1.11/locales/th.global.min.js"></script>

<script>
var EVENTS_URL = '{{ route("student.calendar.events") }}';
var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

document.addEventListener('DOMContentLoaded', function() {
    var calEl = document.getElementById('activity-calendar');

    var cal = new FullCalendar.Calendar(calEl, {
        locale: 'th',
        initialView: window.innerWidth < 640 ? 'listMonth' : 'dayGridMonth',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: {
            today:    'วันนี้',
            month:    'เดือน',
            week:     'สัปดาห์',
            list:     'รายการ',
        },
        height: 'auto',
        events: function(fetchInfo, successCallback, failureCallback) {
            fetch(EVENTS_URL + '?start=' + fetchInfo.startStr + '&end=' + fetchInfo.endStr, {
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { successCallback(data); })
            .catch(function(e) { failureCallback(e); });
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            var ep = info.event.extendedProps;
            var start = info.event.start;
            var dateStr = start ? start.toLocaleDateString('th-TH', {
                year: 'numeric', month: 'long', day: 'numeric',
                weekday: 'short'
            }) : '';

            // Populate modal
            document.getElementById('eventModalTitle').textContent    = info.event.title;
            document.getElementById('eventModalCategory').textContent  = ep.category || '';
            document.getElementById('eventModalDate').textContent      = dateStr;
            document.getElementById('eventModalLocation').textContent  = ep.location || '-';
            document.getElementById('eventModalHours').textContent     = (ep.hours || 0) + ' ชั่วโมง';

            // Badges
            var badges = '';
            if (ep.is_checked_in) badges += '<span style="background:#dcfce7;color:#15803d;border-radius:999px;padding:3px 10px;font-size:.75rem;font-weight:600;">✓ เช็คอินแล้ว</span>';
            else if (ep.is_registered) badges += '<span style="background:#ede9fe;color:#6d28d9;border-radius:999px;padding:3px 10px;font-size:.75rem;font-weight:600;">ลงทะเบียนแล้ว</span>';
            if (ep.needs_feedback) badges += '<span style="background:#fef9c3;color:#a16207;border-radius:999px;padding:3px 10px;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;"><svg style="width:12px;height:12px;margin-right:2px;" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg> รอประเมิน</span>';
            document.getElementById('eventModalBadges').innerHTML = badges;

            // Header color
            document.getElementById('eventModalHeader').style.background =
                info.event.backgroundColor + '18';

            document.getElementById('eventModalBtn').href = info.event.url || '#';

            // Show modal
            var m = document.getElementById('eventModal');
            m.style.display = 'flex';
            requestAnimationFrame(function() { m.style.opacity = '1'; });
        },
        eventDidMount: function(info) {
            // Pulse effect for checkin-open
            if (info.event.extendedProps.is_registered && !info.event.extendedProps.is_checked_in) {
                info.el.style.boxShadow = '0 0 0 2px ' + info.event.backgroundColor + '88';
            }
        },
        noEventsContent: 'ไม่มีกิจกรรมในช่วงนี้',
        listDaySideFormat: { weekday: 'short' },
    });

    cal.render();
});

function closeEventModal() {
    var m = document.getElementById('eventModal');
    m.style.opacity = '0';
    setTimeout(function() { m.style.display = 'none'; }, 200);
}
</script>

<style>
/* FullCalendar Thai overrides */
.fc .fc-button { font-family: 'Sarabun', sans-serif !important; font-size: .8rem !important; }
.fc .fc-toolbar-title { font-size: 1rem !important; font-weight: 700 !important; }
.fc .fc-event { cursor: pointer; border-radius: 6px !important; font-size: .75rem !important; border: none !important; }
.fc .fc-event:hover { filter: brightness(1.1); }
.fc .fc-daygrid-day-number { font-size: .8rem !important; }
.fc .fc-list-event:hover td { background: #f8fafc !important; cursor: pointer; }
@media (max-width: 480px) {
    .fc .fc-toolbar { flex-direction: column; gap: .5rem; }
    .fc .fc-toolbar-chunk { display: flex; justify-content: center; }
}
</style>
@endsection
