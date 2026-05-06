const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, {
    cors: { origin: '*', methods: ['GET', 'POST'] }
});

const SECRET = process.env.SOCKET_SECRET || 'socket_secret';

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
    socket.on('join', (room) => {
        socket.join(room);
    });

    // Typing whisper: relay to another room without broadcasting to sender
    socket.on('typing', ({ toRoom, userId, name }) => {
        socket.to(toRoom).emit('typing', { userId, name });
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`Socket.io server running on port ${PORT}`);
});
