package collector

import (
	"context"
	"encoding/json"
	"fmt"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"uni-activity/go-monitor/config"
)

// tunnelstatus.go — ส่วนที่เหลือจากระบบ tunnel หลังลบ go-monitor/tunnel/watcher.go
//  Responsibilities ที่ยังจำเป็น:
//   - อ่าน docs/active_url.json (cf-manager เป็นคนเขียน URL ปัจจุบัน)
//   - probe สุขภาพ tunnel เป็นระยะ (online / ping / error) สำหรับ dashboard + alerts engine
//   - payload /api/tunnel-urls (telegram bot + embedded dashboard ใช้)

type tunnelHealthState struct {
	mu        sync.RWMutex
	online    bool
	pingMS    int
	errStr    string
	url       string
	sshURL    string
	updatedAt string
}

// tHealth — ค่าเริ่มต้น online=true เหมือน Status เดิม (กัน alert วาบตอนเริ่ม)
var tHealth = &tunnelHealthState{online: true}

// readActiveURLs อ่าน docs/active_url.json ที่ cf-manager เขียนเมื่อ URL เปลี่ยน
func readActiveURLs() (httpURL, sshURL, updatedAt string) {
	b, err := os.ReadFile(filepath.Join(config.AppConfig.ProjectRoot, "docs", "active_url.json"))
	if err != nil {
		return "", "", ""
	}
	var payload struct {
		URL       string `json:"url"`
		SSHURL    string `json:"ssh_url"`
		UpdatedAt string `json:"updated_at"`
	}
	if json.Unmarshal(b, &payload) != nil {
		return "", "", ""
	}
	return payload.URL, payload.SSHURL, payload.UpdatedAt
}

// ActiveTunnelURL คืน URL publik ปัจจุบัน (health state ที่ probe ไว้ → fallback อ่านไฟล์สด)
func ActiveTunnelURL() string {
	tHealth.mu.RLock()
	cached := tHealth.url
	tHealth.mu.RUnlock()
	if cached != "" {
		return cached
	}
	httpURL, _, _ := readActiveURLs()
	return httpURL
}

// TunnelStatus คืน (online, ping, error, url) แทน tunnel.Status.GetStatus()
func TunnelStatus() (bool, int, string, string) {
	tHealth.mu.RLock()
	defer tHealth.mu.RUnlock()
	return tHealth.online, tHealth.pingMS, tHealth.errStr, tHealth.url
}

// GetTunnelURLs คืน payload เดิมของ /api/tunnel-urls (shape คงเดิม — bot + embedded JS ใช้)
func GetTunnelURLs() map[string]interface{} {
	httpURL, sshURL, updatedAt := readActiveURLs()
	if httpURL == "" {
		httpURL = ActiveTunnelURL()
	}
	if sshURL == "" {
		tHealth.mu.RLock()
		sshURL = tHealth.sshURL
		tHealth.mu.RUnlock()
	}
	if updatedAt == "" {
		updatedAt = time.Now().Format("2006-01-02 15:04:05")
	}
	return map[string]interface{}{
		"http_url":   httpURL,
		"ssh_url":    sshURL,
		"server_lan": "192.168.1.222",
		"ssh_port":   8022,
		"updated_at": updatedAt,
	}
}

// StartTunnelHealth probe tunnel URL ทุก 15 วิ (แทน StartTunnelWatcher ตัวเก่า —
// ไม่มี restart/propagation แล้ว เพราะ cf-manager เป็นเจ้าของ lifecycle ทั้งหมด)
func StartTunnelHealth() {
	go func() {
		time.Sleep(10 * time.Second)
		client := newTunnelProbeClient(8 * time.Second)

		for {
			time.Sleep(15 * time.Second)

			httpURL, sshURL, updatedAt := readActiveURLs()
			if httpURL == "" || strings.Contains(httpURL, "localhost") || strings.Contains(httpURL, "127.0.0.1") {
				tHealth.mu.Lock()
				tHealth.online = false
				tHealth.errStr = "NO_URL"
				tHealth.url = httpURL
				tHealth.sshURL = sshURL
				if updatedAt != "" {
					tHealth.updatedAt = updatedAt
				}
				tHealth.mu.Unlock()
				continue
			}

			t0 := time.Now()
			resp, err := client.Head(httpURL)
			latency := int(time.Since(t0).Milliseconds())

			tHealth.mu.Lock()
			tHealth.url = httpURL
			tHealth.sshURL = sshURL
			if updatedAt != "" {
				tHealth.updatedAt = updatedAt
			}
			if err == nil {
				resp.Body.Close()
				if resp.StatusCode < 530 {
					tHealth.online = true
					tHealth.pingMS = latency
					tHealth.errStr = ""
				} else {
					tHealth.online = false
					tHealth.pingMS = 0
					if resp.StatusCode == 530 {
						// HTTP 530 = Cloudflare Error 1033 (Tunnel Edge ขาดการเชื่อมต่อ)
						tHealth.errStr = "CLOUDFLARE_ERROR_1033"
					} else {
						tHealth.errStr = fmt.Sprintf("HTTP_%d", resp.StatusCode)
					}
				}
			} else {
				tHealth.online = false
				tHealth.pingMS = 0
				tHealth.errStr = classifyProbeError(err)
			}
			tHealth.mu.Unlock()
		}
	}()
}

// newTunnelProbeClient — resolver ตรง 1.1.1.1/8.8.8.8 กัน Android dnsproxyd
// cache NXDOMAIN ตอน quick-tunnel เพิ่งเปลี่ยน URL (คงพฤติกรรมจาก watcher.go เดิม)
func newTunnelProbeClient(timeout time.Duration) *http.Client {
	dialer := &net.Dialer{
		Timeout: timeout,
		Resolver: &net.Resolver{
			PreferGo: true,
			Dial: func(ctx context.Context, network, address string) (net.Conn, error) {
				d := net.Dialer{Timeout: 3 * time.Second}
				c, err := d.DialContext(ctx, "udp", "1.1.1.1:53")
				if err != nil {
					return d.DialContext(ctx, "udp", "8.8.8.8:53")
				}
				return c, nil
			},
		},
	}
	return &http.Client{
		Timeout: timeout,
		Transport: &http.Transport{
			DialContext:         dialer.DialContext,
			TLSHandshakeTimeout: 5 * time.Second,
		},
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			return http.ErrUseLastResponse
		},
	}
}

func classifyProbeError(err error) string {
	if err != nil {
		s := err.Error()
		switch {
		case strings.Contains(s, "timeout"):
			return "TIMEOUT"
		case strings.Contains(s, "certificate") || strings.Contains(s, "tls"):
			return "SSL_ERROR"
		case strings.Contains(s, "refused"):
			return "CONN_REFUSED"
		default:
			return "NET_ERROR"
		}
	}
	return "UNKNOWN"
}
