package server

import (
	"crypto/sha1"
	"encoding/base64"
	"encoding/binary"
	"fmt"
	"net"
	"net/http"
	"strings"
	"sync"
	"sync/atomic"
)

const wsGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11"

type WSHub struct {
	mu           sync.RWMutex
	conns        map[net.Conn]struct{}
	activeCount  int32
	onConnect    func(net.Conn)
	onDisconnect func()
}

func NewWSHub(onConn func(net.Conn), onDisc func()) *WSHub {
	return &WSHub{
		conns:        make(map[net.Conn]struct{}),
		onConnect:    onConn,
		onDisconnect: onDisc,
	}
}

func (h *WSHub) ClientCount() int {
	return int(atomic.LoadInt32(&h.activeCount))
}

func (h *WSHub) Register(conn net.Conn) {
	h.mu.Lock()
	h.conns[conn] = struct{}{}
	atomic.AddInt32(&h.activeCount, 1)
	h.mu.Unlock()

	if h.onConnect != nil {
		h.onConnect(conn)
	}

	// Read loop to detect disconnect
	go func() {
		defer h.Unregister(conn)
		buf := make([]byte, 512)
		for {
			_, err := conn.Read(buf)
			if err != nil {
				break
			}
		}
	}()
}

func (h *WSHub) Unregister(conn net.Conn) {
	h.mu.Lock()
	if _, ok := h.conns[conn]; ok {
		delete(h.conns, conn)
		atomic.AddInt32(&h.activeCount, -1)
		conn.Close()
	}
	h.mu.Unlock()

	if h.onDisconnect != nil {
		h.onDisconnect()
	}
}

// Broadcast sends RFC 6455 unmasked text frame to all active clients
func (h *WSHub) Broadcast(payload []byte) {
	frame := encodeWSTextFrame(payload)

	h.mu.RLock()
	defer h.mu.RUnlock()

	for conn := range h.conns {
		go func(c net.Conn) {
			_, err := c.Write(frame)
			if err != nil {
				h.Unregister(c)
			}
		}(conn)
	}
}

// SendDirect sends frame to a specific connection
func (h *WSHub) SendDirect(conn net.Conn, payload []byte) error {
	frame := encodeWSTextFrame(payload)
	_, err := conn.Write(frame)
	return err
}

func encodeWSTextFrame(payload []byte) []byte {
	length := len(payload)
	var header []byte

	if length < 126 {
		header = []byte{0x81, byte(length)}
	} else if length <= 65535 {
		header = make([]byte, 4)
		header[0] = 0x81
		header[1] = 126
		binary.BigEndian.PutUint16(header[2:4], uint16(length))
	} else {
		header = make([]byte, 10)
		header[0] = 0x81
		header[1] = 127
		binary.BigEndian.PutUint64(header[2:10], uint64(length))
	}

	return append(header, payload...)
}

func (h *WSHub) HandleUpgrade(w http.ResponseWriter, r *http.Request) {
	key := r.Header.Get("Sec-WebSocket-Key")
	if key == "" {
		http.Error(w, "Missing Sec-WebSocket-Key", http.StatusBadRequest)
		return
	}

	hash := sha1.New()
	hash.Write([]byte(key + wsGUID))
	acceptKey := base64.StdEncoding.EncodeToString(hash.Sum(nil))

	hijacker, ok := w.(http.Hijacker)
	if !ok {
		http.Error(w, "Websocket upgrade not supported", http.StatusInternalServerError)
		return
	}

	conn, buf, err := hijacker.Hijack()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	// Send 101 Switching Protocols response
	resp := fmt.Sprintf(
		"HTTP/1.1 101 Switching Protocols\r\n"+
			"Upgrade: websocket\r\n"+
			"Connection: Upgrade\r\n"+
			"Sec-WebSocket-Accept: %s\r\n\r\n",
		acceptKey,
	)
	if _, err := buf.WriteString(resp); err != nil {
		conn.Close()
		return
	}
	if err := buf.Flush(); err != nil {
		conn.Close()
		return
	}

	h.Register(conn)
}

// IsWebSocketRequest checks headers
func IsWebSocketRequest(r *http.Request) bool {
	return strings.ToLower(r.Header.Get("Upgrade")) == "websocket"
}
