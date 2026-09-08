package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"
	"time"

	"uni-activity/go-monitor/collector"
	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/server"
	"uni-activity/go-monitor/telegram"
	"uni-activity/go-monitor/tunnel"
)

func main() {
	portFlag := flag.Int("port", 9999, "HTTP port to listen on")
	flag.Parse()

	log.Printf("🚀 Starting Pure Go Monitor Agent on port %d...", *portFlag)

	// Determine project root and static dist directory
	projectRoot := "/data/data/com.termux/files/home/uni-activity"
	if _, err := os.Stat(projectRoot); err != nil {
		cwd, _ := os.Getwd()
		projectRoot = filepath.Dir(cwd)
	}

	staticDir := filepath.Join(projectRoot, "monitor-ui", "dist")
	log.Printf("📂 Project Root: %s", projectRoot)
	log.Printf("🎨 Static UI Dir: %s", staticDir)

	// Initialize Configuration & Environment (.env)
	config.InitConfig(projectRoot)

	// Start Background Engines
	log.Println("🤖 Starting Telegram Bot Poller...")
	telegram.StartBotPoller()

	log.Println("☁️ Starting Cloudflare Tunnel Watcher...")
	tunnel.StartTunnelWatcher()

	// Initialize Telemetry Collector
	col := collector.NewCollector(projectRoot)

	// Initial collection snapshot
	initialData, err := col.Collect()
	if err != nil {
		log.Printf("⚠️ Initial collection warning: %v", err)
	} else {
		log.Printf("✅ Initial telemetry snapshot collected (%d bytes)", len(initialData))
	}

	wakeChan := make(chan struct{}, 1)
	wake := func() {
		select {
		case wakeChan <- struct{}{}:
		default:
		}
	}

	// Create WebSocket Hub with Instant Push on Connect
	var hub *server.WSHub
	hub = server.NewWSHub(
		func(c net.Conn) {
			// Instant Push to newly connected client
			cached := col.GetCachedJSON()
			if len(cached) > 0 {
				_ = hub.SendDirect(c, cached)
			}
			log.Printf("🔌 Web Client Connected (Active clients: %d)", hub.ClientCount())
			wake()
		},
		func() {
			log.Printf("🔌 Web Client Disconnected (Remaining clients: %d)", hub.ClientCount())
		},
	)

	// ── 1. UDP Receiver Goroutines (Ports 9998 & 9997) ────────────────────────
	startUDPReceiver(9998, func(data []byte) {
		var item map[string]interface{}
		if err := json.Unmarshal(data, &item); err == nil {
			col.AddInspectorLog(item)
		}
	})

	startUDPReceiver(9997, func(data []byte) {
		var item map[string]interface{}
		if err := json.Unmarshal(data, &item); err == nil {
			col.AddInspectorLog(item)
		}
	})

	// ── 2. Adaptive Stats Collector & Realtime Streaming Goroutine ───────────
	go func() {
		for {
			clients := hub.ClientCount()
			if clients > 0 {
				select {
				case <-time.After(2500 * time.Millisecond):
					data, err := col.Collect()
					if err == nil {
						hub.Broadcast(data)
					}
				}
			} else {
				// Idle mode: sleep 30s OR wake instantly when client connects
				select {
				case <-time.After(30 * time.Second):
					_, _ = col.Collect()
				case <-wakeChan:
					// Instantly woken up by incoming client
				}
			}
		}
	}()

	// ── 3. HTTP & WebSocket Server (Port 9999 or specified) ───────────────────
	httpServer := server.NewHTTPServer(staticDir, col, hub)
	srv := &http.Server{
		Addr:         fmt.Sprintf(":%d", *portFlag),
		Handler:      httpServer,
		ReadTimeout:  10 * time.Second,
		WriteTimeout: 10 * time.Second,
	}

	go func() {
		log.Printf("🌐 Serving React Dashboard and WebSocket on http://0.0.0.0:%d", *portFlag)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("❌ HTTP server error: %v", err)
		}
	}()

	// Notify Telegram of Startup
	go telegram.SendStartup(*portFlag)

	// ── 4. Graceful Shutdown ──────────────────────────────────────────────────
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, os.Interrupt, syscall.SIGTERM)
	<-sigChan

	log.Println("🛑 Shutting down Go Monitor gracefully...")
	_ = srv.Close()
	log.Println("👋 Shutdown complete.")
}

func startUDPReceiver(port int, handler func([]byte)) {
	addr := net.UDPAddr{
		Port: port,
		IP:   net.ParseIP("0.0.0.0"),
	}
	conn, err := net.ListenUDP("udp", &addr)
	if err != nil {
		log.Printf("⚠️ Could not bind UDP port %d: %v", port, err)
		return
	}
	log.Printf("📡 Listening for UDP telemetry on port %d", port)

	go func() {
		defer conn.Close()
		buf := make([]byte, 8192)
		for {
			n, _, err := conn.ReadFromUDP(buf)
			if err != nil {
				break
			}
			if n > 0 {
				handler(buf[:n])
			}
		}
	}()
}
