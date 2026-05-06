@extends('layouts.app')

@section('content')
<div style="max-width:700px;margin:0 auto;padding:1rem;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
        <a href="{{ route('jobs.show', $job->id) }}" style="color:#6366f1;font-size:.85rem;">← กลับ</a>
        <div>
            <h2 style="margin:0;font-size:1.1rem;font-weight:600;color:#1e293b;">💬 แชทกับผู้ดูแล</h2>
            <p style="margin:0;font-size:.8rem;color:#64748b;">{{ $job->title }}</p>
        </div>
    </div>

    {{-- Chat window --}}
    <div id="chatWindow" style="height:480px;overflow-y:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;display:flex;flex-direction:column;gap:.6rem;margin-bottom:.75rem;">
        @forelse($messages as $msg)
            @php
                $mine      = $msg->sender_id == auth()->id();
                $label     = $mine ? 'คุณ' : ($msg->sender_name ?? 'ผู้ดูแล');
                $photoUrl  = $msg->sender_photo ?? null;
                $initial   = mb_strtoupper(mb_substr($label, 0, 1));
                $avatarBg  = $mine ? '#4f46e5' : '#64748b';
            @endphp
            <div id="cm-{{ $msg->_id }}"
                 style="display:flex;flex-direction:{{ $mine ? 'row-reverse' : 'row' }};align-items:flex-end;gap:.4rem;">
                {{-- Avatar --}}
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $label }}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                @else
                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $avatarBg }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;flex-shrink:0;">{{ $initial }}</div>
                @endif
                {{-- Bubble column --}}
                <div style="display:flex;flex-direction:column;align-items:{{ $mine ? 'flex-end' : 'flex-start' }};max-width:72%;">
                    <span style="font-size:.68rem;color:#94a3b8;margin-bottom:.15rem;">{{ $label }}</span>
                    <div style="padding:.55rem .85rem;border-radius:{{ $mine ? '16px 4px 16px 16px' : '4px 16px 16px 16px' }};background:{{ $mine ? '#4f46e5' : '#fff' }};color:{{ $mine ? '#fff' : '#1e293b' }};font-size:.875rem;box-shadow:0 1px 3px rgba(0,0,0,.08);word-break:break-word;">
                        @if($msg->message)
                            <p style="margin:0;">{{ $msg->message }}</p>
                        @endif
                        @foreach($msg->attachments ?? [] as $att)
                            @php $isImg = str_starts_with($att['mime_type'] ?? '', 'image/'); @endphp
                            @if($isImg)
                                <img src="{{ $att['url'] }}" alt="{{ $att['original_name'] }}"
                                     style="max-width:100%;border-radius:8px;margin-top:.35rem;display:block;cursor:pointer;"
                                     onclick="window.open('{{ $att['url'] }}','_blank')">
                            @else
                                <a href="{{ $att['url'] }}" target="_blank" download="{{ $att['original_name'] }}"
                                   style="display:flex;align-items:center;gap:.4rem;margin-top:.35rem;color:{{ $mine ? '#c7d2fe' : '#4f46e5' }};font-size:.8rem;text-decoration:none;">
                                    📎 {{ $att['original_name'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                    <span style="font-size:.65rem;color:#94a3b8;margin-top:.15rem;">{{ $msg->created_at?->format('H:i') }}</span>
                </div>
            </div>
        @empty
            <p id="noMsg" class="text-sm text-muted text-center" style="margin:auto;">ยังไม่มีข้อความ เริ่มแชทได้เลย</p>
        @endforelse
    </div>

    {{-- Input form --}}
    <form id="chatForm" enctype="multipart/form-data">
        @csrf
        {{-- Attachment preview --}}
        <div id="attachPreview" style="display:none;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem;"></div>

        <div style="display:flex;gap:.5rem;align-items:flex-end;">
            {{-- File button --}}
            <label style="cursor:pointer;padding:.55rem .7rem;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:1.1rem;line-height:1;" title="แนบไฟล์">
                📎
                <input type="file" id="fileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
            </label>

            {{-- Textarea --}}
            <textarea id="msgInput" name="message" rows="1"
                placeholder="พิมพ์ข้อความ..."
                style="flex:1;resize:none;border:1px solid #e2e8f0;border-radius:8px;padding:.6rem .8rem;font-size:.9rem;line-height:1.4;outline:none;font-family:inherit;max-height:120px;overflow-y:auto;"></textarea>

            {{-- Send button --}}
            <button type="submit" id="sendBtn"
                style="padding:.55rem 1.1rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:.9rem;cursor:pointer;font-weight:500;white-space:nowrap;">
                ส่ง
            </button>
        </div>

        <p id="typingLabel" style="display:none;font-size:.75rem;color:#6366f1;margin:.3rem 0 0 .2rem;">✏️ ผู้ดูแลกำลังพิมพ์...</p>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const JOB_ID   = {{ $job->id }};
    const USER_ID  = {{ auth()->id() }};
    const sendUrl  = '{{ route('chat.send', $job->id) }}';
    const readUrl  = '{{ route('chat.read', $job->id) }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const chatWindow = document.getElementById('chatWindow');
    const chatForm   = document.getElementById('chatForm');
    const msgInput   = document.getElementById('msgInput');
    const fileInput  = document.getElementById('fileInput');
    const attachPrev = document.getElementById('attachPreview');
    const sendBtn    = document.getElementById('sendBtn');
    const typingLabel = document.getElementById('typingLabel');

    // ── Auto-scroll to bottom ──
    function scrollBottom() {
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }
    scrollBottom();

    // ── Mark messages as read ──
    fetch(readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });

    // ── Textarea auto-resize ──
    msgInput.addEventListener('input', () => {
        msgInput.style.height = 'auto';
        msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
    });

    // ── File attachment preview ──
    fileInput.addEventListener('change', () => {
        attachPrev.innerHTML = '';
        if (fileInput.files.length === 0) { attachPrev.style.display = 'none'; return; }
        attachPrev.style.display = 'flex';
        Array.from(fileInput.files).forEach(f => {
            const chip = document.createElement('span');
            chip.style.cssText = 'padding:.25rem .6rem;background:#e0e7ff;border-radius:20px;font-size:.75rem;color:#4f46e5;';
            chip.textContent = f.name.length > 20 ? f.name.slice(0, 20) + '...' : f.name;
            attachPrev.appendChild(chip);
        });
    });

    // ── Render a message bubble ──
    function renderBubble(msg, mine) {
        const noMsg = document.getElementById('noMsg');
        if (noMsg) noMsg.remove();

        const label = mine ? 'คุณ' : (msg.sender_name || 'ผู้ดูแล');
        const photo = msg.sender_photo || null;

        const wrap = document.createElement('div');
        wrap.id = 'cm-' + msg.id;
        wrap.style.cssText = 'display:flex;flex-direction:' + (mine ? 'row-reverse' : 'row') + ';align-items:flex-end;gap:.4rem;';

        // Avatar
        const avatar = document.createElement(photo ? 'img' : 'div');
        if (photo) {
            avatar.src = photo; avatar.alt = label;
            avatar.style.cssText = 'width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;';
        } else {
            avatar.textContent = label.charAt(0).toUpperCase();
            avatar.style.cssText = 'width:28px;height:28px;border-radius:50%;background:' + (mine ? '#4f46e5' : '#64748b') + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;flex-shrink:0;';
        }
        wrap.appendChild(avatar);

        // Column: name + bubble + time
        const col = document.createElement('div');
        col.style.cssText = 'display:flex;flex-direction:column;align-items:' + (mine ? 'flex-end' : 'flex-start') + ';max-width:72%;';

        const name = document.createElement('span');
        name.style.cssText = 'font-size:.68rem;color:#94a3b8;margin-bottom:.15rem;';
        name.textContent = label;
        col.appendChild(name);

        const bubble = document.createElement('div');
        bubble.style.cssText = 'padding:.55rem .85rem;border-radius:' +
            (mine ? '16px 4px 16px 16px' : '4px 16px 16px 16px') +
            ';background:' + (mine ? '#4f46e5' : '#fff') +
            ';color:' + (mine ? '#fff' : '#1e293b') +
            ';font-size:.875rem;box-shadow:0 1px 3px rgba(0,0,0,.08);word-break:break-word;';

        if (msg.message) {
            const p = document.createElement('p');
            p.style.margin = '0';
            p.textContent = msg.message;
            bubble.appendChild(p);
        }

        if (msg.attachments && msg.attachments.length > 0) {
            msg.attachments.forEach(att => {
                const isImg = att.mime_type && att.mime_type.startsWith('image/');
                if (isImg) {
                    const img = document.createElement('img');
                    img.src = att.url; img.alt = att.original_name;
                    img.style.cssText = 'max-width:100%;border-radius:8px;margin-top:.35rem;display:block;cursor:pointer;';
                    img.onclick = () => window.open(att.url, '_blank');
                    bubble.appendChild(img);
                } else {
                    const link = document.createElement('a');
                    link.href = att.url; link.target = '_blank'; link.download = att.original_name;
                    link.style.cssText = 'display:flex;align-items:center;gap:.4rem;margin-top:.35rem;color:' + (mine ? '#c7d2fe' : '#4f46e5') + ';font-size:.8rem;text-decoration:none;';
                    link.innerHTML = '📎 ' + att.original_name;
                    bubble.appendChild(link);
                }
            });
        }
        col.appendChild(bubble);

        const time = document.createElement('span');
        time.style.cssText = 'font-size:.65rem;color:#94a3b8;margin-top:.15rem;';
        time.textContent = new Date(msg.created_at).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
        col.appendChild(time);

        wrap.appendChild(col);
        chatWindow.appendChild(wrap);
        scrollBottom();
    }

    // ── Send message ──
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = msgInput.value.trim();
        if (!text && fileInput.files.length === 0) return;

        sendBtn.disabled = true;
        sendBtn.textContent = '...';

        const fd = new FormData(chatForm);

        try {
            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await res.json();
            if (data.success) {
                renderBubble(data.message, true);
                msgInput.value = '';
                msgInput.style.height = 'auto';
                fileInput.value = '';
                attachPrev.innerHTML = '';
                attachPrev.style.display = 'none';
            }
        } finally {
            sendBtn.disabled = false;
            sendBtn.textContent = 'ส่ง';
        }
    });

    // ── Socket.io ──
    if (typeof io !== 'undefined') {
        const socket = io('{{ config('socket.public_url') }}');
        socket.emit('join', 'chat:student:' + USER_ID);
        socket.emit('join', 'chat:thread:' + JOB_ID + ':' + USER_ID);

        let typingTimer;
        socket.on('chat:message', (msg) => {
            if (msg.job_id != JOB_ID) return;
            if (msg.sender_id == USER_ID) return;
            if (!document.getElementById('cm-' + msg.id)) {
                renderBubble(msg, false);
            }
            fetch(readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });
        });

        socket.on('typing', ({ userId }) => {
            if (userId == USER_ID) return;
            typingLabel.style.display = 'block';
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => { typingLabel.style.display = 'none'; }, 3000);
        });

        msgInput.addEventListener('input', () => {
            socket.emit('typing', { toRoom: 'chat:admin:' + JOB_ID, userId: USER_ID,
                name: '{{ addslashes(auth()->user()->full_name ?? '') }}' });
        });
    }
});
</script>
@endsection
