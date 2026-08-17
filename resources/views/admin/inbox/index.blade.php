{{-- Admin: กล่องข้อความรวม --}}
@extends('layouts.admin')
@section('title', 'กล่องข้อความ')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold flex items-center gap-2" style="font-size:1.25rem;">
        <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        กล่องข้อความ
    </h1>
    <span class="text-sm text-muted">การสนทนาทั้งหมด {{ $threads->count() }} รายการ</span>
</div>

<style>
.inbox-thread-item { transition: background .15s, border-color .15s; }
.inbox-thread-item:hover { background: #f8fafc; }
.inbox-thread-item.unread { background: #FF9933 !important; color: #000 !important; }
.inbox-thread-item.unread:hover { background: #e68a2e !important; }
.inbox-thread-item.unread .inbox-unread-text { color: #000 !important; }
.inbox-thread-item.unread p { color: #000 !important; }
.inbox-thread-item.unread .inbox-job-title { color: #431407 !important; font-weight: 600 !important; }
.inbox-thread-item.unread .inbox-time { color: #431407 !important; font-weight: 500 !important; }

/* Light theme */
html[data-theme="light"] .inbox-thread-item { border-bottom-color: #f1f5f9 !important; }
html[data-theme="light"] .inbox-thread-item:hover { background: #f8fafc !important; }
html[data-theme="light"] .inbox-read-text { color: #64748b !important; }

/* Dark theme */
html[data-theme="dark"] .inbox-thread-item { border-bottom-color: #27272a !important; }
html[data-theme="dark"] .inbox-thread-item:hover { background: #27272a !important; }
html[data-theme="dark"] .inbox-read-text { color: #a1a1aa !important; }
</style>

<div class="card" style="padding:0;overflow:hidden;">
    @forelse($threads as $thread)
    @php
        $unread = $thread['unread'] ?? 0;
        $time   = $thread['last_time'];
    @endphp
    <a href="javascript:void(0)" onclick="if(window.AdminChatManager) window.AdminChatManager.openChat('{{ route('admin.inbox.show', [$thread['job_id'], $thread['student_id']]) }}', '{{ addslashes($thread['student_name']) }}', '{{ $thread['job_id'] }}_{{ $thread['student_id'] }}'); else window.location.href='{{ route('admin.inbox.show', [$thread['job_id'], $thread['student_id']]) }}';"
       class="inbox-thread-item {{ $unread > 0 ? 'unread' : '' }}"
       style="display:flex;align-items:center;gap:1rem;padding:.9rem 1.25rem;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;">

        {{-- Avatar --}}
        <div style="position:relative;flex-shrink:0;">
            @if(!empty($thread['student_photo']))
                <img src="{{ $thread['student_photo'] }}" alt="{{ $thread['student_name'] }}"
                     style="width:42px;height:42px;border-radius:50%;object-fit:cover;">
            @else
                <div style="width:42px;height:42px;border-radius:50%;background:#ea580c;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;">
                    {{ strtoupper(mb_substr($thread['student_name'], 0, 1)) }}
                </div>
            @endif
            <span class="student-online-dot student-online-dot-{{ $thread['student_id'] }}" style="display:none;position:absolute;bottom:0;right:0;width:11px;height:11px;background:#10b981;border:2px solid #fff;border-radius:50%;box-shadow:0 0 4px #10b981;" title="กำลังใช้งาน"></span>
        </div>

        {{-- Info --}}
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:.5rem;margin-bottom:.2rem;">
                <div style="display:flex;align-items:baseline;gap:.5rem;min-width:0;">
                    <span class="{{ $unread > 0 ? 'inbox-unread-text' : '' }}" style="font-weight:{{ $unread > 0 ? '700' : '600' }};font-size:.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">
                        {{ $thread['student_name'] }}
                    </span>
                    <span class="inbox-job-title" style="font-size:.8rem;color:#f97316;font-weight:500;flex-shrink:0;">
                        [{{ $thread['job_title'] }}]
                    </span>
                </div>
                <span class="student-status-text student-status-text-{{ $thread['student_id'] }}" data-student-id="{{ $thread['student_id'] }}" style="font-size:0.72rem;color:#10b981;font-weight:600;flex-shrink:0;"></span>
            </div>
            <p class="{{ $unread > 0 ? 'inbox-unread-text' : 'inbox-read-text' }}" style="margin:0;font-size:.82rem;color:{{ $unread > 0 ? '#1e293b' : '#64748b' }};font-weight:{{ $unread > 0 ? '700' : '400' }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {!! $thread['last_message'] ? e($thread['last_message']) : '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ไฟล์แนบ' !!}
            </p>
        </div>

        {{-- Time + Unread --}}
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;flex-shrink:0;">
            <span class="inbox-time" style="font-size:.72rem;color:#94a3b8;">
                @if($time)
                    @php
                        $msgSec  = (int) floor(max(0, $time->diffInSeconds(now())));
                        $msgMin  = (int) floor(max(0, $time->diffInMinutes(now())));
                        $msgHrs  = (int) floor(max(0, $time->diffInHours(now())));
                        $msgDays = (int) floor(max(0, $time->diffInDays(now())));
                    @endphp
                    @if($msgSec < 60)
                        เมื่อสักครู่
                    @elseif($msgMin < 60)
                        {{ $msgMin }} นาทีที่แล้ว
                    @elseif($msgHrs < 24)
                        {{ $msgHrs }} ชม. ที่แล้ว
                    @elseif($time->isYesterday())
                        เมื่อวานนี้ {{ $time->format('H:i') }}
                    @elseif($msgDays < 7)
                        {{ $msgDays }} วันที่แล้ว
                    @else
                        {{ $time->format('d/m/Y') }}
                    @endif
                @endif
            </span>
            @if($unread > 0)
            <span style="background:#ea580c;color:#fff;border-radius:999px;font-size:.7rem;font-weight:700;padding:.1rem .45rem;min-width:20px;text-align:center;">
                {{ $unread }}
            </span>
            @endif
        </div>
    </a>
    @empty
    <div style="padding:3rem;text-align:center;color:#94a3b8;">
        <div style="margin-bottom:.5rem;display:flex;justify-content:center;color:#94a3b8;">
            <svg style="width:48px;height:48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </div>
        <p style="margin:0;font-size:.95rem;">ยังไม่มีข้อความจากนักศึกษา</p>
    </div>
    @endforelse
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // รีเฟรช thread list โดย fetch HTML ใหม่และ replace เนื้อหาใน card
    window.refreshInboxList = function() {
        var url = window.location.href.split('?')[0] + '?_t=' + new Date().getTime();
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newCard = doc.querySelector('.card');
                const oldCard = document.querySelector('.card');
                if (newCard && oldCard) {
                    oldCard.innerHTML = newCard.innerHTML;
                    if (window.updateStudentOnlineDots) window.updateStudentOnlineDots();
                }
            })
            .catch(() => {});
    };

    (function initAdminInboxEcho() {
        if (!window.Echo) {
            setTimeout(initAdminInboxEcho, 200);
            return;
        }

        // admin.inbox จะ fire ทั้งเมื่อนักศึกษาส่ง และเมื่อ admin ส่ง
        window.Echo.private('admin.inbox')
            .listen('.MessageSent', function(e) {
                window.refreshInboxList();
            });

        function formatLastSeen(lastSeenAt) {
            if (!lastSeenAt) return '';
            var date = new Date(lastSeenAt);
            if (isNaN(date.getTime())) return '';
            var now = new Date();
            var diffSec = Math.max(0, Math.floor((now - date) / 1000));
            var diffMin = Math.floor(diffSec / 60);
            var diffHours = Math.floor(diffMin / 60);
            if (diffSec < 60) {
                return 'ออนไลน์เมื่อสักครู่';
            } else if (diffMin < 60) {
                return 'ออนไลน์เมื่อ ' + diffMin + ' นาทีที่แล้ว';
            } else if (diffHours < 24) {
                return 'ออนไลน์เมื่อ ' + diffHours + ' ชม. ที่แล้ว';
            } else {
                return 'ออนไลน์เมื่อ ' + date.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit' }) + ' ' + date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
            }
        }

        window.onlineStudentIds = new Set();
        window.updateStudentOnlineDots = function() {
            document.querySelectorAll('.student-online-dot').forEach(function(el) {
                var match = el.className.match(/student-online-dot-(\d+)/);
                if (match && window.onlineStudentIds.has(String(match[1]))) {
                    el.style.display = 'block';
                } else {
                    el.style.display = 'none';
                }
            });
            document.querySelectorAll('.student-status-text').forEach(function(el) {
                var match = el.className.match(/student-status-text-(\d+)/);
                if (match && window.onlineStudentIds.has(String(match[1]))) {
                    el.innerHTML = '<span style="color:#10b981;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;"></span> ใช้งานอยู่</span>';
                } else {
                    el.innerHTML = '';
                }
            });
        };
        window.updateStudentOnlineDots();

        window.Echo.join('online')
            .here(function(users) {
                var myAdminId = '{{ auth()->id() }}';
                window.onlineStudentIds = new Set(
                    users
                        .filter(function(u) { return String(u.id) !== String(myAdminId) && !u.is_staff && u.role !== 'admin' && u.role !== 'staff'; })
                        .map(function(u) { return String(u.id); })
                );
                window.updateStudentOnlineDots();
            })
            .joining(function(user) {
                var myAdminId = '{{ auth()->id() }}';
                if (String(user.id) !== String(myAdminId) && !user.is_staff && user.role !== 'admin' && user.role !== 'staff') {
                    window.onlineStudentIds.add(String(user.id));
                    window.updateStudentOnlineDots();
                }
            })
            .leaving(function(user) {
                window.onlineStudentIds.delete(String(user.id));
                document.querySelectorAll('.student-status-text-' + user.id).forEach(function(el) {
                    el.setAttribute('data-last-seen', new Date().toISOString());
                });
                window.updateStudentOnlineDots();
            });

        setInterval(function() {
            window.updateStudentOnlineDots();
        }, 15000);

        window.addEventListener('beforeunload', function() {
            if (window.Echo) {
                try { window.Echo.leave('online'); } catch(e) {}
            }
        });
    })();
});
</script>
@endsection
