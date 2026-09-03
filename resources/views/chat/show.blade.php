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
        width: 34px;
        height: 34px;
        border-radius: 8px;
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
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }

    .chat-window::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }

    .date-separator {
        align-self: center;
        background: rgba(100, 116, 139, 0.12);
        border-radius: 999px;
        padding: 0.25rem 0.95rem;
        margin: 0.75rem 0 0.35rem;
        color: var(--chat-text-muted);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .date-separator::before,
    .date-separator::after {
        display: none;
    }

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
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        color: var(--chat-bubble-text-mine);
        border-radius: 16px 4px 16px 16px;
        border: none;
        box-shadow: 0 2px 8px rgba(234, 88, 12, 0.3);
    }

    .message-theirs .message-bubble {
        background: var(--chat-bubble-theirs);
        color: var(--chat-bubble-text-theirs);
        border-radius: 4px 16px 16px 16px;
        border: 1px solid var(--chat-border);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
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
        padding: 0.75rem 1rem;
        border-radius: 14px;
        margin-top: 0.75rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
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
        border-radius: 50%;
        cursor: pointer;
        color: var(--chat-text-muted);
        transition: border-color 0.15s, color 0.15s, background 0.15s;
    }
    .file-label:hover {
        border-color: #fdba74;
        background: #fff7ed;
        color: var(--chat-primary);
    }

    .chat-textarea {
        flex: 1;
        border: 1.5px solid transparent;
        border-radius: 22px;
        padding: 0.7rem 0.95rem;
        font-size: 0.92rem;
        resize: none;
        outline: none;
        max-height: 120px;
        line-height: 1.4;
        background: #f8fafc;
        transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
    }
    .chat-textarea:focus {
        background: #ffffff;
        border-color: var(--chat-primary);
        box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14);
    }

    .send-btn {
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        color: white;
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 3px 10px rgba(234, 88, 12, 0.38);
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.2s ease;
    }
    .send-btn:hover {
        transform: scale(1.07);
        box-shadow: 0 5px 14px rgba(234, 88, 12, 0.45);
    }
    .send-btn:active { transform: scale(0.95); }
    .send-btn:disabled { background: #cbd5e1; box-shadow: none; cursor: not-allowed; transform: none; }

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

    /* ── Admin-matching polish ── */
    .msg-read-status {
        padding: 1px 7px;
        border-radius: 9px;
        background: rgba(234, 88, 12, 0.08);
        font-weight: 600;
    }

    form#chatForm.editing {
        border-color: #10b981 !important;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    #noMsg {
        background: #ffffff;
        border: 1px dashed var(--chat-border);
        border-radius: 16px;
        padding: 1.5rem 2rem;
        margin: auto 1rem;
    }

    /* Native chat shell for tablets and phones */
    /* ══════════════════════════════════════════════
       NATIVE MESSENGER UI — tablets & phones
       ══════════════════════════════════════════════ */
    @media (max-width: 1024px) {
        body:has(.chat-container) {
            overflow: hidden;
            overscroll-behavior: none;
        }

        /* Chat owns the whole screen — hide app chrome */
        body:has(.chat-container) .navbar,
        body:has(.chat-container) .bottom-nav,
        body:has(.chat-container) #chatFloatWidget {
            display: none !important;
        }

        body:has(.chat-container) .container {
            width: 100%;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .chat-container {
            width: 100%;
            max-width: none;
            height: 100dvh;
            min-height: 0;
            padding: 0;
            margin: 0;
        }

        /* ── Native gradient header bar ── */
        .chat-header {
            position: sticky;
            top: 0;
            z-index: 40;
            flex-shrink: 0;
            margin: 0;
            padding: 0.5rem 0.65rem;
            border: none;
            border-radius: 0;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 55%, #c2410c 100%);
            box-shadow: 0 2px 8px rgba(194, 65, 12, 0.35);
        }

        .chat-header h2,
        .chat-header p {
            color: #fff !important;
        }

        .chat-header-info {
            gap: 0.6rem;
            min-width: 0;
        }

        .chat-header-info > div {
            min-width: 0;
        }

        .chat-back-btn {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            color: #fff !important;
        }

        .chat-back-btn:hover {
            background: rgba(255, 255, 255, 0.32);
            color: #fff;
        }

        .chat-header h2 {
            font-size: 0.95rem !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-header p {
            max-width: 52vw;
            font-size: 0.72rem !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            opacity: 0.92;
        }

        /* Online dots pop against the gradient */
        #staffOnlineDot {
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.9) !important;
        }

        .chat-header > div:last-child span {
            color: rgba(255, 255, 255, 0.88) !important;
        }

        .chat-header > div:last-child span span {
            background: #4ade80 !important;
        }

        /* ── Immersive chat canvas ── */
        .chat-window {
            flex: 1;
            min-height: 0;
            margin: 0;
            padding: 0.75rem 0.6rem 0.25rem;
            gap: 0.35rem;
            border: none;
            border-radius: 0;
            background:
                radial-gradient(circle at 15% 8%, rgba(249, 115, 22, 0.05), transparent 42%),
                radial-gradient(circle at 88% 92%, rgba(249, 115, 22, 0.04), transparent 42%),
                var(--chat-bg);
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }

        /* Date separator as a native pill */
        .date-separator {
            align-self: center;
            background: rgba(100, 116, 139, 0.12);
            border-radius: 999px;
            padding: 0.22rem 0.85rem;
            margin: 0.55rem 0 0.3rem;
            font-size: 0.68rem;
            color: var(--chat-text-muted);
        }

        .date-separator::before,
        .date-separator::after {
            display: none;
        }

        /* ── Bubbles ── */
        .message-wrapper {
            gap: 0.35rem;
        }

        .message-content {
            max-width: 84%;
        }

        .message-info {
            display: none;
        }

        .message-bubble {
            padding: 0.5rem 0.78rem;
            font-size: 0.9rem;
            line-height: 1.42;
            border-radius: 18px;
        }

        .message-mine .message-bubble {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border-bottom-right-radius: 6px;
            border: none;
            box-shadow: 0 2px 6px rgba(234, 88, 12, 0.28);
        }

        .message-theirs .message-bubble {
            border-bottom-left-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .message-avatar {
            width: 27px;
            height: 27px;
            font-size: 0.65rem;
        }

        .message-time {
            font-size: 0.62rem;
        }

        /* Actions: always visible below the bubble on touch */
        .message-actions {
            position: static !important;
            display: flex !important;
            flex-direction: column;
            gap: 0.2rem;
            margin-top: 0.2rem;
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
        }

        .message-mine .message-actions {
            align-items: center;
        }

        /* ── Floating pill composer ── */
        .input-area {
            position: sticky;
            bottom: 0;
            z-index: 40;
            flex-shrink: 0;
            margin: 0;
            padding: 0.45rem 0.55rem;
            border: none;
            border-radius: 0;
            background: #ffffff;
            border-top: 1px solid var(--chat-border);
            box-shadow: 0 -2px 10px rgba(15, 23, 42, 0.06);
        }

        .input-group {
            gap: 0.4rem;
        }

        .file-label,
        .send-btn {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 50%;
        }

        .file-label {
            background: #f1f5f9;
            border: none;
            color: #64748b;
        }

        .file-label:active {
            background: #e2e8f0;
        }

        .chat-textarea {
            min-height: 40px;
            max-height: 96px;
            border-radius: 20px;
            padding: 0.55rem 0.9rem;
            font-size: 0.9rem;
            background: #f8fafc;
            border: 1.5px solid transparent;
            transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
        }

        .chat-textarea:focus {
            background: #ffffff;
            border-color: #ea580c;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
        }

        .send-btn {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            box-shadow: 0 3px 8px rgba(234, 88, 12, 0.35);
        }

        .send-btn:active {
            transform: scale(0.92);
        }

        .send-btn:disabled {
            background: #cbd5e1;
            box-shadow: none;
        }

        .typing-bar {
            margin-top: 0.2rem;
            padding-left: 0.4rem;
        }

        .scroll-bottom-badge {
            right: 0.7rem;
            bottom: 0.7rem;
            padding: 0.4rem 0.7rem;
        }

        .attachment-img {
            max-height: 220px;
        }
    }

    @media (max-width: 640px) {
        .chat-header {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .chat-header p {
            max-width: 44vw;
        }

        .chat-window {
            padding: 0.6rem 0.5rem 0.2rem;
        }

        .message-content {
            max-width: 86%;
        }

        div.message-wrapper {
            gap: 0.3rem;
        }

        .message-avatar {
            width: 25px;
            height: 25px;
            font-size: 0.6rem;
        }

        .message-bubble {
            padding: 0.45rem 0.72rem;
            font-size: 0.88rem;
        }

        .attachment-img {
            max-height: 200px;
        }
    }

    @supports (padding-bottom: env(safe-area-inset-bottom)) {
        .input-area {
            padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
        }
    }

    /* ── Dark theme (all sizes) ── */
    html[data-theme="dark"] .chat-header {
        background: #18181b;
        border-color: #27272a;
    }

    html[data-theme="dark"] .chat-back-btn {
        background: #27272a;
        color: #a1a1aa;
    }

    html[data-theme="dark"] .chat-window {
        background: #121214;
        border-color: #27272a;
    }

    html[data-theme="dark"] .message-theirs .message-bubble {
        background: #27272a;
        color: #f4f4f5;
        border-color: #3f3f46;
    }

    html[data-theme="dark"] .input-area {
        background: #18181b;
        border-color: #27272a;
    }

    html[data-theme="dark"] .chat-textarea {
        background: #27272a;
        color: #f4f4f5;
    }

    html[data-theme="dark"] .chat-textarea:focus {
        background: #18181b;
        border-color: #f97316;
    }

    html[data-theme="dark"] .file-label {
        background: #27272a;
        border-color: #3f3f46;
        color: #a1a1aa;
    }

    html[data-theme="dark"] .file-label:hover {
        background: rgba(249, 115, 22, 0.1);
        border-color: #f97316;
        color: #fdba74;
    }

    html[data-theme="dark"] #noMsg {
        background: #1c1c1f;
        border-color: #3f3f46;
    }

    html[data-theme="dark"] .msg-read-status {
        background: rgba(249, 115, 22, 0.14);
    }

    /* ── Native dark theme on mobile/tablet ── */
    @media (max-width: 1024px) {
        html[data-theme="dark"] .chat-header {
            background: linear-gradient(135deg, #c2410c 0%, #9a3412 100%);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
        }

        html[data-theme="dark"] .chat-window {
            background:
                radial-gradient(circle at 15% 8%, rgba(249, 115, 22, 0.06), transparent 42%),
                radial-gradient(circle at 88% 92%, rgba(249, 115, 22, 0.05), transparent 42%),
                #121214;
        }

        html[data-theme="dark"] .date-separator {
            background: rgba(161, 161, 170, 0.16);
            color: #a1a1aa;
        }

        html[data-theme="dark"] .message-theirs .message-bubble {
            background: #27272a;
            color: #f4f4f5;
            border-color: #3f3f46;
        }

        html[data-theme="dark"] .input-area {
            background: #18181b;
            border-top-color: #27272a;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.35);
        }

        html[data-theme="dark"] .chat-textarea {
            background: #27272a;
            color: #f4f4f5;
        }

        html[data-theme="dark"] .chat-textarea:focus {
            background: #18181b;
            border-color: #f97316;
        }

        html[data-theme="dark"] .file-label {
            background: #27272a;
            color: #a1a1aa;
        }
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
        @php
            $staffLastSeen = $staffUser?->last_seen_at;
            $offlineText = 'ออฟไลน์';
            if ($staffLastSeen) {
                $diffMin = max(1, $staffLastSeen->diffInMinutes(now()));
                $diffHours = $staffLastSeen->diffInHours(now());
                if ($diffMin < 60) {
                    $offlineText = "ออนไลน์เมื่อ {$diffMin} นาทีที่แล้ว";
                } elseif ($diffHours < 24) {
                    $offlineText = "ออนไลน์เมื่อ {$diffHours} ชม. ที่แล้ว";
                } elseif ($staffLastSeen->isYesterday()) {
                    $offlineText = "ออนไลน์เมื่อวานนี้ " . $staffLastSeen->format('H:i');
                } else {
                    $offlineText = "ออนไลน์เมื่อ " . $staffLastSeen->format('d/m H:i');
                }
            }
        @endphp
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span id="onlineStatusLabel" data-last-seen="{{ $staffLastSeen?->toISOString() }}" style="font-size:0.75rem;color:var(--chat-text-muted);font-weight:500;">
                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;margin-right:4px;"></span>{{ $offlineText }}
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
            $lastMsg = $messages->last();
            $lastMineMsgId = ($lastMsg && $lastMsg->user_id == auth()->id()) ? $lastMsg->id : null;
        @endphp
        @forelse($messages as $msg)
            @php
                $msgDate = $msg->created_at?->format('Y-m-d');
                $isMine = $msg->user_id == auth()->id();
                $senderLabel = $isMine ? 'คุณ' : ($msg->user?->full_name ?? 'ผู้ดูแล');

                $readStatusText = 'ส่งแล้ว';
                if ($isMine && $otherReadAt && $msg->created_at && $otherReadAt->gte($msg->created_at)) {
                    $diffSec = max(0, now()->diffInSeconds($otherReadAt));
                    $diffMin = max(0, now()->diffInMinutes($otherReadAt));
                    $diffHours = max(0, now()->diffInHours($otherReadAt));
                    if ($diffSec < 60) {
                        $readStatusText = 'เพิ่งอ่าน';
                    } elseif ($diffMin < 60) {
                        $readStatusText = "อ่านเมื่อ {$diffMin} นาทีที่แล้ว";
                    } elseif ($diffHours < 24) {
                        $readStatusText = "อ่านเมื่อ {$diffHours} ชม. ที่แล้ว";
                    } else {
                        $readStatusText = "อ่านเมื่อ " . $otherReadAt->format('d/m H:i');
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
                <div class="message-avatar" style="position:relative;">
                    @if($msg->user?->profile_photo)
                        <img src="{{ asset('storage/' . $msg->user->profile_photo) }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    @else
                        {{ mb_strtoupper(mb_substr($senderLabel, 0, 1)) }}
                    @endif
                    <span class="staff-avatar-online-dot" style="display:none;position:absolute;bottom:-1px;right:-1px;width:9px;height:9px;background:#10b981;border:2px solid #fff;border-radius:50%;box-shadow:0 0 4px #10b981;" title="กำลังใช้งาน"></span>
                </div>
                @endif

                @if($isMine)
                <div class="message-actions">
                    @if($msg->id == $lastMsg?->id)
                    <button class="msg-action-btn msg-edit-btn" onclick="editMyMessage('{{ $msg->id }}')" title="แก้ไข">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    @endif
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
                            <span id="status-{{ $msg->id }}" class="msg-read-status" @if($otherReadAt) data-read-at="{{ $otherReadAt->toISOString() }}" @endif style="font-size:0.65rem;color:#ea580c;">{{ $readStatusText }}</span>
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
    const readStatusUrl = '{{ route("chat.read-status", $job->id) }}';
    const messagesUrl = '{{ route("chat.messages", $job->id) }}';
    
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

    function formatReadStatus(readAt, isRead) {
        if (!readAt && !isRead) return 'ส่งแล้ว';
        if (!readAt) return 'ส่งแล้ว';

        const readTime = new Date(readAt);
        if (isNaN(readTime.getTime())) return 'ส่งแล้ว';

        const now = new Date();
        const diffSec = Math.max(0, Math.floor((now.getTime() - readTime.getTime()) / 1000));
        const diffMin = Math.floor(diffSec / 60);
        const diffHours = Math.floor(diffMin / 60);

        if (diffSec < 60) {
            return 'เพิ่งอ่าน';
        } else if (diffMin < 60) {
            return `อ่านเมื่อ ${diffMin} นาทีที่แล้ว`;
        } else if (diffHours < 24) {
            return `อ่านเมื่อ ${diffHours} ชม. ที่แล้ว`;
        } else {
            return `อ่านเมื่อ ${readTime.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })}`;
        }
    }

    // Live Dynamic Ticker
    function updateAllReadStatuses() {
        document.querySelectorAll('.msg-read-status[data-read-at]').forEach(function(el) {
            const readAt = el.getAttribute('data-read-at');
            if (readAt) {
                el.textContent = formatReadStatus(readAt, true);
            }
        });
    }
    updateAllReadStatuses();
    setInterval(updateAllReadStatuses, 10000);

    // Newest read timestamp seen (guards against out-of-order events)
    let newestReadAtMs = 0;
    document.querySelectorAll('.msg-read-status[data-read-at]').forEach(el => {
        const t = new Date(el.getAttribute('data-read-at')).getTime();
        if (!isNaN(t) && t > newestReadAtMs) newestReadAtMs = t;
    });

    function applyReadUpdate(readAt) {
        const t = new Date(readAt).getTime();
        if (isNaN(t) || t <= newestReadAtMs) return; // ignore stale/duplicate events
        newestReadAtMs = t;
        document.querySelectorAll('.msg-read-status').forEach(el => {
            el.setAttribute('data-read-at', readAt);
            el.textContent = formatReadStatus(readAt, true);
            el.style.color = '#10b981';
            setTimeout(() => { el.style.color = '#ea580c'; }, 2000);
        });
    }

    // Polling fallback: if a WebSocket read event was missed, the status
    // self-heals within 5 seconds from the server's persisted value.
    function pollReadStatus() {
        window.axios.get(readStatusUrl)
            .then(res => {
                if (res.data.success && res.data.other_read_at) {
                    applyReadUpdate(res.data.other_read_at);
                }
            })
            .catch(() => {});
    }
    setInterval(pollReadStatus, 5000);

    // Message delivery fallback: merge any messages we don't have yet.
    // Keeps the chat alive when a WebSocket (Reverb) event is missed.
    window.lastMsgTs = null;
    function pollNewMessages() {
        window.axios.get(messagesUrl, { params: { after: window.lastMsgTs || '' } })
            .then(res => {
                const msgs = res.data.messages || [];
                if (!msgs.length) return;
                const last = msgs[msgs.length - 1];
                if (last.created_at) window.lastMsgTs = last.created_at;
                msgs.forEach(m => {
                    if (document.getElementById('cm-' + m.id)) return;
                    const mine = String(m.user_id) === String(USER_ID) || (m.user && String(m.user.id) === String(USER_ID));
                    const optEl = document.querySelector('.message-wrapper[id^="cm-tmp-"]');
                    if (mine && optEl) return; // optimistic send still in flight — will render on response
                    renderMessage(m, mine);
                    if (!mine) {
                        playChime();
                        window.axios.post(readUrl);
                    }
                });
            })
            .catch(() => {});
    }
    setInterval(pollNewMessages, 3000);
    // Seed the watermark from server history so the first poll doesn't replay old messages
    window.axios.get(messagesUrl).then(res => {
        const msgs = res.data.messages || [];
        if (msgs.length && msgs[msgs.length - 1].created_at) {
            window.lastMsgTs = msgs[msgs.length - 1].created_at;
        }
    }).catch(() => {});

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
            const dotDisplay = window.isStaffOnline ? 'block' : 'none';
            if (photo) {
                avatarHtml = `<img src="${photo}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
            } else {
                avatarHtml = label.charAt(0).toUpperCase();
            }
            avatarHtml += `<span class="staff-avatar-online-dot" style="display:${dotDisplay};position:absolute;bottom:-1px;right:-1px;width:9px;height:9px;background:#10b981;border:2px solid #fff;border-radius:50%;box-shadow:0 0 4px #10b981;" title="กำลังใช้งาน"></span>`;
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

        // Only the latest message can be edited — strip stale edit buttons
        document.querySelectorAll('.msg-edit-btn').forEach(b => b.remove());

        let actionsHtml = '';
        if (isMine && !String(msg.id).startsWith('tmp-')) {
            actionsHtml = `
                <div class="message-actions">
                    <button class="msg-action-btn msg-edit-btn" onclick="editMyMessage('${msg.id}')" title="แก้ไข">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="msg-action-btn" onclick="deleteMyMessage('${msg.id}')" title="ลบ">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>`;
        }

        const isTemp = String(msg.id).startsWith('tmp-');
        const readStatusText = isTemp ? 'กำลังส่ง...' : formatReadStatus(msg.read_at, msg.is_read);

        // Always clear previous read receipt so it disappears when other user replies
        document.querySelectorAll('.msg-read-status').forEach(el => el.remove());

        const dataReadAtAttr = msg.read_at ? `data-read-at="${msg.read_at}"` : '';

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
                    ${isMine ? `<span id="status-${msg.id}" class="msg-read-status" ${dataReadAtAttr} style="font-size:0.65rem;color:${isTemp ? '#94a3b8' : '#ea580c'};">${readStatusText}</span>` : ''}
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

        const formData = new FormData(chatForm);

        // Optimistic UI render — composer stays open for rapid-fire sending
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

        // Fetch the latest content from the server so editing always starts
        // from the current version, even if a real-time update was missed.
        window.axios.get('/chat/messages/' + id)
            .then(res => {
                if (!res.data.success) return;
                const latest = res.data.message.body ?? '';

                // Sync the DOM bubble so it matches what's being edited
                const bodyEl = bubble.querySelector('.msg-text-body');
                if (bodyEl) bodyEl.innerHTML = linkify(latest, true);

                isEditingId = id;
                msgInput.value = latest;
                msgInput.focus();
                msgInput.style.height = 'auto';
                msgInput.style.height = msgInput.scrollHeight + 'px';
                chatForm.classList.add('editing');
                sendBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                sendBtn.style.background = '#10b981';
            })
            .catch(err => alert(err?.response?.data?.message || 'ไม่สามารถโหลดข้อความล่าสุดได้'));
    };

    function cancelEditMode() {
        isEditingId = null;
        msgInput.value = '';
        msgInput.style.height = 'auto';
        chatForm.classList.remove('editing');
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
                .listen('.MessagesRead', (data) => {
                    if (String(data.reader_id) === String(USER_ID)) return;
                    applyReadUpdate(data.read_at || new Date().toISOString());
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
                })
                .listen('.MessagesRead', (data) => {
                    if (String(data.reader_id) === String(USER_ID)) return;
                    if (String(data.room_id) === String(roomID)) {
                        applyReadUpdate(data.read_at || new Date().toISOString());
                    }
                });

            // Whisper typing emit
            msgInput.addEventListener('input', () => {
                window.Echo.private('chat.room.' + roomID)
                    .whisper('typing', { userId: USER_ID, name: 'นักศึกษา' });
            });

            const TARGET_STAFF_ID = '{{ $staffUser?->id }}';
            const IS_GENERAL_SUPPORT = {{ empty($job->id) ? 'true' : 'false' }};
            window.onlineUsersList = [];

            function checkThisChatOnline() {
                if (IS_GENERAL_SUPPORT) {
                    return window.onlineUsersList.some(u => (u.role === 'admin' || u.role === 'staff' || u.is_staff) && String(u.id) !== String(USER_ID));
                }
                return TARGET_STAFF_ID ? window.onlineUsersList.some(u => String(u.id) === String(TARGET_STAFF_ID)) : false;
            }

            // 2. Presence Channel 'online'
            window.Echo.join('online')
                .here((users) => {
                    window.onlineUsersList = users;
                    updateOnlineStatus(checkThisChatOnline());
                })
                .joining((u) => {
                    window.onlineUsersList = window.onlineUsersList.filter(usr => String(usr.id) !== String(u.id)).concat([u]);
                    updateOnlineStatus(checkThisChatOnline());
                })
                .leaving((u) => {
                    window.onlineUsersList = window.onlineUsersList.filter(usr => String(usr.id) !== String(u.id));
                    const isStillOnline = checkThisChatOnline();
                    if (!isStillOnline) {
                        const label = document.getElementById('onlineStatusLabel');
                        if (label) label.setAttribute('data-last-seen', new Date().toISOString());
                    }
                    updateOnlineStatus(isStillOnline);
                });

            function formatLastSeen(lastSeenAt) {
                if (!lastSeenAt) return 'ออฟไลน์';
                const date = new Date(lastSeenAt);
                if (isNaN(date.getTime())) return 'ออฟไลน์';
                const now = new Date();
                const diffSec = Math.max(0, Math.floor((now - date) / 1000));
                const diffMin = Math.floor(diffSec / 60);
                const diffHours = Math.floor(diffMin / 60);
                if (diffSec < 60) {
                    return 'ออนไลน์เมื่อสักครู่';
                } else if (diffMin < 60) {
                    return `ออนไลน์เมื่อ ${diffMin} นาทีที่แล้ว`;
                } else if (diffHours < 24) {
                    return `ออนไลน์เมื่อ ${diffHours} ชม. ที่แล้ว`;
                } else {
                    return `ออนไลน์เมื่อ ${date.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit' })} ${date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })}`;
                }
            }

            window.isStaffOnline = false;
            function updateOnlineStatus(isOnline) {
                window.isStaffOnline = isOnline;
                const dot = document.getElementById('staffOnlineDot');
                const label = document.getElementById('onlineStatusLabel');
                if (dot) dot.style.display = isOnline ? 'inline-block' : 'none';
                if (label) {
                    if (isOnline) {
                        label.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981;margin-right:5px;"></span><span style="color:#10b981;font-weight:600;">กำลังใช้งาน</span>';
                    } else {
                        const lastSeen = label.getAttribute('data-last-seen');
                        label.innerHTML = `<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;margin-right:4px;"></span>${formatLastSeen(lastSeen)}`;
                    }
                }
                document.querySelectorAll('.staff-avatar-online-dot').forEach(el => {
                    el.style.display = isOnline ? 'block' : 'none';
                });
            }

            setInterval(() => {
                if (!window.isStaffOnline) {
                    const label = document.getElementById('onlineStatusLabel');
                    if (label) {
                        const lastSeen = label.getAttribute('data-last-seen');
                        if (lastSeen) {
                            label.innerHTML = `<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#94a3b8;margin-right:4px;"></span>${formatLastSeen(lastSeen)}`;
                        }
                    }
                }
            }, 15000);

            window.addEventListener('beforeunload', () => {
                if (window.Echo) {
                    try { window.Echo.leave('online'); } catch(e) {}
                }
            });
        } else {
            setTimeout(initEcho, 200);
        }
    };
    initEcho();
});
</script>
@endsection
