import asyncio
import websockets
import json

async def test_ws():
    uri = "wss://smithsonian-identification-recipient-quarters.trycloudflare.com/app/uni-chat-key?protocol=7&client=js&version=8.4.0-rc2&flash=false"
    try:
        async with websockets.connect(uri) as websocket:
            print("Connected!")
            greeting = await asyncio.wait_for(websocket.recv(), timeout=5.0)
            print(f"Received: {greeting}")
    except Exception as e:
        print(f"Error: {e}")

asyncio.run(test_ws())
