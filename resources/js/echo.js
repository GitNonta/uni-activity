import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Dynamically determine WebSocket host, port, and TLS mode based on current URL
const isHttps = window.location.protocol === 'https:';
const host = window.location.hostname;
const isIp = /^(\d{1,3}\.){3}\d{1,3}$/.test(host) || host === 'localhost';

// For domain names over HTTPS (e.g. Cloudflare Tunnel), use WSS on port 443
// For local IP / localhost or plain HTTP, use plain WS on port 8080
const forceTLS = isHttps && !isIp;
const wsPort = forceTLS ? 443 : (parseInt(import.meta.env.VITE_REVERB_PORT) || 8080);

console.log('🔌 Dynamic Reverb Config:', { host, wsPort, isHttps, isIp, forceTLS });

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'uni-chat-key',
    wsHost: host,
    wsPort: wsPort,
    wssPort: wsPort,
    forceTLS: forceTLS,
    enabledTransports: ['ws', 'wss'],
});

