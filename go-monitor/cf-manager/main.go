// cf-manager — Cloudflare Tunnel Manager (Go replacement for Python scripts)
//
// แทนที่:
//   - py/auto_update_tunnel_url.py
//   - py/start_cf_ubuntu.py
//
// รันบน Termux/S1:
//
//	./cf-manager &
//
// หน้าที่:
//  1. เริ่ม cloudflared (HTTP + SSH tunnel) อัตโนมัติ
//  2. รอ origin server พร้อมก่อนเสมอ → แก้ Error 1033
//  3. อัพเดท .env APP_URL เมื่อ URL เปลี่ยน
//  4. อัพเดท GitHub Pages active_url.json
//  5. อัพเดท LINE Webhook
//  6. Clear Laravel cache
//  7. Health-check ทุก 15 วิ → auto-restart เมื่อ tunnel ล่ม
//     รวมถึงตรวจ "edge disconnected" (Error 1033: IP not found) ผ่าน
//     cloudflared metrics endpoint แล้ว restart ทันที ไม่ต้องรอ 3 fail
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
	"sync"
	"syscall"
	"time"
)

// ──────────────────────────────────────────────────────────────────────────────
// Config
// ──────────────────────────────────────────────────────────────────────────────

const (
	termuxHome   = "/data/data/com.termux/files/home"
	projectRoot  = termuxHome + "/uni-activity"
	envFile      = projectRoot + "/.env"
	logHTTP      = termuxHome + "/cloudflared.log"
	logSSH       = termuxHome + "/cloudflared-ssh.log"
	localURLJSON = projectRoot + "/docs/active_url.json"
	artisanPath  = projectRoot + "/artisan"
	watchdogLog  = projectRoot + "/storage/logs/cf-manager.log"
	metricsHTTP  = "http://127.0.0.1:20241/metrics"
	metricsSSH   = "http://127.0.0.1:20242/metrics"

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
	// Edge disconnected (Error 1033 "IP not found") restarts faster — see edgeFailThreshold
	edgeFailThreshold = 2
)

// Default tunnel target — can be overridden via TUNNEL_TARGET_URL in .env
var (
	tunnelTarget    = "http://127.0.0.1:8088"
	activeTunnelURL string
	mu              sync.RWMutex
)

var cfURLRegex = regexp.MustCompile(`https://[a-zA-Z0-9-]+\.trycloudflare\.com`)

func isCloudflaredAlive() bool {
	out, err := exec.Command("pgrep", "-f", "cloudflared").Output()
	return err == nil && len(strings.TrimSpace(string(out))) > 0
}

// ──────────────────────────────────────────────────────────────────────────────
// Edge connection probe (detects Error 1033 directly)
// ──────────────────────────────────────────────────────────────────────────────

// tunnelEdgeState queries the cloudflared metrics endpoint and reports whether
// the tunnel has at least one live connection to Cloudflare's edge.
//
// Error 1033 ("Argo Tunnel error / IP not found") happens exactly when this
// count drops to 0 while the process is still running — Cloudflare has no edge
// to route the request to, so every request returns the 1033 error page.
//
// Returns (hasConnections, known). known=false means the state could not be
// determined (metrics endpoint unreachable, e.g. port bound by something else,
// or the metric is missing on older cloudflared builds) — callers must treat
// that as "no data" and skip the check instead of triggering restarts.
func tunnelEdgeState(metricsURL string) (hasConnections, known bool) {
	client := &http.Client{Timeout: 3 * time.Second}
	resp, err := client.Get(metricsURL)
	if err != nil {
		return false, false
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return false, false
	}
	for _, line := range strings.Split(string(body), "\n") {
		line = strings.TrimSpace(line)
		if !strings.HasPrefix(line, "cloudflared_tunnel_server_locations{") {
			continue
		}
		fields := strings.Fields(line)
		if len(fields) >= 2 {
			return fields[len(fields)-1] != "0", true
		}
	}
	// Metric absent (older cloudflared) → unknown, do not act on it.
	return true, false
}

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
// HTTP 530 is what Cloudflare serves for tunnel-level failures (which includes
// error 1033 at the edge), so anything >= 530 counts as dead.
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
	mu.Lock()
	activeTunnelURL = httpURL
	mu.Unlock()

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
	go notifyTelegramURLChange(httpURL)
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

// notifyTelegramURLChange sends an alert when the public URL changes so users
// and admins learn about Error 1033 recovery immediately.
func notifyTelegramURLChange(httpURL string) {
	token := readEnv("TELEGRAM_BOT_TOKEN")
	chatID := readEnv("TELEGRAM_CHAT_ID")
	if token == "" || chatID == "" {
		return
	}
	body, _ := json.Marshal(map[string]interface{}{
		"chat_id":                  chatID,
		"text":                     "🌐 Tunnel URL changed → " + httpURL + "\n(Error 1033 auto-recovery applied)",
		"parse_mode":               "HTML",
		"disable_web_page_preview": true,
	})
	req, err := http.NewRequest("POST", "https://api.telegram.org/bot"+token+"/sendMessage", bytes.NewBuffer(body))
	if err != nil {
		return
	}
	req.Header.Set("Content-Type", "application/json")
	client := &http.Client{Timeout: 10 * time.Second}
	if resp, err := client.Do(req); err == nil {
		resp.Body.Close()
	}
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
	_ = exec.Command("pkill", "-9", "-f", "cloudflared").Run()
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

	// SSH tunnel (only if explicitly enabled in .env to save Cloudflare rate limit quota)
	if readEnv("ENABLE_SSH_TUNNEL") == "true" {
		sshCmd := fmt.Sprintf(
			"nohup cloudflared tunnel --url ssh://127.0.0.1:8022 --no-autoupdate --metrics 127.0.0.1:20242 > %s 2>&1 &",
			logSSH,
		)
		if e := exec.Command("sh", "-c", sshCmd).Start(); e != nil {
			log.Printf("[CF-MGR] ⚠️  SSH tunnel start failed: %v", e)
		}
	}

	// Poll logs for URLs (max 60s)
	log.Println("[CF-MGR] Polling for tunnel URLs…")
	for i := 0; i < 60; i++ {
		time.Sleep(1 * time.Second)

		// Check for rate limit error in log
		if data, err := os.ReadFile(logHTTP); err == nil {
			str := string(data)
			if strings.Contains(str, "429 Too Many Requests") || strings.Contains(str, "1015") {
				return "", "", fmt.Errorf("rate-limited by Cloudflare (HTTP 429 / Error 1015). Must cooldown for 10-15 minutes")
			}
		}

		if httpURL == "" {
			httpURL = lastURLFromLog(logHTTP)
		}
		if sshURL == "" && readEnv("ENABLE_SSH_TUNNEL") == "true" {
			sshURL = lastURLFromLog(logSSH)
		}
		if httpURL != "" && (sshURL != "" || readEnv("ENABLE_SSH_TUNNEL") != "true") {
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

// getActiveURL returns currently active tunnel URL with fallbacks:
// in-memory activeTunnelURL -> last URL from cloudflared.log -> APP_URL from .env
func getActiveURL() string {
	mu.RLock()
	cur := activeTunnelURL
	mu.RUnlock()
	if cur != "" {
		return cur
	}
	if u := lastURLFromLog(logHTTP); u != "" {
		mu.Lock()
		activeTunnelURL = u
		mu.Unlock()
		return u
	}
	return readEnv("APP_URL")
}

const cooldownFilePath = projectRoot + "/storage/logs/cf-cooldown.json"

type CooldownStatus struct {
	Active        bool   `json:"active"`
	RemainingSec  int    `json:"remaining_sec"`
	RemainingText string `json:"remaining_text"`
	Reason        string `json:"reason"`
	UpdatedAt     string `json:"updated_at"`
}

var (
	cooldownUntil  time.Time
	cooldownReason string
	cdMu           sync.RWMutex
)

func setCooldown(d time.Duration, reason string) {
	cdMu.Lock()
	cooldownUntil = time.Now().Add(d)
	cooldownReason = reason
	cdMu.Unlock()
	writeCooldownFile(true, d, reason)
}

func clearCooldown() {
	cdMu.Lock()
	cooldownUntil = time.Time{}
	cooldownReason = ""
	cdMu.Unlock()
	writeCooldownFile(false, 0, "")
}

func getCooldownRemaining() (bool, time.Duration, string) {
	cdMu.RLock()
	defer cdMu.RUnlock()
	if time.Now().Before(cooldownUntil) {
		return true, time.Until(cooldownUntil), cooldownReason
	}
	return false, 0, ""
}

func writeCooldownFile(active bool, rem time.Duration, reason string) {
	remSec := int(rem.Seconds())
	if remSec < 0 {
		remSec = 0
	}
	mins := remSec / 60
	secs := remSec % 60
	text := fmt.Sprintf("%02dm %02ds", mins, secs)
	if mins == 0 {
		text = fmt.Sprintf("%02ds", secs)
	}

	st := CooldownStatus{
		Active:        active,
		RemainingSec:  remSec,
		RemainingText: text,
		Reason:        reason,
		UpdatedAt:     time.Now().Format("2006-01-02 15:04:05"),
	}
	data, _ := json.MarshalIndent(st, "", "  ")
	_ = os.WriteFile(cooldownFilePath, data, 0644)
}

// runHealthWatcher pings the active tunnel URL and auto-restarts on failure.
//
// Error 1033 ("Argo Tunnel error") appears when cloudflared is still running
// but has lost ALL connections to Cloudflare's edge. The public URL then keeps
// serving the 1033 error page (wrapped in HTTP 530) even though the process is
// alive. To catch that case we also probe cloudflared's metrics endpoint
// (cloudflared_tunnel_server_locations): when it reports 0 edge locations we
// restart the tunnel after only edgeFailThreshold consecutive observations.
func runHealthWatcher() {
	failCount := 0
	edgeFailCount := 0
	lastRestart := time.Time{}

	client := &http.Client{
		Timeout:       8 * time.Second,
		CheckRedirect: func(r *http.Request, via []*http.Request) error { return http.ErrUseLastResponse },
	}

	for {
		time.Sleep(healthCheckInterval)

		// Check if cooldown is currently active
		isCd, remDuration, reason := getCooldownRemaining()
		if isCd {
			remSec := int(remDuration.Seconds())
			mins := remSec / 60
			secs := remSec % 60
			log.Printf("[CF-MGR][COOLDOWN] ⏳ Cooldown active: %02dm %02ds remaining before auto-restart (Reason: %s)", mins, secs, reason)
			writeCooldownFile(true, remDuration, reason)
			continue
		} else {
			writeCooldownFile(false, 0, "")
		}

		url := getActiveURL()
		cfAlive := isCloudflaredAlive()

		// Primary check: process + public URL
		restartNeeded := false
		reasonStr := ""

		if !cfAlive {
			failCount++
			log.Printf("[CF-MGR][HEALTH] ⚠️  cloudflared process is NOT running — failCount=%d", failCount)
			if failCount >= failThreshold {
				restartNeeded = true
				reasonStr = "cloudflared process down"
			}
		} else if url != "" {
			resp, err := client.Head(url)
			if err == nil && resp.StatusCode < 530 {
				resp.Body.Close()
				failCount = 0
				edgeFailCount = 0
				log.Printf("[CF-MGR][HEALTH] ✅ %s — OK", url)
			} else {
				failCount++
				errReason := ""
				if err != nil {
					errReason = err.Error()
				} else {
					errReason = fmt.Sprintf("HTTP %d", resp.StatusCode)
				}
				log.Printf("[CF-MGR][HEALTH] ⚠️  %s unreachable (%s) — failCount=%d", url, errReason, failCount)
				if failCount >= failThreshold {
					restartNeeded = true
					reasonStr = "public URL unreachable (" + errReason + ")"
				}
			}

			// Secondary check: edge connection (Error 1033 / "IP not found").
			// The URL keeps serving the 1033 page while the process looks fine.
			// Only act when the metrics endpoint gives a definite answer.
			if !restartNeeded {
				if edgeOK, known := tunnelEdgeState(metricsHTTP); known {
					if edgeOK {
						edgeFailCount = 0
					} else {
						edgeFailCount++
						log.Printf("[CF-MGR][HEALTH] ⚠️  Edge disconnected (Error 1033: IP not found) — edgeFailCount=%d", edgeFailCount)
						if edgeFailCount >= edgeFailThreshold {
							restartNeeded = true
							reasonStr = "tunnel lost edge connection (Error 1033)"
						}
					}
				}
			}
		} else {
			// No URL in memory or logs
			if u := lastURLFromLog(logHTTP); u != "" {
				mu.Lock()
				activeTunnelURL = u
				mu.Unlock()
			} else {
				failCount++
				log.Printf("[CF-MGR][HEALTH] ⚠️  No tunnel URL in log or .env — failCount=%d", failCount)
				if failCount >= failThreshold {
					restartNeeded = true
					reasonStr = "no tunnel URL available"
				}
			}
		}

		if restartNeeded && time.Since(lastRestart) > restartCooldown {
			lastRestart = time.Now()
			failCount = 0
			edgeFailCount = 0
			log.Printf("[CF-MGR] 🔄 Auto-restart triggered (%s)", reasonStr)
			newHTTP, newSSH, startErr := restartTunnelVerified()
			if startErr != nil {
				log.Printf("[CF-MGR] Auto-restart failed: %v", startErr)
				if strings.Contains(startErr.Error(), "rate-limited") {
					log.Println("[CF-MGR] ⏳ Entering 10-minute cooldown to allow Cloudflare rate limit to clear...")
					setCooldown(10*time.Minute, "Rate-limited by Cloudflare (HTTP 429 / Error 1015)")
					lastRestart = time.Now().Add(8 * time.Minute)
				}
			} else {
				clearCooldown()
				applyNewURL(newHTTP, newSSH)
			}
		}
	}
}

// restartTunnelVerified kills cloudflared, starts new tunnels and only returns
// success once the fresh URL is confirmed working from the public side.
// This guarantees a tunnel URL is never propagated while it still shows
// Cloudflare error pages such as Error 1033.
func restartTunnelVerified() (httpURL, sshURL string, err error) {
	killCloudflared()

	httpURL, sshURL, err = startTunnels()
	if err != nil {
		return "", "", err
	}

	deadline := time.Now().Add(90 * time.Second)
	client := &http.Client{
		Timeout:       8 * time.Second,
		CheckRedirect: func(r *http.Request, via []*http.Request) error { return http.ErrUseLastResponse },
	}
	for time.Now().Before(deadline) {
		resp, e := client.Head(httpURL)
		if e == nil && resp.StatusCode < 530 {
			resp.Body.Close()
			log.Printf("[CF-MGR] Restart verified — %s is live ✓", httpURL)
			return httpURL, sshURL, nil
		}
		if e == nil {
			resp.Body.Close()
		}
		log.Printf("[CF-MGR] Waiting for fresh tunnel URL to go live…")
		time.Sleep(3 * time.Second)
	}
	return "", "", fmt.Errorf("new tunnel URL %s still not serving after 90s (possible Error 1033 persistence)", httpURL)
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
	log.Println("║   cf-manager v2.1 — Go Cloudflare Tunnel Manager    ║")
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
		if strings.Contains(err.Error(), "rate-limited") {
			setCooldown(10*time.Minute, "Rate-limited by Cloudflare (HTTP 429 / Error 1015)")
		}
		log.Println("[CF-MGR] Continuing with background watchers…")
	} else {
		clearCooldown()
		applyNewURL(httpURL, sshURL)
	}

	// Start background watchers
	go runURLWatcher()
	go runHealthWatcher()

	// 1-second ticker to keep cf-cooldown.json updated in real-time for live second ticking
	go func() {
		for {
			time.Sleep(1 * time.Second)
			isCd, remDuration, reason := getCooldownRemaining()
			if isCd {
				writeCooldownFile(true, remDuration, reason)
			}
		}
	}()

	log.Println("[CF-MGR] All watchers started. Running…")

	// Block forever
	select {}
}
