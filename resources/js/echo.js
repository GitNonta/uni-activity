import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const host = window.location.hostname;
const currentPort = window.location.port;
const protocol = window.location.protocol === 'https:' ? 'https' : 'http';

// กำหนดพอร์ตสำหรับ WebSocket อัตโนมัติ
// 1. ถ้าเป็น localhost/127.0.0.1 และเข้าผ่านพอร์ต 8000 -> ใช้ 8080 (Reverb Direct)
// 2. ถ้าเข้าผ่านหน้าเว็บพอร์ตอื่นๆ (เช่น ngrok หรือ public ip) -> ใช้พอร์ตเดียวกับหน้าเว็บ (Nginx Proxy)
let wsPort = currentPort || (protocol === 'https' ? 443 : 80);
if ((host === 'localhost' || host === '127.0.0.1') && currentPort === '8000') {
    wsPort = 8080;
}

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: wsPort,
    wssPort: wsPort,
    forceTLS: protocol === 'https',
    enabledTransports: ['ws', 'wss'],
});
