{{-- Admin: หน้าแชทกับนักศึกษาคนหนึ่ง (จาก Inbox) --}}
@extends('layouts.admin')
@section('title', ($student->full_name ?? 'นักศึกษา') . ' — ' . ($job->title ?? 'กล่องข้อความ'))

@section('content')
    @if(request('widget'))
    <style>
        html, body { background: #fff !important; overflow: hidden !important; height: 100vh !important; margin: 0 !important; padding: 0 !important; }
        .sb-sidebar, .sb-topbar, .admin-mobile-header, .admin-bottom-nav, .sb-footer, .chat-header-container { display: none !important; }
        .sb-content { margin-left: 0 !important; padding-top: 0 !important; height: 100vh !important; width: 100% !important; }
        .sb-main { padding: 0 !important; height: 100vh !important; max-width: 100% !important; display: flex !important; flex-direction: column !important; margin: 0 !important; }
        #chatWindow { flex: 1 !important; height: 0 !important; border: none !important; border-radius: 0 !important; margin: 0 !important; }
        form#chatForm { padding: 0.6rem 0.75rem; background: #fff; border-top: 1px solid #e2e8f0; flex-shrink: 0; }
        @media (prefers-color-scheme: dark) {
            html, body, form#chatForm { background: #202124 !important; border-top-color: #36383a !important; }
            #chatWindow { background: #202124 !important; }
        }
    </style>
    @endif

    <style>
        :root {
            --chat-primary: #ea580c;
            --chat-primary-hover: #c2410c;
        }

        .chat-header-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .chat-link {
            color: #dc2626 !important;
            text-decoration: underline !important;
            text-underline-offset: 3px;
            word-break: break-all;
            font-weight: 600;
            cursor: pointer;
        }
        .chat-link-mine {
            color: #ea580c !important;
            background: #ffffff;
            padding: 1px 6px;
            border-radius: 6px;
            display: inline-block;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            text-decoration: underline !important;
        }

        .msg-bubble-container {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
            position: relative;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .msg-bubble-mine {
            flex-direction: row-reverse;
        }

        .msg-actions {
            display: none;
            align-items: center;
            gap: 0.25rem;
            position: absolute;
            top: -12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2px 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            z-index: 10;
        }
        .msg-bubble-mine .msg-actions { right: 8px; }
        .msg-bubble-theirs .msg-actions { left: 36px; }
        .msg-bubble-container:hover .msg-actions { display: flex; }

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
            max-height: 260px;
            object-fit: contain;
            border-radius: 8px;
            margin-top: 0.35rem;
            cursor: pointer;
            transition: transform 0.15s;
        }
        .attachment-img:hover { transform: scale(1.02); }

        .attachment-file {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.35rem;
            padding: 0.4rem 0.65rem;
            background: rgba(0,0,0,0.06);
            border-radius: 6px;
            text-decoration: none;
            color: inherit;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .scroll-bottom-badge {
            position: absolute;
            bottom: 16px;
            right: 24px;
            background: #ea580c;
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

    {{-- Header --}}
    <div class="chat-header-card chat-header-container">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <a href="{{ route('admin.inbox.index') }}" style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#f1f5f9;color:#64748b;text-decoration:none;" title="กลับไปกล่องข้อความ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </a>
            <div>
                <h2 style="margin:0;font-size:1.05rem;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:.4rem;">
                    <span>{{ $student->full_name }}</span>
                    <span id="adminOnlineDot" style="display:none;width:8px;height:8px;background:#10b981;border-radius:50%;box-shadow:0 0 0 2px #fff;" title="ออนไลน์"></span>
                    <span style="font-size:.85rem;color:#ea580c;font-weight:500;">[{{ $job->title }}]</span>
                </h2>
                <div style="font-size:0.75rem;color:#64748b;">
                    รหัสนักศึกษา: {{ $student->student_id ?? '-' }} | คณะ: {{ $student->faculty ?? '-' }}
                </div>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span id="studentOnlineLabel" style="font-size:0.75rem;color:#64748b;font-weight:500;">
                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;margin-right:4px;"></span>ออฟไลน์
            </span>
            <button onclick="deleteChat()" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:0.4rem;border-radius:8px;display:flex;align-items:center;justify-content:center;" title="ลบการสนทนานี้">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Chat window --}}
    <div id="chatWindow" style="height:480px;overflow-y:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;display:flex;flex-direction:column;gap:.65rem;margin-bottom:.75rem;position:relative;">
        @php
            $studentPivot = $room->users->firstWhere('id', $student->id);
            $studentReadAtStr = $studentPivot?->pivot?->last_read_at ?? null;
            $studentReadAt = $studentReadAtStr ? \Carbon\Carbon::parse($studentReadAtStr) : null;
            $lastMineMsgId = $messages->where('user_id', auth()->id())->last()?->id;
        @endphp
        @forelse($messages as $msg)
            @php
                $isMine   = $msg->user_id == auth()->id();
                $label    = $isMine ? 'คุณ' : ($msg->user?->full_name ?? $student->full_name);
                $photoUrl = $msg->user?->profile_photo ? asset('storage/' . $msg->user->profile_photo) : null;
                $initial  = mb_strtoupper(mb_substr($label, 0, 1));
                $avatarBg = $isMine ? '#ea580c' : '#64748b';

                $readStatusText = '✓ ส่งแล้ว';
                if ($isMine && $studentReadAt && $msg->created_at && $studentReadAt->gte($msg->created_at)) {
                    $diffSec = max(0, now()->diffInSeconds($studentReadAt));
                    $diffMin = max(0, now()->diffInMinutes($studentReadAt));
                    $diffHours = max(0, now()->diffInHours($studentReadAt));
                    if ($diffSec < 90) {
                        $readStatusText = '✓✓ เพิ่งอ่าน';
                    } elseif ($diffMin < 60) {
                        $readStatusText = "✓✓ เห็นเมื่อ {$diffMin} นาทีที่แล้ว";
                    } elseif ($diffHours < 24) {
                        $readStatusText = "✓✓ เห็นเมื่อ {$diffHours} ชม. ที่แล้ว";
                    } else {
                        $readStatusText = "✓✓ เห็นเมื่อ " . $studentReadAt->format('d/m H:i');
                    }
                }
            @endphp
            <div id="cm-{{ $msg->id }}" class="msg-bubble-container {{ $isMine ? 'msg-bubble-mine' : 'msg-bubble-theirs' }}">
                {{-- Avatar --}}
                @if(!$isMine)
                <div style="position:relative;flex-shrink:0;">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $label }}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                    @else
                        <div style="width:30px;height:30px;border-radius:50%;background:{{ $avatarBg }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;">{{ $initial }}</div>
                    @endif
                </div>
                @endif

                {{-- Actions --}}
                @if($isMine)
                <div class="msg-actions">
                    <button class="msg-action-btn" onclick="editAdminMessage('{{ $msg->id }}')" title="แก้ไข">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="msg-action-btn" onclick="deleteAdminMessage('{{ $msg->id }}')" title="ลบ">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>
                @endif

                {{-- Bubble column --}}
                <div style="display:flex;flex-direction:column;align-items:{{ $isMine ? 'flex-end' : 'flex-start' }};max-width:72%;">
                    <span style="font-size:.68rem;color:#94a3b8;margin-bottom:.15rem;">{{ $label }}</span>
                    @php
                        $hasText = !empty($msg->body);
                        $bg = $isMine ? '#ea580c' : '#ffffff';
                        $pad = '.55rem .85rem';
                        $shadow = '0 1px 3px rgba(0,0,0,.04)';
                    @endphp
                    <div id="bubble-{{ $msg->id }}" style="padding:{{$pad}};border-radius:{{ $isMine ? '16px 4px 16px 16px' : '4px 16px 16px 16px' }};background:{{$bg}};color:{{ $isMine ? '#fff' : '#1e293b' }};font-size:.9rem;box-shadow:{{$shadow}};word-break:break-word;border:{{ $isMine ? 'none' : '1px solid #e2e8f0' }};">
                        @if($msg->body)
                            @php
                                $linkClass = $isMine ? 'chat-link chat-link-mine' : 'chat-link';
                                $formattedBody = preg_replace('~(https?://[^\s<]+[^<.,:;"\')\]\s])~i', '<a href="$1" target="_blank" rel="noopener noreferrer" class="' . $linkClass . '">$1</a>', e($msg->body));
                            @endphp
                            <div class="msg-text-body">{!! $formattedBody !!}</div>
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
                                <img src="{{ $url }}" alt="image" class="attachment-img" onclick="openLightbox('{{ $url }}')">
                            @else
                                <a href="{{ $url }}" target="_blank" download="{{ $att['original_name'] ?? 'file' }}" class="attachment-file">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                                    {{ $att['original_name'] ?? 'ไฟล์แนบ' }}
                                </a>
                            @endif
                        @endforeach
                    </div>

                    <div style="display:flex;align-items:center;gap:0.35rem;margin-top:0.25rem;">
                        <span style="font-size:.65rem;color:#94a3b8;">{{ $msg->created_at?->format('H:i') }}</span>
                        @if($isMine && $msg->id == $lastMineMsgId)
                            <span id="status-{{ $msg->id }}" class="admin-msg-read-status" @if($studentReadAt) data-read-at="{{ $studentReadAt->toISOString() }}" @endif style="font-size:0.65rem;color:#ea580c;">{{ $readStatusText }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div id="noMsg" style="margin:auto;text-align:center;color:#94a3b8;font-size:.9rem;">
                <svg style="width:40px;height:40px;margin:0 auto .5rem;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                ยังไม่มีประวัติการสนทนา เริ่มคุยกับนักศึกษาได้ทันที
            </div>
        @endforelse

        {{-- Floating new message indicator --}}
        <div id="scrollBottomBtn" class="scroll-bottom-badge" onclick="scrollBottom(true)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
            ข้อความใหม่
        </div>
    </div>

    {{-- Typing bar --}}
    <div id="adminTypingBar" style="display:none;font-size:.75rem;color:#ea580c;padding:0 .5rem;margin-bottom:.4rem;font-style:italic;">
        <span>{{ $student->full_name }} กำลังพิมพ์...</span>
    </div>

    {{-- Form --}}
    <form id="chatForm" enctype="multipart/form-data" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:0.75rem 1rem;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
        @csrf
        <div id="attachPreview" style="display:none;gap:.4rem;flex-wrap:wrap;margin-bottom:.5rem;"></div>
        
        <div style="display:flex;align-items:flex-end;gap:.5rem;">
            <label style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;color:#64748b;" title="แนบไฟล์ (หรือวางภาพ Ctrl+V)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                <input type="file" id="fileInput" name="attachments[]" multiple style="display:none;">
            </label>

            <textarea id="msgInput" name="message" rows="1" style="flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:.65rem .85rem;font-size:.92rem;resize:none;outline:none;max-height:120px;line-height:1.4;" placeholder="พิมพ์ข้อความ..."></textarea>

            <button type="submit" id="sendBtn" style="width:40px;height:40px;background:#ea580c;color:#fff;border:none;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;" title="ส่งข้อความ">
                <svg style="width:18px;height:18px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </form>

    {{-- Lightbox Modal --}}
    <div id="chatLightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;" onclick="this.style.display='none'">
        <img id="lightboxImg" src="" style="max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const win          = document.getElementById('chatWindow');
    const form         = document.getElementById('chatForm');
    const input        = document.getElementById('msgInput');
    const fileIn       = document.getElementById('fileInput');
    const prev         = document.getElementById('attachPreview');
    const btn          = document.getElementById('sendBtn');
    const scrollBtn    = document.getElementById('scrollBottomBtn');

    const myId         = {{ (int) auth()->id() }};
    const studentId    = {{ (int) $student->id }};
    const roomID       = '{{ $room->id }}';
    const sendUrl      = '{{ route("admin.inbox.send", [$job->id, $student->id]) }}';
    const readUrl      = '{{ route("admin.inbox.read", [$job->id, $student->id]) }}';

    let isEditingId    = null;

    // Web Audio soft chime
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15);
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
            win.scrollTo({ top: win.scrollHeight, behavior: 'smooth' });
        } else {
            win.scrollTop = win.scrollHeight;
        }
        scrollBtn.style.display = 'none';
    }

    win.addEventListener('scroll', () => {
        const isNearBottom = win.scrollHeight - win.scrollTop - win.clientHeight < 80;
        if (isNearBottom) scrollBtn.style.display = 'none';
    });

    scrollBottom();

    // Auto-expand textarea
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });

    // Paste image
    document.addEventListener('paste', (e) => {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (let item of items) {
            if (item.type.indexOf('image') === 0) {
                const blob = item.getAsFile();
                const dataTransfer = new DataTransfer();
                if (fileIn.files.length > 0) {
                    Array.from(fileIn.files).forEach(f => dataTransfer.items.add(f));
                }
                dataTransfer.items.add(blob);
                fileIn.files = dataTransfer.files;
                fileIn.dispatchEvent(new Event('change'));
            }
        }
    });

    fileIn.addEventListener('change', () => {
        prev.innerHTML = '';
        if (fileIn.files.length > 0) {
            prev.style.display = 'flex';
            Array.from(fileIn.files).forEach(f => {
                const item = document.createElement('div');
                item.style.cssText = 'padding:0.3rem 0.65rem; background:#fff7ed; border:1px solid #fed7aa; border-radius:16px; font-size:0.75rem; color:#ea580c; display:flex; align-items:center; gap:4px;';
                item.innerHTML = `
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                    ${f.name.length > 18 ? f.name.substring(0, 18) + '...' : f.name}
                `;
                prev.appendChild(item);
            });
        } else {
            prev.style.display = 'none';
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

    function formatReadStatus(readAt, isRead) {
        if (!readAt && !isRead) return '✓ ส่งแล้ว';
        if (!readAt) return '✓✓ เพิ่งอ่าน';

        const readTime = new Date(readAt);
        if (isNaN(readTime.getTime())) return '✓✓ เพิ่งอ่าน';

        const now = new Date();
        const diffSec = Math.max(0, Math.floor((now.getTime() - readTime.getTime()) / 1000));
        const diffMin = Math.floor(diffSec / 60);
        const diffHours = Math.floor(diffMin / 60);

        if (diffSec < 60) {
            return '✓✓ เพิ่งอ่าน';
        } else if (diffMin < 60) {
            return `✓✓ เห็นเมื่อ ${diffMin} นาทีที่แล้ว`;
        } else if (diffHours < 24) {
            return `✓✓ เห็นเมื่อ ${diffHours} ชม. ที่แล้ว`;
        } else {
            return `✓✓ เห็นเมื่อ ${readTime.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })}`;
        }
    }

    // Live Dynamic Ticker
    setInterval(function() {
        document.querySelectorAll('.admin-msg-read-status[data-read-at]').forEach(function(el) {
            const readAt = el.getAttribute('data-read-at');
            if (readAt) {
                el.textContent = formatReadStatus(readAt, true);
            }
        });
    }, 10000);

    function renderMessage(msg, isMine) {
        const noMsg = document.getElementById('noMsg');
        if (noMsg) noMsg.remove();

        const label = isMine ? 'คุณ' : (msg.user?.name || '{{ $student->full_name }}');
        const photo = msg.user?.photo || null;

        const wrapper = document.createElement('div');
        wrapper.id = 'cm-' + msg.id;
        wrapper.className = `msg-bubble-container ${isMine ? 'msg-bubble-mine' : 'msg-bubble-theirs'}`;

        let avatarHtml = '';
        if (!isMine) {
            if (photo) {
                avatarHtml = `<img src="${photo}" alt="" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">`;
            } else {
                avatarHtml = `<div style="width:30px;height:30px;border-radius:50%;background:#64748b;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;">${label.charAt(0).toUpperCase()}</div>`;
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
                <div class="msg-actions">
                    <button class="msg-action-btn" onclick="editAdminMessage('${msg.id}')" title="แก้ไข">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="msg-action-btn" onclick="deleteAdminMessage('${msg.id}')" title="ลบ">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>`;
        }

        const isTemp = String(msg.id).startsWith('tmp-');
        const readStatusText = isTemp ? 'กำลังส่ง...' : formatReadStatus(msg.read_at, msg.is_read);

        if (isMine) {
            document.querySelectorAll('.admin-msg-read-status').forEach(el => el.remove());
        }

        const dataReadAtAttr = msg.read_at ? `data-read-at="${msg.read_at}"` : '';

        wrapper.innerHTML = `
            ${!isMine ? `<div style="position:relative;flex-shrink:0;">${avatarHtml}</div>` : ''}
            ${actionsHtml}
            <div style="display:flex;flex-direction:column;align-items:${isMine ? 'flex-end' : 'flex-start'};max-width:72%;">
                <span style="font-size:.68rem;color:#94a3b8;margin-bottom:.15rem;">${label}</span>
                <div id="bubble-${msg.id}" style="padding:.55rem .85rem;border-radius:${isMine ? '16px 4px 16px 16px' : '4px 16px 16px 16px'};background:${isMine ? '#ea580c' : '#ffffff'};color:${isMine ? '#fff' : '#1e293b'};font-size:.9rem;box-shadow:0 1px 3px rgba(0,0,0,.04);word-break:break-word;border:${isMine ? 'none' : '1px solid #e2e8f0'};">
                    ${safeMessage ? `<div class="msg-text-body">${safeMessage}</div>` : ''}
                    ${msg.is_edited ? '<span class="edit-badge" style="font-size:0.65rem;opacity:0.8;margin-left:4px;">(แก้ไขแล้ว)</span>' : ''}
                    ${attachmentsHtml}
                </div>
                <div style="display:flex;align-items:center;gap:0.35rem;margin-top:0.25rem;">
                    <span style="font-size:.65rem;color:#94a3b8;">${timeStr}</span>
                    ${isMine ? `<span id="status-${msg.id}" class="admin-msg-read-status" ${dataReadAtAttr} style="font-size:0.65rem;color:${isTemp ? '#94a3b8' : '#ea580c'};">${readStatusText}</span>` : ''}
                </div>
            </div>
        `;

        const isNearBottom = win.scrollHeight - win.scrollTop - win.clientHeight < 120;
        win.appendChild(wrapper);

        if (isNearBottom || isMine) {
            scrollBottom(true);
        } else {
            scrollBtn.style.display = 'flex';
        }
    }

    // Submit Handler
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();

        if (isEditingId) {
            if (!text) return;
            try {
                const res = await window.axios.put('/admin/inbox/messages/' + isEditingId, { message: text });
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

        if (!text && fileIn.files.length === 0) return;

        btn.disabled = true;
        const formData = new FormData(form);

        // Optimistic UI
        const tempId = 'tmp-' + Date.now();
        const optimisticMsg = {
            id: tempId,
            message: text,
            user_id: myId,
            attachments: [],
            created_at: new Date().toISOString(),
            user: { id: myId, name: 'คุณ' }
        };

        if (fileIn.files.length > 0) {
            Array.from(fileIn.files).forEach(f => {
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

        input.value = '';
        input.style.height = 'auto';
        fileIn.value = '';
        prev.innerHTML = '';
        prev.style.display = 'none';

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
            btn.disabled = false;
        }
    });

    // Delete message
    window.deleteAdminMessage = async function(id) {
        if (!confirm('ต้องการลบข้อความนี้ใช่หรือไม่?')) return;
        try {
            const res = await window.axios.delete('/admin/inbox/messages/' + id);
            if (res.data.success) {
                const el = document.getElementById('cm-' + id);
                if (el) {
                    el.style.transition = 'all 0.2s ease-out';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 200);
                }
            }
        } catch(e) {
            alert('ไม่สามารถลบข้อความได้');
        }
    };

    // Edit message
    window.editAdminMessage = function(id) {
        const bubble = document.getElementById('bubble-' + id);
        if (!bubble) return;
        const textEl = bubble.querySelector('.msg-text-body');
        if (!textEl) return;

        isEditingId = id;
        input.value = textEl.textContent.trim();
        input.focus();
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        btn.style.background = '#10b981';
    };

    function cancelEditMode() {
        isEditingId = null;
        input.value = '';
        input.style.height = 'auto';
        btn.innerHTML = '<svg style="width:18px;height:18px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        btn.style.background = '#ea580c';
    }

    // Delete chat room
    window.deleteChat = function() {
        if (!confirm('ยืนยันลบห้องแชทนี้และข้อความทั้งหมด? (นักศึกษาจะมองไม่เห็นแชทนี้อีก)')) return;
        window.axios.delete('{{ route("admin.inbox.delete", [$job->id, $student->id]) }}')
            .then(res => {
                if (res.data.success) {
                    window.location.href = '{{ route("admin.inbox.index") }}';
                }
            });
    };

    // Real-time Laravel Echo + Reverb
    const initEcho = () => {
        if (window.Echo) {
            window.Echo.private('chat.room.' + roomID)
                .listen('.MessageSent', function (msg) {
                    if (msg.user && msg.user.id == myId) return;
                    if (!document.getElementById('cm-' + msg.id)) {
                        playChime();
                        renderMessage(msg, false);
                        window.axios.post(readUrl);
                    }
                })
                .listen('.MessagesRead', function (data) {
                    if (String(data.reader_id) === String(myId)) return;
                    const readAt = data.read_at || new Date().toISOString();
                    const statusEls = document.querySelectorAll('.admin-msg-read-status');
                    statusEls.forEach(el => {
                        el.setAttribute('data-read-at', readAt);
                        el.textContent = formatReadStatus(readAt, true);
                        el.style.color = '#10b981';
                        setTimeout(() => { el.style.color = '#ea580c'; }, 2000);
                    });
                })
                .listen('.MessageDeleted', function (e) {
                    const el = document.getElementById('cm-' + e.id);
                    if (el) {
                        el.style.transition = 'all 0.2s';
                        el.style.opacity = '0';
                        setTimeout(() => el.remove(), 200);
                    }
                })
                .listen('.MessageEdited', function (e) {
                    const bubble = document.getElementById('bubble-' + e.id);
                    if (bubble) {
                        const bodyEl = bubble.querySelector('.msg-text-body');
                        if (bodyEl) bodyEl.innerHTML = linkify(e.message, false);
                        if (!bubble.querySelector('.edit-badge')) {
                            bubble.insertAdjacentHTML('beforeend', '<span class="edit-badge" style="font-size:0.65rem;opacity:0.8;margin-left:4px;">(แก้ไขแล้ว)</span>');
                        }
                    }
                })
                .listenForWhisper('typing', function(e) {
                    if (e.userId == myId) return;
                    const bar = document.getElementById('adminTypingBar');
                    if (bar) {
                        bar.style.display = 'block';
                        clearTimeout(window.adminTypingTimer);
                        window.adminTypingTimer = setTimeout(() => bar.style.display = 'none', 2800);
                    }
                });

            // 1.1 Admin Inbox Channel for MessagesRead
            window.Echo.private('admin.inbox')
                .listen('.MessagesRead', function (data) {
                    if (String(data.reader_id) === String(myId)) return;
                    if (String(data.room_id) === String(roomID)) {
                        const readAt = data.read_at || new Date().toISOString();
                        const statusEls = document.querySelectorAll('.admin-msg-read-status');
                        statusEls.forEach(el => {
                            el.setAttribute('data-read-at', readAt);
                            el.textContent = formatReadStatus(readAt, true);
                        });
                    }
                });

            // Typing emit
            input.addEventListener('input', function() {
                window.Echo.private('chat.room.' + roomID)
                    .whisper('typing', { userId: myId, name: 'ผู้ดูแล' });
            });

            // Presence channel — student online status
            window.Echo.join('online')
                .here((users) => {
                    const isOnline = users.some(u => String(u.id) === String(studentId));
                    toggleStudentOnline(isOnline);
                })
                .joining((user) => {
                    if (String(user.id) === String(studentId)) toggleStudentOnline(true);
                })
                .leaving((user) => {
                    if (String(user.id) === String(studentId)) toggleStudentOnline(false);
                });

            function toggleStudentOnline(isOnline) {
                const headerDot = document.getElementById('adminOnlineDot');
                const label = document.getElementById('studentOnlineLabel');
                if (headerDot) headerDot.style.display = isOnline ? 'inline-block' : 'none';
                if (label) {
                    label.innerHTML = isOnline 
                        ? '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#10b981;margin-right:4px;"></span>ออนไลน์' 
                        : '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;margin-right:4px;"></span>เพิ่งออนไลน์';
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
