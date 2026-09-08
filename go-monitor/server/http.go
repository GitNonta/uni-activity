package server

import (
	"encoding/json"
	"net/http"
	"net/http/httputil"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"time"

	"uni-activity/go-monitor/collector"
)

type HTTPServer struct {
	staticDir string
	collector *collector.Collector
	wsHub     *WSHub
}

func NewHTTPServer(staticDir string, c *collector.Collector, hub *WSHub) *HTTPServer {
	return &HTTPServer{
		staticDir: staticDir,
		collector: c,
		wsHub:     hub,
	}
}

func (s *HTTPServer) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// 1. CORS & Preflight
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
	w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Cache-Control")

	if r.Method == http.MethodOptions {
		w.WriteHeader(http.StatusNoContent)
		return
	}

	// 2. WebSocket Upgrade
	if IsWebSocketRequest(r) || r.URL.Path == "/ws" {
		s.wsHub.HandleUpgrade(w, r)
		return
	}

	// 3. API Endpoints
	path := r.URL.Path
	if path == "/api/stats" {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		data := s.collector.GetCachedJSON()
		if len(data) == 0 {
			var err error
			data, err = s.collector.Collect()
			if err != nil {
				http.Error(w, "Failed to collect stats", http.StatusInternalServerError)
				return
			}
		}
		w.Write(data)
		return
	}

	if path == "/api/tunnel-urls" {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		payload, _ := json.Marshal(map[string]interface{}{
			"http_url":   "",
			"ssh_url":    "",
			"server_lan": "192.168.1.222",
			"ssh_port":   8022,
			"updated_at": time.Now().Format("2006-01-02 15:04:05"),
		})
		w.Write(payload)
		return
	}

	if strings.HasPrefix(path, "/api/proxy/traffic") {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		payload, _ := json.Marshal(map[string]interface{}{
			"ok":        true,
			"timestamp": time.Now().Unix(),
			"traffic":   map[string]interface{}{"requests": 150},
		})
		w.Write(payload)
		return
	}

	// 4. Forward admin/control endpoints to Python backend on port 9995
	if strings.HasPrefix(path, "/api/deploy") || strings.HasPrefix(path, "/api/restart") ||
		strings.HasPrefix(path, "/api/proxy/blocklist") || strings.HasPrefix(path, "/api/st") ||
		strings.HasPrefix(path, "/api/speedtest") || strings.HasPrefix(path, "/api/failed-jobs") {
		pyTarget, _ := url.Parse("http://127.0.0.1:9995")
		proxy := httputil.NewSingleHostReverseProxy(pyTarget)
		proxy.ServeHTTP(w, r)
		return
	}

	// 5. Static Files & SPA Fallback
	cleanPath := strings.TrimPrefix(path, "/")
	if cleanPath == "" {
		cleanPath = "index.html"
	}

	filePath := filepath.Join(s.staticDir, cleanPath)
	fileInfo, err := os.Stat(filePath)

	// Fallback to index.html for SPA if file doesn't exist or is a directory
	if err != nil || fileInfo.IsDir() {
		filePath = filepath.Join(s.staticDir, "index.html")
		fileInfo, err = os.Stat(filePath)
		if err != nil {
			http.NotFound(w, r)
			return
		}
	}

	// Cache static assets
	ext := filepath.Ext(filePath)
	if ext == ".js" || ext == ".css" {
		w.Header().Set("Cache-Control", "public, max-age=3600")
	}

	http.ServeFile(w, r, filePath)
}
