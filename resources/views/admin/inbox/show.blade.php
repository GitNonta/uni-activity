{{-- Admin: หน้าแชทกับนักศึกษาคนหนึ่ง (จาก Inbox) --}}
@extends('layouts.admin')
@section('title', $student->full_name . ' — ' . $job->title)

@section('content')
    @if(request('widget'))
    <style>
        .sb-sidebar, .sb-topbar, .admin-mobile-header { display: none !important; }
        .sb-content { margin-left: 0 !important; padding-top: 0 !important; }
        .sb-main { padding: 0 !important; height: 100vh; display: flex; flex-direction: column; }
        .chat-header-container { display: none !important; }
        #chatWindow { flex: 1; height: 0 !important; border: none !important; border-radius: 0 !important; margin: 0 !important; }
        body { background: #fff !important; overflow: hidden; }
        form#chatForm { padding: 0.75rem; background: #fff; border-top: 1px solid #e2e8f0; }
    </style>
    @endif

    {{-- Header --}}
    <div class="chat-header-container" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('admin.inbox.index') }}" style="color:#6366f1;font-size:.85rem;">← กล่องข้อความ</a>
        <div>
            <h2 style="margin:0;font-size:1.05rem;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:.4rem;">
                {{ $student->full_name }}
                <span id="adminOnlineDot" style="display:none;width:8px;height:8px;background:#10b981;border-radius:50%;vertical-align:middle;box-shadow:0 0 0 2px #fff;" title="ออนไลน์"></span>
                <span style="font-size:.85rem;color:#6366f1;font-weight:500;">[{{ $job->title }}]</span>
            </h2>
        </div>
        <button onclick="deleteChat()" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:0.4rem;border-radius:50%;margin-left:auto;display:flex;align-items:center;justify-content:center;" title="ลบแชท">
            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
    </div>

    {{-- Chat window --}}
    <div id="chatWindow" style="height:460px;overflow-y:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;display:flex;flex-direction:column;gap:.6rem;margin-bottom:.75rem;">
        @forelse($messages as $msg)
            @php
                $isMine   = $msg->user_id == auth()->id();
                $label    = $isMine ? 'คุณ' : ($msg->user?->full_name ?? $student->full_name);
                $photoUrl = $msg->user?->profile_photo ? asset('storage/' . $msg->user->profile_photo) : null;
                $initial  = mb_strtoupper(mb_substr($label, 0, 1));
                $avatarBg = $isMine ? '#4f46e5' : '#64748b';
            @endphp
            <div id="cm-{{ $msg->id }}"
                 class="msg-bubble-container"
                 style="display:flex;flex-direction:{{ $isMine ? 'row-reverse' : 'row' }};align-items:flex-end;gap:.4rem;position:relative;"
                 onmouseover="const a=this.querySelector('.msg-actions');if(a)a.style.display='flex'"
                 onmouseout="const a=this.querySelector('.msg-actions');if(a)a.style.display='none'">
                {{-- Avatar with online dot --}}
                <div style="position:relative;flex-shrink:0;">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $label }}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                    @else
                        <div style="width:28px;height:28px;border-radius:50%;background:{{ $avatarBg }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;">{{ $initial }}</div>
                    @endif
                    {{-- Online dot for student --}}
                    @if(!$isMine)
                        <span id="avatar-dot-{{ $msg->id }}" class="student-online-dot" style="display:none;position:absolute;bottom:0;right:0;width:10px;height:10px;background:#10b981;border-radius:50%;border:2px solid #f8fafc;"></span>
                    @endif
                </div>
                {{-- Bubble column --}}
                <div style="display:flex;flex-direction:column;align-items:{{ $isMine ? 'flex-end' : 'flex-start' }};max-width:72%;">
                    <span style="font-size:.68rem;color:#94a3b8;margin-bottom:.15rem;">{{ $label }}</span>
                    <div style="padding:.55rem .85rem;border-radius:{{ $isMine ? '16px 4px 16px 16px' : '4px 16px 16px 16px' }};background:{{ $isMine ? '#4f46e5' : '#fff' }};color:{{ $isMine ? '#fff' : '#1e293b' }};font-size:.875rem;box-shadow:0 1px 3px rgba(0,0,0,.08);word-break:break-word;">
                        @if($msg->body)
                            <p style="margin:0;">{{ $msg->body }}</p>
                        @endif
                        @foreach($msg->attachments ?? [] as $att)
                            @php $isImg = str_starts_with($att['mime_type'] ?? '', 'image/'); @endphp
                            @if($isImg)
                                <img src="{{ $att['url'] }}" alt="{{ $att['original_name'] }}"
                                     style="max-width:240px;max-height:240px;object-fit:cover;border-radius:8px;margin-top:.35rem;display:block;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.1);"
                                     onclick="window.open('{{ $att['url'] }}','_blank')">
                            @else
                                <a href="{{ $att['url'] }}" target="_blank" download="{{ $att['original_name'] }}"
                                   style="display:flex;align-items:center;gap:.4rem;margin-top:.35rem;color:{{ $isMine ? '#c7d2fe' : '#4f46e5' }};font-size:.8rem;text-decoration:none;">
                                    <svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> {{ $att['original_name'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                    <span style="font-size:.65rem;color:#94a3b8;margin-top:.15rem;">{{ $msg->created_at?->format('H:i') }}</span>
                </div>
                
                {{-- Actions --}}
                <div class="msg-actions" style="display:none; position:absolute; bottom:20px; {{ $isMine ? 'right:100%; margin-right:5px;' : 'left:100%; margin-left:5px;' }} background:#fff; padding:2px 4px; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,0.15); gap:2px; flex-direction: row; white-space: nowrap; z-index: 10;">
                    <button onclick="editMessageBtn('{{$msg->id}}')" style="background:none;border:none;cursor:pointer;padding:2px 4px;color:#64748b;font-size:1rem;" title="แก้ไข">✏️</button>
                    <button onclick="deleteMessageBtn('{{$msg->id}}')" style="background:none;border:none;cursor:pointer;padding:2px 4px;color:#ef4444;font-size:1rem;" title="ลบ">🗑️</button>
                </div>
            </div>
        @empty
            <p id="noMsg" style="margin:auto;font-size:.875rem;color:#94a3b8;">ยังไม่มีข้อความ</p>
        @endforelse
    </div>

    {{-- Typing indicator bar --}}
    <div id="adminTypingBar" style="display:none;align-items:center;padding:.4rem .75rem;background:#f8fafc;font-size:.72rem;color:#6366f1;">
        <svg style="width:12px;height:12px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
        {{ $student->full_name }} กำลังพิมพ์...
    </div>
    {{-- Input form --}}
    <form id="chatForm" enctype="multipart/form-data">
        @csrf
        <div id="attachPreview" style="display:none;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem;"></div>
        <div style="display:flex;gap:.5rem;align-items:flex-end;">
            <label for="fileInput" style="cursor:pointer;padding:.5rem .6rem;background:#f1f5f9;border-radius:8px;font-size:1.1rem;line-height:1;flex-shrink:0;display:flex;align-items:center;" title="แนบไฟล์">
                <svg style="width:18px;height:18px;color:#64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            </label>
            <input type="file" id="fileInput" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
            <textarea id="msgInput" name="message" rows="1"
                placeholder="พิมพ์ข้อความ..."
                style="flex:1;resize:none;border:1px solid #e2e8f0;border-radius:10px;padding:.55rem .8rem;font-size:.875rem;line-height:1.5;outline:none;font-family:inherit;max-height:100px;overflow-y:auto;"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('sendBtn').click();}"></textarea>
            <button id="sendBtn" type="submit"
                style="background:#4f46e5;color:#fff;border:none;border-radius:10px;padding:.55rem 1.1rem;font-size:.875rem;cursor:pointer;flex-shrink:0;">ส่ง</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const win        = document.getElementById('chatWindow');
    const form       = document.getElementById('chatForm');
    const input      = document.getElementById('msgInput');
    const fileInput  = document.getElementById('fileInput');
    const preview    = document.getElementById('attachPreview');
    const sendUrl    = '{{ route('admin.inbox.send', [$job->id, $student->id]) }}';
    const readUrl    = '{{ route('admin.inbox.read', [$job->id, $student->id]) }}';
    const csrfToken  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const myId       = '{{ Auth::id() }}';
    const studentId  = '{{ $student->id }}';

    // Scroll to bottom
    win.scrollTop = win.scrollHeight;

    // Mark messages as read
    fetch(readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });

    // File preview
    fileInput.addEventListener('change', function () {
        preview.innerHTML = '';
        if (!fileInput.files.length) { preview.style.display = 'none'; return; }
        preview.style.display = 'flex';
        Array.from(fileInput.files).forEach(f => {
            const chip = document.createElement('span');
            chip.style.cssText = 'background:#e0e7ff;color:#3730a3;border-radius:6px;padding:.2rem .55rem;font-size:.78rem;';
            chip.textContent = f.name;
            preview.appendChild(chip);
        });
    });

    // Render bubble helper
    function renderBubble(msg, isMine) {
        if (isMine === undefined) {
            isMine = msg.user?.id == myId || (msg.sender_role === 'admin' && !msg.user);
        }

        const dir   = isMine ? 'row-reverse' : 'row';
        const align = isMine ? 'flex-end' : 'flex-start';
        const bg    = isMine ? '#4f46e5' : '#fff';
        const color = isMine ? '#fff'    : '#1e293b';
        const br    = isMine ? '16px 4px 16px 16px' : '4px 16px 16px 16px';
        const linkC = isMine ? '#c7d2fe' : '#4f46e5';
        const label = isMine ? 'คุณ' : (msg.user?.name || '{{ $student->full_name }}');
        const photo = msg.user?.photo || msg.sender_photo || null;
        const avatarBg = isMine ? '#4f46e5' : '#64748b';
        const initial  = label.charAt(0).toUpperCase();
        
        var avatarHtml = '';
        if (photo) {
            avatarHtml = '<div style="position:relative;flex-shrink:0;"><img src="' + photo + '" alt="' + label + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">' + (!isMine ? '<span id="avatar-dot-' + msg.id + '" class="student-online-dot" style="display:none;position:absolute;bottom:0;right:0;width:10px;height:10px;background:#10b981;border-radius:50%;border:2px solid #f8fafc;"></span>' : '') + '</div>';
        } else {
            avatarHtml = '<div style="position:relative;flex-shrink:0;"><div style="width:28px;height:28px;border-radius:50%;background:' + avatarBg + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;">' + initial + '</div>' + (!isMine ? '<span id="avatar-dot-' + msg.id + '" class="student-online-dot" style="display:none;position:absolute;bottom:0;right:0;width:10px;height:10px;background:#10b981;border-radius:50%;border:2px solid #f8fafc;"></span>' : '') + '</div>';
        }

        var attHtml = '';
        (msg.attachments || []).forEach(function(a) {
            if ((a.mime_type || '').startsWith('image/')) {
                attHtml += '<img src="' + a.url + '" style="max-width:240px;max-height:240px;object-fit:cover;border-radius:8px;margin-top:.35rem;display:block;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.1);" onclick="window.open(\'' + a.url + '\',\'_blank\')">';
            } else {
                attHtml += '<a href="' + a.url + '" target="_blank" style="display:flex;align-items:center;gap:.4rem;margin-top:.35rem;color:' + linkC + ';font-size:.8rem;text-decoration:none;"><svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ' + a.original_name + '</a>';
            }
        });

        const time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString('th-TH', {hour:'2-digit',minute:'2-digit'}) : '';
        var status = '';
        if (isMine && msg.read_at) {
            status = '<span id="admin-read-' + msg.id + '" style="font-size:.6rem;color:#6366f1;">✓✓ เห็นเมื่อ ' + new Date(msg.read_at).toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'}) + '</span>';
        } else if (isMine) {
            status = '<span id="admin-read-' + msg.id + '" style="font-size:.6rem;color:#94a3b8;">✓ ส่งแล้ว</span>';
        }

        var actionsHtml = '';
        if (msg.id && !msg.id.toString().startsWith('tmp-')) {
            actionsHtml = '<div class="msg-actions" style="display:none; position:absolute; bottom:20px; ' + (isMine ? 'right:100%; margin-right:5px;' : 'left:100%; margin-left:5px;') + ' background:#fff; padding:4px; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,0.15); gap:4px; flex-direction: row; white-space: nowrap; z-index: 10; border:1px solid #e2e8f0;">' +
                (isMine ? '<button onclick="editMessageBtn(\'' + msg.id + '\')" style="background:none;border:none;cursor:pointer;padding:4px;color:#64748b;display:flex;align-items:center;justify-content:center;border-radius:4px;" title="แก้ไข" onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'transparent\'"><svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>' : '') +
                '<button onclick="deleteMessageBtn(\'' + msg.id + '\')" style="background:none;border:none;cursor:pointer;padding:4px;color:#ef4444;display:flex;align-items:center;justify-content:center;border-radius:4px;" title="ลบ" onmouseover="this.style.background=\'#fee2e2\'" onmouseout="this.style.background=\'transparent\'"><svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>' +
            '</div>';
        }

        return '<div id="cm-' + msg.id + '" class="msg-bubble-container" style="display:flex;flex-direction:' + dir + ';align-items:flex-end;gap:.4rem;position:relative;" onmouseover="const a=this.querySelector(\'.msg-actions\');if(a)a.style.display=\'flex\'" onmouseout="const a=this.querySelector(\'.msg-actions\');if(a)a.style.display=\'none\'">' +
            avatarHtml +
            '<div style="display:flex;flex-direction:column;align-items:' + align + ';max-width:72%;">' +
                '<span style="font-size:.68rem;color:#94a3b8;margin-bottom:.15rem;">' + label + '</span>' +
                '<div style="padding:.55rem .85rem;border-radius:' + br + ';background:' + bg + ';color:' + color + ';font-size:.875rem;box-shadow:0 1px 3px rgba(0,0,0,.08);word-break:break-word;" id="msg-body-' + msg.id + '">' +
                    (msg.message ? '<p style="margin:0;">' + msg.message.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>' : '') +
                    (msg.is_edited ? '<span style="font-size:0.6rem;opacity:0.7;margin-left:5px;">(แก้ไขแล้ว)</span>' : '') +
                    attHtml +
                '</div>' +
                (status || '<span style="font-size:.65rem;color:#94a3b8;margin-top:.15rem;">' + time + '</span>') +
            '</div>' +
            actionsHtml +
        '</div>';
    }

    // Send message
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text  = input.value.trim();
        const files = fileInput.files;
        if (!text && !files.length) return;

        const fd = new FormData(form);
        const btn = document.getElementById('sendBtn');
        btn.disabled = true;

        // Optimistic bubble
        const noMsg = document.getElementById('noMsg');
        if (noMsg) noMsg.remove();
        const ADMIN_PHOTO = '{{ Auth::user()->profile_photo ? asset('storage/' . Auth::user()->profile_photo) : '' }}';
        const optimistic = { 
            id: 'tmp-' + Date.now(), 
            sender_role: 'admin', 
            sender_name: 'คุณ', 
            sender_photo: ADMIN_PHOTO || null, 
            message: text, 
            attachments: [], 
            created_at: new Date().toISOString() 
        };
        win.insertAdjacentHTML('beforeend', renderBubble(optimistic, true));
        win.scrollTop = win.scrollHeight;
        input.value = '';
        fileInput.value = '';
        preview.innerHTML = '';
        preview.style.display = 'none';

        fetch(sendUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd })
            .then(r => r.json())
            .then(data => {
                const tmp = document.getElementById('cm-' + optimistic.id);
                if (tmp && data.message) {
                    tmp.outerHTML = renderBubble(data.message, true);
                } else if (tmp && data.error) {
                    tmp.style.opacity = '0.5';
                    tmp.style.border = '1px solid red';
                    alert(data.error);
                }
                btn.disabled = false;
            })
            .catch(() => { btn.disabled = false; });
    });

    // Laravel Echo — receive messages
    const initEcho = () => {
        if (window.Echo) {
            window.Echo.private('chat.room.' + '{{ $room->id }}')
                .listen('.MessageSent', function (msg) {

                    if (msg.user.id == myId) return;
                    const noMsg = document.getElementById('noMsg');
                    if (noMsg) noMsg.remove();
                    if (!document.getElementById('cm-' + msg.id)) {
                        win.insertAdjacentHTML('beforeend', renderBubble(msg, false));
                        win.scrollTop = win.scrollHeight;
                    }
                    // Auto mark-read
                    window.axios.post(readUrl);
                })
                .listenForWhisper('typing', function(e) {
                    if (e.userId == myId) return;
                    const bar = document.getElementById('adminTypingBar');
                    if (bar) {
                        bar.style.display = 'block';
                        clearTimeout(window.adminTypingTimer);
                        window.adminTypingTimer = setTimeout(() => bar.style.display='none', 3000);
                    }
                });

            // Typing emit
            input.addEventListener('input', function() {
                window.Echo.private('chat.room.' + '{{ $room->id }}')
                    .whisper('typing', {
                        userId: myId,
                        name: 'ผู้ดูแล'
                    });
            });
            
            // Presence
            window.Echo.join('online')
                .here((users) => {
                    const isOnline = users.some(u => u.id == studentId);
                    toggleStudentOnline(isOnline);
                })
                .joining((user) => {
                    if (user.id == studentId) toggleStudentOnline(true);
                })
                .leaving((user) => {
                    if (user.id == studentId) toggleStudentOnline(false);
                });
        } else {
            setTimeout(initEcho, 200);
        }
    };
    initEcho();

            // Delete and Edit Listeners
            window.Echo.private('chat.room.' + '{{ $room->id }}')
                .listen('.MessageDeleted', function (e) {
                    const el = document.getElementById('cm-' + e.id);
                    if (el) el.remove();
                })
                .listen('.MessageEdited', function (e) {
                    const bodyEl = document.getElementById('msg-body-' + e.id);
                    if (bodyEl) {
                        bodyEl.innerHTML = '<p style="margin:0;">' + e.message.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p><span style="font-size:0.6rem;opacity:0.7;margin-left:5px;">(แก้ไขแล้ว)</span>';
                    }
                });
            
            window.Echo.private('admin.inbox')
                .listen('.ChatDeleted', function (e) {
                    if (e.room_id === '{{ $room->id }}') {
                        if (window.AdminChatManager) {
                            window.AdminChatManager.closeChat('{{ $room->job_id }}_{{ $room->users->where("role","student")->first()?->id }}');
                        } else {
                            window.location.href = '{{ route("admin.inbox.index") }}';
                        }
                    }
                });

    function toggleStudentOnline(isOnline) {
        const headerDot = document.getElementById('adminOnlineDot');
        if (headerDot) headerDot.style.display = isOnline ? 'inline-block' : 'none';
        document.querySelectorAll('.student-online-dot').forEach(el => {
            el.style.display = isOnline ? 'inline-block' : 'none';
        });
    }

});

window.deleteChat = function() {
    if (!confirm('ยืนยันลบห้องแชทนี้และข้อความทั้งหมด? (นักศึกษาจะมองไม่เห็นแชทนี้อีก)')) return;
    fetch('{{ route("admin.inbox.delete", [$job->id, $student->id]) }}', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
    }).then(r => r.json()).then(res => {
        if (res.success) {
            if (window.AdminChatManager) {
                window.AdminChatManager.closeChat('{{ $job->id }}_{{ $student->id }}');
            } else {
                window.location.href = '{{ route("admin.inbox.index") }}';
            }
        }
    });
};

window.deleteMessageBtn = function(id) {
    if (!confirm('ต้องการลบข้อความนี้ใช่หรือไม่?')) return;
    fetch('/admin/inbox/messages/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
    }).then(r => r.json()).then(res => {
        if (res.success) {
            const el = document.getElementById('cm-' + id);
            if (el) el.remove();
        }
    });
};

window.editMessageBtn = function(id) {
    const newText = prompt('แก้ไขข้อความ:');
    if (newText === null || newText.trim() === '') return;
    
    fetch('/admin/inbox/messages/' + id, {
        method: 'PUT',
        headers: { 
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ message: newText })
    }).then(r => r.json()).then(res => {
        if (res.success) {
            const bodyEl = document.getElementById('msg-body-' + id);
            if (bodyEl) {
                bodyEl.innerHTML = '<p style="margin:0;">' + newText.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p><span style="font-size:0.6rem;opacity:0.7;margin-left:5px;">(แก้ไขแล้ว)</span>';
            }
        } else if (res.message) {
            alert(res.message); // Validation error
        }
    });
};
</script>
@endsection
