// cf-manager — Cloudflare Tunnel Manager (Go replacement for Python scripts)
//
// แทนที่:
//   - py/auto_update_tunnel_url.py
//   - py/start_cf_ubuntu.py
//
// รันบน Termux/S1:
//   ./cf-manager &
//
// หน้าที่:
//   1. เริ่ม cloudflared (HTTP + SSH tunnel) อัตโนมัติ
//   2. รอ origin server พร้อมก่อนเสมอ → แก้ Error 1033
//   3. อัพเดท .env APP_URL เมื่อ URL เปลี่ยน
//   4. อัพเดท GitHub Pages active_url.json
//   5. อัพเดท LINE Webhook
//   6. Clear Laravel cache
//   7. Health-check ทุก 15 วิ → auto-restart เมื่อ tunnel ล่ม
package main

import (
	"bufio"
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"regexp"
	"strings"
	"syscall"
	"time"
)

// ──────────────────────────────────────────────────────────────────────────────
// Config
// ──────────────────────────────────────────────────────────────────────────────

const (
	termuxHome     = "/data/data/com.termux/files/home"
	projectRoot    = termuxHome + "/uni-activity"
	envFile        = projectRoot + "/.env"
	logHTTP        = termuxHome + "/cloudflared.log"
	logSSH         = termuxHome + "/cloudflared-ssh.log"
	localURLJSON   = projectRoot + "/docs/active_url.json"
	artisanPath    = projectRoot + "/artisan"
	watchdogLog    = projectRoot + "/storage/logs/cf-manager.log"

	// How long to wait for origin before starting cloudflared
	originReadyTimeout = 90 * time.Second
	// Interval between tunnel health checks
	healthCheckInterval = 15 * time.Second
	// Interval between passive URL scans
	urlScanInterval = 15 * time.Second
	// Auto-restart cooldown
	restartCooldown = 120 * time.Second
	// Fail threshold before auto-restart
	failThreshold = 3
)

// Default tunnel target — can be overridden via TUNNEL_TARGET_URL in .env
var tunnelTarget = "http://127.0.0.1:8088"

var cfURLRegex = regexp.MustCompile(`https://[a-zA-Z0-9-]+\.trycloudflare\.com`)

// ──────────────────────────────────────────────────────────────────────────────
// .env helpers
// ──────────────────────────────────────────────────────────────────────────────

func readEnv(key string) string {
	f, err := os.Open(envFile)
	if err != nil {
		return ""
	}
	defer f.Close()
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if strings.HasPrefix(line, key+"=") {
			return strings.Trim(strings.TrimPrefix(line, key+"="), "\"' \r\n")
		}
	}
	return ""
}

func updateEnv(updates map[string]string) {
	data, err := os.ReadFile(envFile)
	if err != nil {
		log.Printf("[ENV] Cannot read .env: %v", err)
		return
	}
	lines := strings.Split(string(data), "\n")
	replaced := map[string]bool{}
	for i, line := range lines {
		for k, v := range updates {
			if strings.HasPrefix(line, k+"=") {
				lines[i] = k + "=" + v
				replaced[k] = true
			}
		}
	}
	for k, v := range updates {
		if !replaced[k] {
			lines = append(lines, k+"="+v)
		}
	}
	if err := os.WriteFile(envFile, []byte(strings.Join(lines, "\n")), 0644); err != nil {
		log.Printf("[ENV] Write failed: %v", err)
	} else {
		log.Printf("[ENV] Updated: %v", updates)
	}
}

// ──────────────────────────────────────────────────────────────────────────────
// Log parsing
// ──────────────────────────────────────────────────────────────────────────────

// lastURLFromLog returns the LAST trycloudflare URL found in the log file.
// Using last-occurrence prevents picking up stale URLs from previous runs.
func lastURLFromLog(logPath string) string {
	data, err := os.ReadFile(logPath)
	if err != nil {
		return ""
	}
	all := cfURLRegex.FindAll(data, -1)
	if len(all) == 0 {
		return ""
	}
	return string(all[len(all)-1])
}

// ──────────────────────────────────────────────────────────────────────────────
// Origin health check
// ──────────────────────────────────────────────────────────────────────────────

// waitForOrigin blocks until targetURL responds with HTTP < 500, or timeout.
func waitForOrigin(targetURL string, timeout time.Duration) bool {
	deadline := time.Now().Add(timeout)
	client := &http.Client{Timeout: 3 * time.Second}
	for time.Now().Before(deadline) {
		resp, err := client.Get(targetURL)
		if err == nil && resp.StatusCode < 500 {
			resp.Body.Close()
			log.Printf("[CF-MGR] Origin %s ready ✓", targetURL)
			return true
		}
		log.Printf("[CF-MGR] Waiting for origin %s …", targetURL)
		time.Sleep(3 * time.Second)
	}
	return false
}

// isURLAlive returns true when the public tunnel URL is responding correctly.
func isURLAlive(url string) bool {
	client := &http.Client{
		Timeout:       5 * time.Second,
		CheckRedirect: func(r *http.Request, via []*http.Request) error { return http.ErrUseLastResponse },
	}
	resp, err := client.Head(url)
	if err != nil {
		return false
	}
	defer resp.Body.Close()
	return resp.StatusCode < 530
}

// ──────────────────────────────────────────────────────────────────────────────
// Side-effects after new URL detected
// ──────────────────────────────────────────────────────────────────────────────

func applyNewURL(httpURL, sshURL string) {
	log.Printf("[CF-MGR] ✅ New tunnel URL: %s (SSH: %s)", httpURL, sshURL)

	// 1. Update .env
	updateEnv(map[string]string{
		"APP_URL":           httpURL,
		"LINE_CALLBACK_URL": "https://gitnonta.github.io/uni-activity/callback.html",
	})

	// 2. Write local JSON
	_ = os.MkdirAll(filepath.Dir(localURLJSON), 0755)
	payload, _ := json.MarshalIndent(map[string]interface{}{
		"url":        httpURL,
		"ssh_url":    sshURL,
		"updated_at": time.Now().Format("2006-01-02 15:04:05"),
	}, "", "  ")
	if err := os.WriteFile(localURLJSON, payload, 0644); err != nil {
		log.Printf("[CF-MGR][LOCAL] Write failed: %v", err)
	} else {
		log.Printf("[CF-MGR][LOCAL] active_url.json written")
	}

	// 3–5 async (don't block the main loop)
	go pushToGitHub(httpURL, sshURL)
	go updateLINEWebhook(httpURL)
	go clearLaravelCache()
}

func clearLaravelCache() {
	for _, cmd := range []string{"config:cache", "route:cache", "view:cache"} {
		out, err := exec.Command("php", artisanPath, cmd).CombinedOutput()
		if err != nil {
			log.Printf("[CF-MGR][ARTISAN] %s failed: %v — %s", cmd, err, strings.TrimSpace(string(out)))
		} else {
			log.Printf("[CF-MGR][ARTISAN] %s → OK", cmd)
		}
	}
}

func updateLINEWebhook(httpURL string) {
	token := readEnv("LINE_CHANNEL_ACCESS_TOKEN")
	if token == "" {
		return
	}
	body, _ := json.Marshal(map[string]string{"endpoint": httpURL + "/line/callback"})
	req, _ := http.NewRequest("PUT", "https://api.line.me/v2/bot/channel/webhook/endpoint", bytes.NewBuffer(body))
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "UniActivity-CF-Manager-Go")

	client := &http.Client{Timeout: 10 * time.Second, Transport: &http.Transport{Proxy: nil}}
	resp, err := client.Do(req)
	if err != nil {
		log.Printf("[CF-MGR][LINE] Webhook failed: %v", err)
		return
	}
	defer resp.Body.Close()
	log.Printf("[CF-MGR][LINE] Webhook updated → HTTP %d", resp.StatusCode)
}

func pushToGitHub(httpURL, sshURL string) {
	pat := readEnv("GITHUB_PAT")
	if pat == "" {
		return
	}

	const apiURL = "https://api.github.com/repos/GitNonta/uni-activity/contents/docs/active_url.json"
	headers := map[string]string{
		"Authorization": "token " + pat,
		"Accept":        "application/vnd.github.v3+json",
		"User-Agent":    "UniActivity-CF-Manager-Go",
	}

	client := &http.Client{Timeout: 15 * time.Second}

	// Get SHA
	var sha string
	if req, err := http.NewRequest("GET", apiURL, nil); err == nil {
		for k, v := range headers {
			req.Header.Set(k, v)
		}
		if resp, err := client.Do(req); err == nil {
			var res map[string]interface{}
			_ = json.NewDecoder(resp.Body).Decode(&res)
			resp.Body.Close()
			if s, ok := res["sha"].(string); ok {
				sha = s
			}
		}
	}

	// Build & push
	content, _ := json.MarshalIndent(map[string]interface{}{
		"url":        httpURL,
		"ssh_url":    sshURL,
		"updated_at": time.Now().Format("2006-01-02 15:04:05"),
	}, "", "  ")
	putPayload := map[string]interface{}{
		"message": fmt.Sprintf("chore: update active tunnel URL to %s [cf-manager-go]", httpURL),
		"content": base64.StdEncoding.EncodeToString(content),
	}
	if sha != "" {
		putPayload["sha"] = sha
	}

	bodyBytes, _ := json.Marshal(putPayload)
	putReq, _ := http.NewRequest("PUT", apiURL, bytes.NewBuffer(bodyBytes))
	for k, v := range headers {
		putReq.Header.Set(k, v)
	}
	putReq.Header.Set("Content-Type", "application/json")

	if resp, err := client.Do(putReq); err == nil {
		defer resp.Body.Close()
		if resp.StatusCode == 200 || resp.StatusCode == 201 {
			log.Printf("[CF-MGR][GH] Pushed active_url.json → %s", httpURL)
		} else {
			b, _ := io.ReadAll(resp.Body)
			l := len(b)
			if l > 200 {
				l = 200
			}
			log.Printf("[CF-MGR][GH] Push HTTP %d: %s", resp.StatusCode, string(b[:l]))
		}
	} else {
		log.Printf("[CF-MGR][GH] Push failed: %v", err)
	}
}

// ──────────────────────────────────────────────────────────────────────────────
// Tunnel lifecycle
// ──────────────────────────────────────────────────────────────────────────────

func killCloudflared() {
	_ = exec.Command("pkill", "-9", "cloudflared").Run()
	time.Sleep(2 * time.Second)
}

// startTunnels launches cloudflared HTTP + SSH tunnel processes.
// It truncates old logs first so URL detection only picks up this run.
// It WAITS for the origin to be ready to avoid Error 1033.
func startTunnels() (httpURL, sshURL string, err error) {
	// Read tunnel target from .env
	if t := readEnv("TUNNEL_TARGET_URL"); t != "" {
		tunnelTarget = t
	}

	// Wait for origin
	log.Printf("[CF-MGR] Waiting for origin %s (max %s)…", tunnelTarget, originReadyTimeout)
	if !waitForOrigin(tunnelTarget, originReadyTimeout) {
		log.Printf("[CF-MGR] ⚠️  Origin not ready — starting tunnel anyway (will retry connections)")
	}

	// Truncate old log files
	_ = os.WriteFile(logHTTP, []byte(""), 0644)
	_ = os.WriteFile(logSSH, []byte(""), 0644)

	// HTTP tunnel
	httpCmd := fmt.Sprintf(
		"nohup cloudflared tunnel --url %s --no-autoupdate --metrics 127.0.0.1:20241 > %s 2>&1 &",
		tunnelTarget, logHTTP,
	)
	if e := exec.Command("sh", "-c", httpCmd).Start(); e != nil {
		log.Printf("[CF-MGR] ⚠️  HTTP tunnel start failed: %v", e)
	}
	time.Sleep(1 * time.Second)

	// SSH tunnel
	sshCmd := fmt.Sprintf(
		"nohup cloudflared tunnel --url http://127.0.0.1:80 --no-autoupdate --metrics 127.0.0.1:20242 > %s 2>&1 &",
		logSSH,
	)
	if e := exec.Command("sh", "-c", sshCmd).Start(); e != nil {
		log.Printf("[CF-MGR] ⚠️  SSH tunnel start failed: %v", e)
	}

	// Poll logs for URLs (max 60s)
	log.Println("[CF-MGR] Polling for tunnel URLs…")
	for i := 0; i < 60; i++ {
		time.Sleep(1 * time.Second)
		if httpURL == "" {
			httpURL = lastURLFromLog(logHTTP)
		}
		if sshURL == "" {
			sshURL = lastURLFromLog(logSSH)
		}
		if httpURL != "" && sshURL != "" {
			break
		}
	}

	if httpURL == "" {
		return "", "", fmt.Errorf("timeout: no HTTP tunnel URL appeared within 60s")
	}
	return httpURL, sshURL, nil
}

// ──────────────────────────────────────────────────────────────────────────────
// Background watchers
// ──────────────────────────────────────────────────────────────────────────────

// runURLWatcher passively monitors cloudflared.log and applies side-effects
// when it detects a new URL (e.g. tunnel spontaneously reconnected).
func runURLWatcher() {
	var lastURL string
	for {
		time.Sleep(urlScanInterval)
		cur := lastURLFromLog(logHTTP)
		if cur == "" || cur == lastURL {
			continue
		}
		if !isURLAlive(cur) {
			continue
		}
		sshCur := lastURLFromLog(logSSH)
		lastURL = cur
		applyNewURL(cur, sshCur)
	}
}

// runHealthWatcher pings the active tunnel URL and auto-restarts on failure.
func runHealthWatcher(getActiveURL func() string) {
	failCount := 0
	lastRestart := time.Time{}

	client := &http.Client{
		Timeout:       8 * time.Second,
		CheckRedirect: func(r *http.Request, via []*http.Request) error { return http.ErrUseLastResponse },
	}

	for {
		time.Sleep(healthCheckInterval)

		url := getActiveURL()
		if url == "" {
			continue
		}

		resp, err := client.Head(url)
		if err == nil && resp.StatusCode < 530 {
			resp.Body.Close()
			failCount = 0
			log.Printf("[CF-MGR][HEALTH] ✅ %s — OK", url)
		} else {
			failCount++
			var reason string
			if err != nil {
				reason = err.Error()
			} else {
				reason = fmt.Sprintf("HTTP %d", resp.StatusCode)
			}
			log.Printf("[CF-MGR][HEALTH] ⚠️  %s failed (%s) — failCount=%d", url, reason, failCount)

			if failCount >= failThreshold && time.Since(lastRestart) > restartCooldown {
				lastRestart = time.Now()
				failCount = 0
				log.Println("[CF-MGR] 🔄 Auto-restart triggered")
				killCloudflared()
				newHTTP, newSSH, startErr := startTunnels()
				if startErr != nil {
					log.Printf("[CF-MGR] Auto-restart failed: %v", startErr)
				} else {
					applyNewURL(newHTTP, newSSH)
				}
			}
		}
	}
}

// ──────────────────────────────────────────────────────────────────────────────
// Main
// ──────────────────────────────────────────────────────────────────────────────

func main() {
	// Setup logging to file + stdout
	_ = os.MkdirAll(filepath.Dir(watchdogLog), 0755)
	logFile, err := os.OpenFile(watchdogLog, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0644)
	if err == nil {
		log.SetOutput(io.MultiWriter(os.Stdout, logFile))
	}
	log.SetFlags(log.Ldate | log.Ltime | log.Lmicroseconds)

	log.Println("╔══════════════════════════════════════════════════════╗")
	log.Println("║   cf-manager v2.0 — Go Cloudflare Tunnel Manager    ║")
	log.Println("║   Replaces: auto_update_tunnel_url.py                ║")
	log.Println("╚══════════════════════════════════════════════════════╝")

	// Graceful shutdown
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, os.Interrupt, syscall.SIGTERM)
	go func() {
		<-sigCh
		log.Println("[CF-MGR] Shutting down…")
		killCloudflared()
		os.Exit(0)
	}()

	// Kill any existing cloudflared first
	killCloudflared()

	// Start fresh tunnels
	httpURL, sshURL, err := startTunnels()
	if err != nil {
		log.Printf("[CF-MGR] Initial tunnel start failed: %v", err)
		log.Println("[CF-MGR] Continuing with background watchers…")
	} else {
		applyNewURL(httpURL, sshURL)
	}

	// Track current active URL
	activeURL := httpURL
	getActiveURL := func() string {
		// Re-read from log each time (more reliable than in-memory state)
		if u := lastURLFromLog(logHTTP); u != "" {
			return u
		}
		return activeURL
	}

	// Start background watchers
	go runURLWatcher()
	go runHealthWatcher(getActiveURL)

	log.Println("[CF-MGR] All watchers started. Running…")

	// Block forever
	select {}
}
