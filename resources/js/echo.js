import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Use configured values from .env (via Vite)
const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const wsPort = import.meta.env.VITE_REVERB_PORT || 8080;
const protocol = import.meta.env.VITE_REVERB_SCHEME || 'http';

console.log('🔌 Reverb config:', { host, wsPort, protocol });

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
