const express = require('express');
const crypto = require('crypto');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
app.use(express.json());

const server = http.createServer(app);
const allowedOrigins = (process.env.SOCKET_ALLOWED_ORIGINS || 'http://localhost:8000,http://127.0.0.1:8000')
    .split(',')
    .map((origin) => origin.trim())
    .filter(Boolean);
const io = new Server(server, {
    cors: { origin: allowedOrigins, methods: ['GET', 'POST'] }
});

const SECRET = process.env.SOCKET_SECRET || 'socket_secret';

function validRoomToken(room, token) {
    if (typeof room !== 'string' || typeof token !== 'string') return false;

    const expected = crypto.createHmac('sha256', SECRET).update(room).digest('hex');
    if (token.length !== expected.length) return false;

    return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(token));
}

app.get('/health', (_, res) => res.json({ ok: true }));

// Laravel calls this to broadcast events to rooms
app.post('/emit', (req, res) => {
    const { secret, room, event, data } = req.body;
    if (secret !== SECRET) return res.status(401).json({ error: 'Unauthorized' });
    io.to(room).emit(event, data);
    return res.json({ ok: true });
});

io.on('connection', (socket) => {
    // Client joins a named room
    socket.on('join', ({ room, token } = {}) => {
        if (!validRoomToken(room, token)) {
            socket.emit('join:error', { error: 'Unauthorized room' });
            return;
        }

        socket.join(room);
    });

    // Typing whisper: relay to another room without broadcasting to sender
    socket.on('typing', ({ toRoom, token, userId, name } = {}) => {
        if (!validRoomToken(toRoom, token)) return;

        socket.to(toRoom).emit('typing', { userId, name });
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`Socket.io server running on port ${PORT}`);
});
