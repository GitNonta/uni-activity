import { useEffect, useRef, useState, useCallback } from 'react'

const WS_URL = `ws://${window.location.hostname}:${window.location.port}/ws`

export function useWebSocket() {
  const [data, setData] = useState(null)
  const [connected, setConnected] = useState(false)
  const wsRef = useRef(null)
  const reconnectTimer = useRef(null)

  const connect = useCallback(() => {
    try {
      const ws = new WebSocket(WS_URL)
      wsRef.current = ws

      ws.onopen = () => {
        setConnected(true)
        if (reconnectTimer.current) clearTimeout(reconnectTimer.current)
      }

      ws.onmessage = (evt) => {
        try {
          setData(JSON.parse(evt.data))
        } catch (_) {}
      }

      ws.onclose = () => {
        setConnected(false)
        reconnectTimer.current = setTimeout(connect, 3000)
      }

      ws.onerror = () => {
        ws.close()
      }
    } catch (_) {}
  }, [])

  useEffect(() => {
    connect()
    return () => {
      if (wsRef.current) wsRef.current.close()
      if (reconnectTimer.current) clearTimeout(reconnectTimer.current)
    }
  }, [connect])

  return { data, connected }
}
