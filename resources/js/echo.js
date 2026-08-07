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
//   HTTPS (Cloudflare Tunnel) → wss://[tunnel-hostname]:443/app/...
//     Cloudflare รับ WSS และส่งต่อเป็น WS ธรรมดาให้ Nginx → Reverb
//
//   HTTP (Local IP / 192.168.x.x) → ws://[REVERB_HOST]:8080/app/...
//     เชื่อมตรง Nginx บนเครื่อง server
// ─────────────────────────────────────────────────────────────────────────────

const isHttps   = window.location.protocol === 'https:';
const pageHost  = window.location.hostname;
const localIP   = import.meta.env.VITE_REVERB_HOST || '192.168.1.222';
const localPort = parseInt(import.meta.env.VITE_REVERB_PORT) || 8080;

// เมื่อเข้าผ่าน HTTPS → ใช้ hostname ปัจจุบัน (tunnel) + port 443 + TLS
// เมื่อเข้าผ่าน HTTP  → ใช้ Reverb server จริง (192.168.1.222) + port 8080
const wsHost  = isHttps ? pageHost : localIP;
const wsPort  = isHttps ? 443      : localPort;
const forceTLS = isHttps;

console.log('🔌 Reverb Config:', {
    wsHost, wsPort, forceTLS,
    access: isHttps ? 'HTTPS/Tunnel→WSS' : 'HTTP/Local→WS',
});

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'uni-chat-key',
    wsHost:  wsHost,
    wsPort:  wsPort,
    wssPort: wsPort,
    forceTLS: forceTLS,
    enabledTransports: ['ws', 'wss'],
});

