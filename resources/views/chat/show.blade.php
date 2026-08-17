@extends('layouts.app')

@section('title', 'แชทกับผู้ดูแล — ' . ($job->title ?? 'สอบถามข้อมูล'))

@section('content')
<style>
    :root {
        --chat-bg: #f8fafc;
        --chat-border: #e2e8f0;
        --chat-primary: #ea580c;
        --chat-primary-hover: #c2410c;
        --chat-text-main: #1e293b;
        --chat-text-muted: #64748b;
        --chat-bubble-mine: #ea580c;
        --chat-bubble-theirs: #ffffff;
        --chat-bubble-text-mine: #ffffff;
        --chat-bubble-text-theirs: #1e293b;
    }

    .chat-container {
        max-width: 860px;
        margin: 0 auto;
        padding: 1rem 1rem 1.5rem;
        height: calc(100vh - 85px);
        display: flex;
        flex-direction: column;
    }

    .chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: #ffffff;
        border: 1px solid var(--chat-border);
        border-radius: 16px;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
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
        width: 38px;
        height: 38px;
        border-radius: 10px;
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
        border: 1px solid var(--chat-border);
        border-radius: 16px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        scroll-behavior: smooth;
        position: relative;
    }

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
        margin: 1rem 0;
        color: var(--chat-text-muted);
        font-size: 0.75rem;
        font-weight: 600;
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
        gap: 0.6rem;
        position: relative;
        animation: fadeIn 0.25s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-mine {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        background: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.8rem;
        font-weight: 700;
        position: relative;
    }

    .message-content {
        display: flex;
        flex-direction: column;
        max-width: 72%;
    }

    .message-mine .message-content {
        align-items: flex-end;
    }

    .message-info {
        font-size: 0.72rem;
        color: var(--chat-text-muted);
        margin-bottom: 0.2rem;
        display: flex;
        gap: 0.4rem;
    }

    .message-bubble {
        padding: 0.65rem 0.95rem;
        border-radius: 16px;
        font-size: 0.92rem;
        line-height: 1.45;
        position: relative;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
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

    .chat-link {
        color: #dc2626 !important;
        text-decoration: underline !important;
        text-underline-offset: 3px;
        word-break: break-all;
        font-weight: 600;
        cursor: pointer;
    }

    .message-mine .chat-link,
    .chat-link-mine {
        color: #ea580c !important;
        background: #ffffff;
        padding: 1px 6px;
        border-radius: 6px;
        display: inline-block;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        text-decoration: underline !important;
    }

    .message-actions {
        display: none;
        align-items: center;
        gap: 0.25rem;
        position: absolute;
        top: -12px;
        background: #ffffff;
        border: 1px solid var(--chat-border);
        border-radius: 20px;
        padding: 2px 6px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        z-index: 10;
    }
    .message-mine .message-actions { right: 10px; }
    .message-theirs .message-actions { left: 40px; }
    .message-wrapper:hover .message-actions { display: flex; }

    .msg-action-btn {
        background: none;
        border: none;
        padding: 2px 4px;
        cursor: pointer;
        color: #64748b;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .msg-action-btn:hover { color: #ea580c; background: #f1f5f9; }

    .attachment-img {
        max-width: 100%;
        max-height: 280px;
        object-fit: contain;
        border-radius: 10px;
        margin-top: 0.4rem;
        cursor: pointer;
        transition: transform 0.15s;
    }
    .attachment-img:hover { transform: scale(1.02); }

    .attachment-file {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.4rem;
        padding: 0.45rem 0.75rem;
        background: rgba(0,0,0,0.06);
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
        font-size: 0.82rem;
        font-weight: 500;
    }

    .input-area {
        background: white;
        padding: 0.85rem 1rem;
        border-radius: 16px;
        margin-top: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid var(--chat-border);
    }

    .preview-container {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 0.6rem;
    }

    .preview-item {
        padding: 0.3rem 0.65rem;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 16px;
        font-size: 0.75rem;
        color: var(--chat-primary);
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .input-group {
        display: flex;
        align-items: flex-end;
        gap: 0.6rem;
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
        padding: 0.7rem 0.9rem;
        font-size: 0.92rem;
        resize: none;
        outline: none;
        max-height: 120px;
        line-height: 1.4;
        transition: border-color 0.2s;
    }
    .chat-textarea:focus { border-color: var(--chat-primary); }

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
    .send-btn:hover { background: var(--chat-primary-hover); transform: translateY(-1px); }
    .send-btn:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; }

    .typing-bar {
        font-size: 0.75rem;
        color: var(--chat-primary);
        margin-top: 0.4rem;
        display: none;
        align-items: center;
        gap: 4px;
        font-style: italic;
    }

    .scroll-bottom-badge {
        position: absolute;
        bottom: 16px;
        right: 24px;
        background: var(--chat-primary);
        color: #fff;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        box-shadow: 0 4px 10px rgba(234, 88, 12, 0.3);
        cursor: pointer;
        display: none;
        align-items: center;
        gap: 4px;
        z-index: 20;
        animation: bounce 1.5s infinite;
    }
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }
</style>

<div class="chat-container">
    {{-- Header --}}
    <header class="chat-header">
        <div class="chat-header-info">
            <a href="{{ $job->id > 0 ? route('jobs.show', $job->id) : route('jobs.index') }}" class="chat-back-btn" title="ย้อนกลับ">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <div>
                <h2 style="margin:0;font-size:1.05rem;font-weight:700;color:var(--chat-text-main);display:flex;align-items:center;gap:6px;">
                    <span>แชทกับผู้ดูแล</span>
                    <span id="staffOnlineDot" style="display:none;width:8px;height:8px;background:#10b981;border-radius:50%;box-shadow:0 0 0 2px #fff;" title="ออนไลน์"></span>
                </h2>
                <p style="margin:0;font-size:0.8rem;color:var(--chat-text-muted);">{{ $job->title ?? 'สอบถามข้อมูลเจ้าหน้าที่' }}</p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span id="onlineStatusLabel" style="font-size:0.75rem;color:var(--chat-text-muted);font-weight:500;">
                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;margin-right:4px;"></span>ออฟไลน์
            </span>
        </div>
    </header>

    {{-- Chat Window --}}
    <div id="chatWindow" class="chat-window">
        @php
            $lastDate = null;
            $otherUserObj = $room->users->firstWhere('id', '!=', auth()->id());
            $otherReadAtStr = $otherUserObj?->pivot?->last_read_at ?? null;
            $otherReadAt = $otherReadAtStr ? \Carbon\Carbon::parse($otherReadAtStr) : null;
            $lastMineMsgId = $messages->where('user_id', auth()->id())->last()?->id;
        @endphp
        @forelse($messages as $msg)
            @php
                $msgDate = $msg->created_at?->format('Y-m-d');
                $isMine = $msg->user_id == auth()->id();
                $senderLabel = $isMine ? 'คุณ' : ($msg->user?->full_name ?? 'ผู้ดูแล');

                $readStatusText = '✓ ส่งแล้ว';
                if ($isMine && $otherReadAt && $msg->created_at && $otherReadAt->gte($msg->created_at)) {
                    $diffSec = max(0, now()->diffInSeconds($otherReadAt));
                    $diffMin = max(0, now()->diffInMinutes($otherReadAt));
                    $diffHours = max(0, now()->diffInHours($otherReadAt));
                    if ($diffSec < 90) {
                        $readStatusText = '✓✓ เพิ่งอ่าน';
                    } elseif ($diffMin < 60) {
                        $readStatusText = "✓✓ เห็นเมื่อ {$diffMin} นาทีที่แล้ว";
                    } elseif ($diffHours < 24) {
                        $readStatusText = "✓✓ เห็นเมื่อ {$diffHours} ชม. ที่แล้ว";
                    } else {
                        $readStatusText = "✓✓ เห็นเมื่อ " . $otherReadAt->format('d/m H:i');
                    }
                }
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
                @if(!$isMine)
                <div class="message-avatar">
                    @if($msg->user?->profile_photo)
                        <img src="{{ asset('storage/' . $msg->user->profile_photo) }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    @else
                        {{ mb_strtoupper(mb_substr($senderLabel, 0, 1)) }}
                    @endif
                </div>
                @endif

                @if($isMine)
                <div class="message-actions">
                    <button class="msg-action-btn" onclick="editMyMessage('{{ $msg->id }}')" title="แก้ไข">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="msg-action-btn" onclick="deleteMyMessage('{{ $msg->id }}')" title="ลบ">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>
                @endif

                <div class="message-content">
                    <div class="message-info">{{ $senderLabel }}</div>
                    <div class="message-bubble" id="bubble-{{ $msg->id }}">
                        @if($msg->body)
                            @php
                                $linkClass = $isMine ? 'chat-link chat-link-mine' : 'chat-link';
                                $linkifiedMsg = preg_replace(
                                    '~(https?://[^\s<]+[^<.,:;"\')\]\s])~i',
                                    '<a href="$1" target="_blank" rel="noopener noreferrer" class="' . $linkClass . '">$1</a>',
                                    e($msg->body)
                                );
                            @endphp
                            <div class="msg-text-body">{!! $linkifiedMsg !!}</div>
                            @if($msg->is_edited)
                                <span class="edit-badge" style="font-size:0.65rem;opacity:0.8;margin-left:4px;">(แก้ไขแล้ว)</span>
                            @endif
                        @endif
                        @foreach($msg->attachments ?? [] as $att)
                            @php
                                $path = $att['path'] ?? $att['file_path'] ?? '';
                                $url = !empty($path) ? asset('storage/' . $path) : ($att['url'] ?? '#');
                                $isImg = str_starts_with($att['mime_type'] ?? '', 'image/');
                            @endphp
                            @if($isImg)
                                <img src="{{ $url }}" alt="{{ $att['original_name'] ?? 'image' }}" class="attachment-img" onclick="openLightbox('{{ $url }}')">
                            @else
                                <a href="{{ $url }}" target="_blank" download="{{ $att['original_name'] ?? 'file' }}" class="attachment-file">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                                    {{ $att['original_name'] ?? 'ไฟล์แนบ' }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                    <div style="display:flex;align-items:center;gap:0.35rem;margin-top:0.25rem;">
                        <span class="message-time">{{ $msg->created_at?->format('H:i') }}</span>
                        @if($isMine && $msg->id == $lastMineMsgId)
                            <span id="status-{{ $msg->id }}" class="msg-read-status" style="font-size:0.65rem;color:#ea580c;">{{ $readStatusText }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div id="noMsg" style="margin:auto;text-align:center;color:var(--chat-text-muted);">
                <div style="margin-bottom:0.75rem;color:#94a3b8;">
                    <svg style="width:44px;height:44px;margin:0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <p style="margin:0;font-size:0.9rem;">ยังไม่มีข้อความ เริ่มต้นสนทนากับผู้ดูแลได้เลย</p>
            </div>
        @endforelse

        {{-- Floating new message indicator --}}
        <div id="scrollBottomBtn" class="scroll-bottom-badge" onclick="scrollBottom(true)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
            ข้อความใหม่
        </div>
    </div>

    {{-- Input Area --}}
    <div class="input-area">
        <form id="chatForm" enctype="multipart/form-data">
            @csrf
            <div id="attachPreview" class="preview-container" style="display:none;"></div>
            
            <div class="input-group">
                <label class="file-label" title="แนบไฟล์ (หรือวางรูปภาพ Ctrl+V)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                    <input type="file" id="fileInput" name="attachments[]" multiple style="display:none;">
                </label>

                <textarea id="msgInput" name="message" rows="1" class="chat-textarea" placeholder="พิมพ์ข้อความ... (กด Enter เพื่อส่ง, วางภาพด้วย Ctrl+V)"></textarea>

                <button type="submit" id="sendBtn" class="send-btn" title="ส่งข้อความ">
                    <svg style="width:18px;height:18px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
            <div id="typingIndicator" class="typing-bar">
                <span>ผู้ดูแลกำลังพิมพ์...</span>
            </div>
        </form>
    </div>
</div>

{{-- Lightbox Modal --}}
<div id="chatLightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;" onclick="this.style.display='none'">
    <img id="lightboxImg" src="" style="max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chatWindow   = document.getElementById('chatWindow');
    const chatForm     = document.getElementById('chatForm');
    const msgInput     = document.getElementById('msgInput');
    const fileInput    = document.getElementById('fileInput');
    const attachPrev   = document.getElementById('attachPreview');
    const sendBtn      = document.getElementById('sendBtn');
    const typingIndicator = document.getElementById('typingIndicator');
    const scrollBtn    = document.getElementById('scrollBottomBtn');

    const USER_ID  = {{ (int) auth()->id() }};
    const roomID   = '{{ $room->id }}';
    const sendUrl  = '{{ route("chat.send", $job->id) }}';
    const readUrl  = '{{ route("chat.read", $job->id) }}';
    
    let isEditingId = null;

    // Web Audio soft chime
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch(e) {}
    }

    function scrollBottom(smooth = false) {
        if (smooth) {
            chatWindow.scrollTo({ top: chatWindow.scrollHeight, behavior: 'smooth' });
        } else {
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }
        scrollBtn.style.display = 'none';
    }

    chatWindow.addEventListener('scroll', () => {
        const isNearBottom = chatWindow.scrollHeight - chatWindow.scrollTop - chatWindow.clientHeight < 80;
        if (isNearBottom) scrollBtn.style.display = 'none';
    });

    scrollBottom();

    // Auto-expand textarea & handle Enter to submit
    msgInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    msgInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });

    // Paste image from clipboard
    document.addEventListener('paste', (e) => {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (let item of items) {
            if (item.type.indexOf('image') === 0) {
                const blob = item.getAsFile();
                const dataTransfer = new DataTransfer();
                if (fileInput.files.length > 0) {
                    Array.from(fileInput.files).forEach(f => dataTransfer.items.add(f));
                }
                dataTransfer.items.add(blob);
                fileInput.files = dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        }
    });

    fileInput.addEventListener('change', () => {
        attachPrev.innerHTML = '';
        if (fileInput.files.length > 0) {
            attachPrev.style.display = 'flex';
            Array.from(fileInput.files).forEach(file => {
                const item = document.createElement('div');
                item.className = 'preview-item';
                item.innerHTML = `
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                    ${file.name.length > 18 ? file.name.substring(0, 18) + '...' : file.name}
                `;
                attachPrev.appendChild(item);
            });
        } else {
            attachPrev.style.display = 'none';
        }
    });

    window.openLightbox = function(url) {
        document.getElementById('lightboxImg').src = url;
        document.getElementById('chatLightbox').style.display = 'flex';
    };

    function linkify(text, isMine) {
        if (!text) return '';
        const safe = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const cls = isMine ? 'chat-link chat-link-mine' : 'chat-link';
        const urlRegex = /(https?:\/\/[^\s<]+[^<.,:;"')\]\s])/gi;
        return safe.replace(urlRegex, (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer" class="${cls}">${url}</a>`);
    }

    function formatReadStatus(readAt, isRead, readStatus) {
        if (readStatus && readStatus !== 'ส่งแล้ว') {
            return readStatus.startsWith('✓') ? readStatus : '✓✓ ' + readStatus;
        }
        if (!readAt && !isRead) return '✓ ส่งแล้ว';
        if (!readAt) return '✓✓ เพิ่งอ่าน';

        const readTime = new Date(readAt);
        const now = new Date();
        const diffSec = Math.max(0, Math.floor((now - readTime) / 1000));
        const diffMin = Math.floor(diffSec / 60);
        const diffHours = Math.floor(diffMin / 60);

        if (diffSec < 90) {
            return '✓✓ เพิ่งอ่าน';
        } else if (diffMin < 60) {
            return `✓✓ เห็นเมื่อ ${diffMin} นาทีที่แล้ว`;
        } else if (diffHours < 24) {
            return `✓✓ เห็นเมื่อ ${diffHours} ชม. ที่แล้ว`;
        } else {
            return `✓✓ เห็นเมื่อ ${readTime.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })}`;
        }
    }

    function renderMessage(msg, isMine) {
        const noMsg = document.getElementById('noMsg');
        if (noMsg) noMsg.remove();

        const label = isMine ? 'คุณ' : (msg.user?.name || 'ผู้ดูแล');
        const photo = msg.user?.photo || null;

        const wrapper = document.createElement('div');
        wrapper.id = 'cm-' + msg.id;
        wrapper.className = `message-wrapper ${isMine ? 'message-mine' : 'message-theirs'}`;

        let avatarHtml = '';
        if (!isMine) {
            if (photo) {
                avatarHtml = `<img src="${photo}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
            } else {
                avatarHtml = label.charAt(0).toUpperCase();
            }
        }
        
        let attachmentsHtml = '';
        (msg.attachments || []).forEach(att => {
            const url = att.url || (att.file_path ? '/storage/' + att.file_path : '#');
            const isImg = att.is_image || att.mime_type?.startsWith('image/');
            if (isImg) {
                attachmentsHtml += `<img src="${url}" alt="" class="attachment-img" onclick="openLightbox('${url}')">`;
            } else {
                attachmentsHtml += `
                    <a href="${url}" target="_blank" download="${att.original_name}" class="attachment-file">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                        ${att.original_name}
                    </a>`;
            }
        });

        const timeStr = msg.time_formatted || new Date(msg.created_at).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
        const safeMessage = linkify(msg.message || msg.body || '', isMine);

        let actionsHtml = '';
        if (isMine && !String(msg.id).startsWith('tmp-')) {
            actionsHtml = `
                <div class="message-actions">
                    <button class="msg-action-btn" onclick="editMyMessage('${msg.id}')" title="แก้ไข">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="msg-action-btn" onclick="deleteMyMessage('${msg.id}')" title="ลบ">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>`;
        }

        const isTemp = String(msg.id).startsWith('tmp-');
        const readStatusText = isTemp ? 'กำลังส่ง...' : formatReadStatus(msg.read_at, msg.is_read, msg.read_status);

        if (isMine) {
            document.querySelectorAll('.msg-read-status').forEach(el => el.remove());
        }

        wrapper.innerHTML = `
            ${!isMine ? `<div class="message-avatar">${avatarHtml}</div>` : ''}
            ${actionsHtml}
            <div class="message-content">
                <div class="message-info">${label}</div>
                <div class="message-bubble" id="bubble-${msg.id}">
                    ${safeMessage ? `<div class="msg-text-body">${safeMessage}</div>` : ''}
                    ${msg.is_edited ? '<span class="edit-badge" style="font-size:0.65rem;opacity:0.8;margin-left:4px;">(แก้ไขแล้ว)</span>' : ''}
                    ${attachmentsHtml}
                </div>
                <div style="display:flex;align-items:center;gap:0.35rem;margin-top:0.25rem;">
                    <span class="message-time">${timeStr}</span>
                    ${isMine ? `<span id="status-${msg.id}" class="msg-read-status" style="font-size:0.65rem;color:${isTemp ? '#94a3b8' : '#ea580c'};">${readStatusText}</span>` : ''}
                </div>
            </div>
        `;

        const isNearBottom = chatWindow.scrollHeight - chatWindow.scrollTop - chatWindow.clientHeight < 120;
        chatWindow.appendChild(wrapper);

        if (isNearBottom || isMine) {
            scrollBottom(true);
        } else {
            scrollBtn.style.display = 'flex';
        }
    }

    // Submit Handler (Optimistic UI)
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = msgInput.value.trim();

        if (isEditingId) {
            // Edit Mode
            if (!text) return;
            try {
                const res = await window.axios.put('/chat/messages/' + isEditingId, { message: text });
                if (res.data.success) {
                    const bubble = document.getElementById('bubble-' + isEditingId);
                    if (bubble) {
                        const bodyEl = bubble.querySelector('.msg-text-body');
                        if (bodyEl) bodyEl.innerHTML = linkify(text, true);
                        if (!bubble.querySelector('.edit-badge')) {
                            bubble.insertAdjacentHTML('beforeend', '<span class="edit-badge" style="font-size:0.65rem;opacity:0.8;margin-left:4px;">(แก้ไขแล้ว)</span>');
                        }
                    }
                }
            } catch(err) {
                alert('ไม่สามารถแก้ไขข้อความได้');
            } finally {
                cancelEditMode();
            }
            return;
        }

        if (!text && fileInput.files.length === 0) return;

        sendBtn.disabled = true;
        const formData = new FormData(chatForm);

        // Optimistic UI render
        const tempId = 'tmp-' + Date.now();
        const optimisticMsg = {
            id: tempId,
            message: text,
            user_id: USER_ID,
            attachments: [],
            created_at: new Date().toISOString(),
            user: { id: USER_ID, name: 'คุณ' }
        };

        if (fileInput.files.length > 0) {
            Array.from(fileInput.files).forEach(f => {
                optimisticMsg.attachments.push({
                    original_name: f.name,
                    url: URL.createObjectURL(f),
                    is_image: f.type.startsWith('image/'),
                    mime_type: f.type
                });
            });
        }

        renderMessage(optimisticMsg, true);
        const optEl = document.getElementById('cm-' + tempId);
        if (optEl) optEl.style.opacity = '0.7';

        msgInput.value = '';
        msgInput.style.height = 'auto';
        fileInput.value = '';
        attachPrev.innerHTML = '';
        attachPrev.style.display = 'none';

        try {
            const response = await window.axios.post(sendUrl, formData, {
                headers: { 'Accept': 'application/json' }
            });
            if (response.data.success) {
                if (optEl) optEl.remove();
                renderMessage(response.data.message, true);
            }
        } catch (err) {
            if (optEl) {
                optEl.style.opacity = '1';
                const statusSpan = document.getElementById('status-' + tempId);
                if (statusSpan) { statusSpan.textContent = 'ส่งไม่สำเร็จ'; statusSpan.style.color = '#ef4444'; }
            }
            alert('ไม่สามารถส่งข้อความได้ กรุณาลองใหม่อีกครั้ง');
        } finally {
            sendBtn.disabled = false;
        }
    });

    // Delete message
    window.deleteMyMessage = async function(id) {
        if (!confirm('ยืนยันลบข้อความนี้?')) return;
        try {
            const res = await window.axios.delete('/chat/messages/' + id);
            if (res.data.success) {
                const el = document.getElementById('cm-' + id);
                if (el) {
                    el.style.transition = 'all 0.2s ease-out';
                    el.style.opacity = '0';
                    el.style.transform = 'scale(0.95)';
                    setTimeout(() => el.remove(), 200);
                }
            }
        } catch(e) {
            alert('ไม่สามารถลบข้อความได้');
        }
    };

    // Edit message
    window.editMyMessage = function(id) {
        const bubble = document.getElementById('bubble-' + id);
        if (!bubble) return;
        const textEl = bubble.querySelector('.msg-text-body');
        if (!textEl) return;

        isEditingId = id;
        msgInput.value = textEl.textContent.trim();
        msgInput.focus();
        sendBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        sendBtn.style.background = '#10b981';
    };

    function cancelEditMode() {
        isEditingId = null;
        msgInput.value = '';
        msgInput.style.height = 'auto';
        sendBtn.innerHTML = '<svg style="width:18px;height:18px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        sendBtn.style.background = 'var(--chat-primary)';
    }

    // Real-time Laravel Echo + Reverb
    const initEcho = () => {
        if (window.Echo) {
            // 1. Private Room Channel
            window.Echo.private('chat.room.' + roomID)
                .listen('.MessageSent', (data) => {
                    if (data.user && String(data.user.id) === String(USER_ID)) return;
                    if (!document.getElementById('cm-' + data.id)) {
                        playChime();
                        renderMessage(data, false);
                        window.axios.post(readUrl);
                    }
                })
                .listen('.MessageEdited', (data) => {
                    const bubble = document.getElementById('bubble-' + data.id);
                    if (bubble) {
                        const bodyEl = bubble.querySelector('.msg-text-body');
                        if (bodyEl) bodyEl.innerHTML = linkify(data.message || data.body, false);
                        if (!bubble.querySelector('.edit-badge')) {
                            bubble.insertAdjacentHTML('beforeend', '<span class="edit-badge" style="font-size:0.65rem;opacity:0.8;margin-left:4px;">(แก้ไขแล้ว)</span>');
                        }
                    }
                })
                .listen('.MessageDeleted', (data) => {
                    const el = document.getElementById('cm-' + data.id);
                    if (el) {
                        el.style.transition = 'all 0.2s';
                        el.style.opacity = '0';
                        setTimeout(() => el.remove(), 200);
                    }
                })
                .listen('.ChatDeleted', () => {
                    alert('ห้องแชทนี้ถูกปิดโดยผู้ดูแล');
                    window.location.href = '{{ route("jobs.index") }}';
                })
                .listenForWhisper('typing', (e) => {
                    if (e.userId == USER_ID) return;
                    typingIndicator.style.display = 'flex';
                    clearTimeout(window.typingTimer);
                    window.typingTimer = setTimeout(() => { typingIndicator.style.display = 'none'; }, 2800);
                });

            // 1.1 Personal Student Channel (Ironclad fallback)
            window.Echo.private('chat.student.' + USER_ID)
                .listen('.MessageSent', (data) => {
                    if (data.user && String(data.user.id) === String(USER_ID)) return;
                    if (String(data.room_id) === String(roomID) || (data.room && String(data.room.id) === String(roomID))) {
                        if (!document.getElementById('cm-' + data.id)) {
                            playChime();
                            renderMessage(data, false);
                            window.axios.post(readUrl);
                        }
                    }
                });

            // Whisper typing emit
            msgInput.addEventListener('input', () => {
                window.Echo.private('chat.room.' + roomID)
                    .whisper('typing', { userId: USER_ID, name: 'นักศึกษา' });
            });

            // 2. Presence Channel 'online'
            window.Echo.join('online')
                .here((users) => {
                    const hasStaff = users.some(u => u.role === 'admin' || u.role === 'staff');
                    updateOnlineStatus(hasStaff);
                })
                .joining((u) => {
                    if (u.role === 'admin' || u.role === 'staff') updateOnlineStatus(true);
                })
                .leaving((u) => {
                    // Check remaining
                });

            function updateOnlineStatus(isOnline) {
                const dot = document.getElementById('staffOnlineDot');
                const label = document.getElementById('onlineStatusLabel');
                if (dot) dot.style.display = isOnline ? 'inline-block' : 'none';
                if (label) {
                    label.innerHTML = isOnline 
                        ? '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#10b981;margin-right:4px;"></span>ออนไลน์' 
                        : '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;margin-right:4px;"></span>ออฟไลน์';
                }
            }
        } else {
            setTimeout(initEcho, 200);
        }
    };
    initEcho();
});
</script>
@endsection
