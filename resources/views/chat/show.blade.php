@extends('layouts.app')

@section('content')
<style>
    :root {
        --chat-bg: #f8fafc;
        --chat-border: #e2e8f0;
        --chat-primary: #4f46e5;
        --chat-primary-hover: #4338ca;
        --chat-text-main: #1e293b;
        --chat-text-muted: #64748b;
        --chat-bubble-mine: #4f46e5;
        --chat-bubble-theirs: #ffffff;
        --chat-bubble-text-mine: #ffffff;
        --chat-bubble-text-theirs: #1e293b;
    }

    .chat-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 1.5rem;
        height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }

    .chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--chat-border);
        margin-bottom: 1rem;
    }

    .chat-header-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .chat-back-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #f1f5f9;
        color: var(--chat-text-muted);
        text-decoration: none;
        transition: all 0.2s;
    }

    .chat-back-btn:hover {
        background: #e2e8f0;
        color: var(--chat-text-main);
    }

    .chat-window {
        flex: 1;
        overflow-y: auto;
        background: var(--chat-bg);
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        scroll-behavior: smooth;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }

    /* Custom Scrollbar */
    .chat-window::-webkit-scrollbar {
        width: 6px;
    }
    .chat-window::-webkit-scrollbar-track {
        background: transparent;
    }
    .chat-window::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .date-separator {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 1.5rem 0;
        color: var(--chat-text-muted);
        font-size: 0.75rem;
        font-weight: 500;
    }

    .date-separator::before,
    .date-separator::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid var(--chat-border);
    }

    .date-separator:not(:empty)::before { margin-right: 1rem; }
    .date-separator:not(:empty)::after { margin-left: 1rem; }

    .message-wrapper {
        display: flex;
        gap: 0.75rem;
        animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-mine {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        background: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .message-content {
        display: flex;
        flex-direction: column;
        max-width: 75%;
    }

    .message-mine .message-content {
        align-items: flex-end;
    }

    .message-info {
        font-size: 0.7rem;
        color: var(--chat-text-muted);
        margin-bottom: 0.25rem;
        display: flex;
        gap: 0.5rem;
    }

    .message-bubble {
        padding: 0.75rem 1rem;
        border-radius: 18px;
        font-size: 0.9rem;
        line-height: 1.5;
        position: relative;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        word-break: break-word;
        white-space: pre-wrap;
    }

    .message-mine .message-bubble {
        background: var(--chat-bubble-mine);
        color: var(--chat-bubble-text-mine);
        border-bottom-right-radius: 4px;
    }

    .message-theirs .message-bubble {
        background: var(--chat-bubble-theirs);
        color: var(--chat-bubble-text-theirs);
        border-bottom-left-radius: 4px;
        border: 1px solid var(--chat-border);
    }

    .message-time {
        font-size: 0.65rem;
        color: var(--chat-text-muted);
        margin-top: 0.35rem;
    }

    .attachment-img {
        max-width: 100%;
        max-height: 300px;
        object-fit: contain;
        border-radius: 12px;
        margin-top: 0.5rem;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .attachment-img:hover {
        transform: scale(1.02);
    }

    .attachment-file {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        padding: 0.5rem;
        background: rgba(0,0,0,0.05);
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
        font-size: 0.8rem;
    }

    .input-area {
        background: white;
        padding: 1rem;
        border-radius: 16px;
        margin-top: 1rem;
        box-shadow: 0 -4px 12px rgba(0,0,0,0.03);
        border: 1px solid var(--chat-border);
    }

    .preview-container {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 0.75rem;
    }

    .preview-item {
        padding: 0.35rem 0.75rem;
        background: #eef2ff;
        border-radius: 20px;
        font-size: 0.75rem;
        color: var(--chat-primary);
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .input-group {
        display: flex;
        align-items: flex-end;
        gap: 0.75rem;
    }

    .file-label {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        background: #f8fafc;
        border: 1px solid var(--chat-border);
        border-radius: 12px;
        cursor: pointer;
        color: var(--chat-text-muted);
        transition: all 0.2s;
    }

    .file-label:hover {
        background: #f1f5f9;
        color: var(--chat-primary);
    }

    .chat-textarea {
        flex: 1;
        border: 1px solid var(--chat-border);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        resize: none;
        outline: none;
        max-height: 120px;
        transition: border-color 0.2s;
    }

    .chat-textarea:focus {
        border-color: var(--chat-primary);
    }

    .send-btn {
        width: 42px;
        height: 42px;
        background: var(--chat-primary);
        color: white;
        border: none;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .send-btn:hover {
        background: var(--chat-primary-hover);
        transform: translateY(-1px);
    }

    .send-btn:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    .typing-indicator {
        font-size: 0.75rem;
        color: var(--chat-primary);
        margin-top: 0.5rem;
        font-style: italic;
        display: none;
    }
</style>

<div class="chat-container">
    {{-- Header --}}
    <header class="chat-header">
        <div class="chat-header-info">
            <a href="{{ route('jobs.show', $job->id) }}" class="chat-back-btn" title="Back to Job">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <div>
                <h2 style="margin:0;font-size:1.1rem;font-weight:700;color:var(--chat-text-main);display:flex;align-items:center;gap:4px;">
                    <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    แชทกับผู้ดูแล
                </h2>
                <p style="margin:0;font-size:0.8rem;color:var(--chat-text-muted);">{{ $job->title }}</p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span style="width:8px;height:8px;background:#10b981;border-radius:50%;"></span>
            <span style="font-size:0.75rem;color:var(--chat-text-muted);font-weight:500;">Online</span>
        </div>
    </header>

    {{-- Chat Window --}}
    <div id="chatWindow" class="chat-window">
        @php $lastDate = null; @endphp
        @forelse($messages as $msg)
            @php
                $msgDate = $msg->created_at?->format('Y-m-d');
                $isMine = $msg->user_id == auth()->id();
                $senderLabel = $isMine ? 'คุณ' : ($msg->user?->full_name ?? 'ผู้ดูแล');
            @endphp

            @if($msgDate !== $lastDate)
                <div class="date-separator">
                    @if($msgDate == date('Y-m-d')) วันนี้
                    @elseif($msgDate == date('Y-m-d', strtotime('-1 day'))) เมื่อวานนี้
                    @else {{ $msg->created_at?->translatedFormat('j F Y') }}
                    @endif
                </div>
                @php $lastDate = $msgDate; @endphp
            @endif

            <div id="cm-{{ $msg->id }}" class="message-wrapper {{ $isMine ? 'message-mine' : 'message-theirs' }}">
                <div class="message-avatar">
                    @if($msg->user?->profile_photo)
                        <img src="{{ asset('storage/' . $msg->user->profile_photo) }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    @else
                        {{ mb_strtoupper(mb_substr($senderLabel, 0, 1)) }}
                    @endif
                </div>
                <div class="message-content">
                    <div class="message-info">{{ $senderLabel }}</div>
                    <div class="message-bubble">
                        @if($msg->body)
                            <div>{{ $msg->body }}</div>
                        @endif
                        @foreach($msg->attachments ?? [] as $att)
                            @php $isImg = str_starts_with($att['mime_type'] ?? '', 'image/'); @endphp
                            @if($isImg)
                                <img src="{{ $att['url'] }}" alt="{{ $att['original_name'] }}" class="attachment-img" onclick="window.open('{{ $att['url'] }}','_blank')">
                            @else
                                <a href="{{ $att['url'] }}" target="_blank" download="{{ $att['original_name'] }}" class="attachment-file">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                                    {{ $att['original_name'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                    <div class="message-time">{{ $msg->created_at?->format('H:i') }}</div>
                </div>
            </div>
        @empty
            <div id="noMsg" style="margin:auto;text-align:center;color:var(--chat-text-muted);">
                <div style="margin-bottom:1rem;color:#94a3b8;">
                    <svg style="width:48px;height:48px;margin:0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <p>ยังไม่มีข้อความ เริ่มแชทได้เลย</p>
            </div>
        @endforelse
    </div>

    {{-- Input Area --}}
    <div class="input-area">
        <form id="chatForm" enctype="multipart/form-data">
            @csrf
            <div id="attachPreview" class="preview-container" style="display:none;"></div>
            
            <div class="input-group">
                <label class="file-label" title="แนบไฟล์">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                    <input type="file" id="fileInput" name="attachments[]" multiple style="display:none;">
                </label>

                <textarea id="msgInput" name="message" rows="1" class="chat-textarea" placeholder="พิมพ์ข้อความที่นี่..."></textarea>

                <button type="submit" id="sendBtn" class="send-btn">
                    <svg style="width:18px;height:18px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
            <div id="typingIndicator" class="typing-indicator flex items-center gap-1">
                <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                ผู้ดูแลกำลังพิมพ์...
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const USER_ID = {{ auth()->id() }};
    const sendUrl = '{{ route('chat.send', $job->id) }}';
    const readUrl = '{{ route('chat.read', $job->id) }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const roomID = '{{ $room->id }}';

    const chatWindow = document.getElementById('chatWindow');
    const chatForm = document.getElementById('chatForm');
    const msgInput = document.getElementById('msgInput');
    const fileInput = document.getElementById('fileInput');
    const attachPrev = document.getElementById('attachPreview');
    const sendBtn = document.getElementById('sendBtn');
    const typingIndicator = document.getElementById('typingIndicator');

    function scrollBottom() {
        chatWindow.scrollTo({ top: chatWindow.scrollHeight, behavior: 'smooth' });
    }
    scrollBottom();

    // Mark as read
    fetch(readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });

    // Auto-resize textarea
    msgInput.addEventListener('input', () => {
        msgInput.style.height = 'auto';
        msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
    });

    // File preview
    fileInput.addEventListener('change', () => {
        attachPrev.innerHTML = '';
        if (fileInput.files.length === 0) {
            attachPrev.style.display = 'none';
            return;
        }
        attachPrev.style.display = 'flex';
        Array.from(fileInput.files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'preview-item';
            item.innerHTML = `
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                ${file.name.length > 15 ? file.name.substring(0, 15) + '...' : file.name}
            `;
            attachPrev.appendChild(item);
        });
    });

    function renderMessage(msg, isMine) {
        const noMsg = document.getElementById('noMsg');
        if (noMsg) noMsg.remove();

        const label = isMine ? 'คุณ' : (msg.user?.name || 'ผู้ดูแล');
        const photo = msg.user?.photo || null;

        const wrapper = document.createElement('div');
        wrapper.id = 'cm-' + msg.id;
        wrapper.className = `message-wrapper ${isMine ? 'message-mine' : 'message-theirs'}`;

        let avatarHtml = photo 
            ? `<img src="${photo}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`
            : label.charAt(0).toUpperCase();

        let attachmentsHtml = '';
        (msg.attachments || []).forEach(att => {
            const isImg = att.mime_type?.startsWith('image/');
            if (isImg) {
                attachmentsHtml += `<img src="${att.url}" alt="" class="attachment-img" onclick="window.open('${att.url}','_blank')">`;
            } else {
                attachmentsHtml += `
                    <a href="${att.url}" target="_blank" download="${att.original_name}" class="attachment-file">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                        ${att.original_name}
                    </a>`;
            }
        });

        const timeStr = new Date(msg.created_at).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });

        const safeMessage = msg.message 
            ? msg.message.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;')
            : '';

        let statusHtml = `<div class="message-time">${timeStr}</div>`;
        if (isMine) {
            const isTemp = String(msg.id).startsWith('tmp-');
            statusHtml = `
                <div style="display:flex;align-items:center;gap:.25rem;margin-top:.35rem;">
                    <span class="message-time">${timeStr}</span>
                    <span id="status-${msg.id}" style="font-size:.65rem;color:${isTemp ? '#94a3b8' : '#6366f1'};">
                        ${isTemp ? 'กำลังส่ง...' : '✓ ส่งแล้ว'}
                    </span>
                </div>`;
        }

        wrapper.innerHTML = `
            <div class="message-avatar">${avatarHtml}</div>
            <div class="message-content">
                <div class="message-info">${label}</div>
                <div class="message-bubble">
                    ${safeMessage ? `<div>${safeMessage}</div>` : ''}
                    ${attachmentsHtml}
                </div>
                ${statusHtml}
            </div>
        `;

        chatWindow.appendChild(wrapper);
        scrollBottom();
    }

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = msgInput.value.trim();
        if (!text && fileInput.files.length === 0) return;

        sendBtn.disabled = true;
        const formData = new FormData(chatForm);

        // Optimistic UI
        const tempId = 'tmp-' + Date.now();
        const optimisticMsg = {
            id: tempId,
            message: text,
            user_id: USER_ID,
            attachments: [],
            created_at: new Date().toISOString(),
            user: {
                id: USER_ID,
                name: 'คุณ'
            }
        };

        if (fileInput.files.length > 0) {
            Array.from(fileInput.files).forEach(f => {
                optimisticMsg.attachments.push({
                    original_name: f.name,
                    url: '#',
                    mime_type: f.type
                });
            });
        }

        renderMessage(optimisticMsg, true);
        const optimisticBubble = document.getElementById('cm-' + tempId);
        if (optimisticBubble) optimisticBubble.style.opacity = '0.6';

        // Clear input
        msgInput.value = '';
        msgInput.style.height = 'auto';
        fileInput.value = '';
        attachPrev.innerHTML = '';
        attachPrev.style.display = 'none';

        try {
            const response = await window.axios.post(sendUrl, formData, {
                headers: { 'Accept': 'application/json' }
            });
            const data = response.data;
            if (data.success) {
                if (optimisticBubble) optimisticBubble.remove();
                renderMessage(data.message, true);
            }
        } catch (err) {
            if (optimisticBubble) {
                optimisticBubble.style.opacity = '1';
                optimisticBubble.querySelector('.message-bubble').style.border = '1px solid #ef4444';
            }
            alert('ไม่สามารถส่งข้อความได้ กรุณาลองใหม่');
        } finally {
            sendBtn.disabled = false; sendBtn.innerHTML = '<svg style="width:18px;height:18px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        }
    });

    // Real-time with Echo
    const initEcho = () => {
        if (window.Echo) {
            window.Echo.private('chat.room.' + roomID)
                .listen('.MessageSent', (data) => {
                    if (data.user.id == USER_ID) return;
                    if (!document.getElementById('cm-' + data.id)) {
                        renderMessage(data, false);
                        window.axios.post(readUrl);
                    }
                })
                .listenForWhisper('typing', (e) => {
                    typingIndicator.style.display = 'block';
                    clearTimeout(window.typingTimer);
                    window.typingTimer = setTimeout(() => { typingIndicator.style.display = 'none'; }, 3000);
                });

            msgInput.addEventListener('input', () => {
                window.Echo.private('chat.room.' + roomID)
                    .whisper('typing', { userId: USER_ID });
            });
        } else {
            setTimeout(initEcho, 200);
        }
    };
    initEcho();
});
</script>
@endsection
