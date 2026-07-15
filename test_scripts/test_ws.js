const WebSocket = require('ws');
const ws = new WebSocket('ws://127.0.0.1:8082/app/uni-chat-key?protocol=7&client=js&version=8.4.0-rc2&flash=false');

ws.on('open', function open() {
  console.log('Connected');
});

ws.on('message', function incoming(data) {
  console.log('Received: %s', data);
  const msg = JSON.parse(data);
  if (msg.event === 'pusher:connection_established') {
    const subscribeMsg = JSON.stringify({
      event: 'pusher:subscribe',
      data: { channel: 'chat.room.1' }
    });
    ws.send(subscribeMsg);
    console.log('Sent subscribe');
  }
});
