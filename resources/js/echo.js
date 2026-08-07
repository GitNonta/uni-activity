import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ─────────────────────────────────────────────────────────────────────────────
// Reverb WebSocket Config
//
// กลยุทธ์:
//   1. ถ้าเข้าผ่าน local IP (192.168.x.x) หรือ localhost → connect ตรงไปที่
//      VITE_REVERB_HOST:VITE_REVERB_PORT (ไม่ใช้ TLS)
//   2. ถ้าเข้าผ่าน domain/tunnel (HTTPS) → connect ไปที่ wsHost เดิม
//      แต่ใช้ REVERB_HOST จาก env เป็น wsHost จริง (ไม่ใช้ tunnel hostname)
//      เพราะ Cloudflare Tunnel ไม่ proxy WebSocket ตรงๆ
// ─────────────────────────────────────────────────────────────────────────────

const currentHost = window.location.hostname;
const isLocalAccess = /^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.|localhost|127\.)/.test(currentHost);

// ใช้ REVERB_HOST จาก env เสมอ (ชี้ที่ server จริง 192.168.1.222)
const reverbHost = import.meta.env.VITE_REVERB_HOST || currentHost;
const reverbPort = parseInt(import.meta.env.VITE_REVERB_PORT) || 8080;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

const forceTLS = reverbScheme === 'https';
const wsPort   = reverbPort;

console.log('🔌 Reverb Config:', {
    reverbHost, wsPort, forceTLS, isLocalAccess,
    currentHost, scheme: reverbScheme,
});

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'uni-chat-key',
    wsHost: reverbHost,
    wsPort: wsPort,
    wssPort: wsPort,
    forceTLS: forceTLS,
    enabledTransports: ['ws', 'wss'],
});
