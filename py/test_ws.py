import asyncio
import websockets
import json
import sys

async def test_ws():
    uri = "ws://127.0.0.1:8082/app/uni-chat-key?protocol=7&client=js&version=8.4.0-rc2&flash=false"
    try:
        async with websockets.connect(uri) as websocket:
            print("Connected!")
            greeting = await asyncio.wait_for(websocket.recv(), timeout=5.0)
            print(f"Received: {greeting}")
            
            sub_msg = json.dumps({
                "event": "pusher:subscribe",
                "data": {"channel": "chat.room.1"}
            })
            await websocket.send(sub_msg)
            print("Sent subscribe")
            
            res = await asyncio.wait_for(websocket.recv(), timeout=5.0)
            print(f"Received: {res}")
    except Exception as e:
        print(f"Error: {e}")

asyncio.run(test_ws())
