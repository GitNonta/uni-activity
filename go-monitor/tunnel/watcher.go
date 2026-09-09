package tunnel

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
	"path/filepath"
	"regexp"
	"strings"
	"sync"
	"time"

	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/telegram"
)

// ──────────────────────────────────────────────────────────────────────────────
// Types & State
// ──────────────────────────────────────────────────────────────────────────────

// TunnelStatus holds the latest known tunnel state. Access via mu.
type TunnelStatus struct {
	mu        sync.RWMutex
	Online    bool   `json:"online"`
	PingMS    int    `json:"ping_ms"`
	Error     string `json:"error"`
	URL       string `json:"url"`
	SSHURL    string `json:"ssh_url"`
	UpdatedAt string `json:"updated_at"`
}

// Status is the package-level singleton.
var Status = &TunnelStatus{Online: true}

func (s *TunnelStatus) GetStatus() (bool, int, string, string) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.Online, s.PingMS, s.Error, s.URL
}

// ──────────────────────────────────────────────────────────────────────────────
// Constants
// ──────────────────────────────────────────────────────────────────────────────

var cfURLRegex = regexp.MustCompile(`https://[a-zA-Z0-9-]+\.trycloudflare\.com`)

const (
	termuxHome  = "/data/data/com.termux/files/home"
	logHTTPPath = termuxHome + "/cloudflared.log"
	logSSHPath  = termuxHome + "/cloudflared-ssh.log"

	// cloudflared metrics endpoints (must match the --metrics flags cf-manager
	// starts the tunnels with)
	metricsHTTP = "http://127.0.0.1:20241/metrics"
	metricsSSH  = "http://127.0.0.1:20242/metrics"
)

// ──────────────────────────────────────────────────────────────────────────────
// .env helpers
// ──────────────────────────────────────────────────────────────────────────────

// ReadEnv reads a single key from the project .env file.
func ReadEnv(key string) string {
	envPath := filepath.Join(config.AppConfig.ProjectRoot, ".env")
	f, err := os.Open(envPath)
	if err != nil {
		return ""
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if strings.HasPrefix(line, key+"=") {
			val := strings.TrimPrefix(line, key+"=")
			return strings.Trim(val, "\"' \r\n")
		}
	}
	return ""
}

// UpdateEnv replaces key=value pairs inside .env atomically.
func UpdateEnv(updates map[string]string) error {
	envPath := filepath.Join(config.AppConfig.ProjectRoot, ".env")
	data, err := os.ReadFile(envPath)
	if err != nil {
		return err
	}

	lines := strings.Split(string(data), "\n")
	replaced := make(map[string]bool)

	for i, line := range lines {
		for k, v := range updates {
			if strings.HasPrefix(line, k+"=") {
				lines[i] = k + "=" + v
				replaced[k] = true
				break
			}
		}
	}
	// Append any key not yet found
	for k, v := range updates {
		if !replaced[k] {
			lines = append(lines, k+"="+v)
		}
	}

	return os.WriteFile(envPath, []byte(strings.Join(lines, "\n")), 0644)
}

// ──────────────────────────────────────────────────────────────────────────────
// URL detection
// ──────────────────────────────────────────────────────────────────────────────

// scanLastURLFromLog reads a log file and returns the LAST trycloudflare URL found.
// Using last-occurrence avoids picking up stale URLs from previous tunnel runs.
func scanLastURLFromLog(logPath string) string {
	data, err := os.ReadFile(logPath)
	if err != nil {
		return ""
	}
	matches := cfURLRegex.FindAll(data, -1)
	if len(matches) == 0 {
		return ""
	}
	return string(matches[len(matches)-1])
}

// isURLAlive returns true when the URL is reachable and not returning a tunnel-error code.
func isURLAlive(url string) bool {
	client := &http.Client{
		Timeout: 5 * time.Second,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			return http.ErrUseLastResponse
		},
	}
	resp, err := client.Head(url)
	if err != nil {
		return false
	}
	defer resp.Body.Close()
	// 530 is what Cloudflare serves for tunnel-level failures (which includes
	// error 1033 at the edge), so anything >= 530 counts as dead.
	return resp.StatusCode < 530
}

// GetActiveURL returns the best-known live public URL for the HTTP tunnel.
func GetActiveURL() string {
	Status.mu.RLock()
	cur := Status.URL
	Status.mu.RUnlock()
	if cur != "" {
		return cur
	}

	// 1. docs/active_url.json
	activePath := filepath.Join(config.AppConfig.ProjectRoot, "docs", "active_url.json")
	if f, err := os.Open(activePath); err == nil {
		defer f.Close()
		var d struct {
			URL string `json:"url"`
		}
		if json.NewDecoder(f).Decode(&d) == nil && d.URL != "" {
			return d.URL
		}
	}

	// 2. cloudflared metrics endpoint (port 20241)
	httpClient := &http.Client{Timeout: 2 * time.Second}
	if resp, err := httpClient.Get("http://127.0.0.1:20241/metrics"); err == nil {
		body, _ := io.ReadAll(resp.Body)
		resp.Body.Close()
		if m := cfURLRegex.Find(body); m != nil {
			return string(m)
		}
	}

	// 3. cloudflared.log — last occurrence
	return scanLastURLFromLog(logHTTPPath)
}

// GetSSHURL returns the best-known live public URL for the SSH tunnel.
func GetSSHURL() string {
	Status.mu.RLock()
	cur := Status.SSHURL
	Status.mu.RUnlock()
	if cur != "" {
		return cur
	}

	activePath := filepath.Join(config.AppConfig.ProjectRoot, "docs", "active_url.json")
	if f, err := os.Open(activePath); err == nil {
		defer f.Close()
		var d struct {
			SSHURL string `json:"ssh_url"`
		}
		if json.NewDecoder(f).Decode(&d) == nil && d.SSHURL != "" {
			return d.SSHURL
		}
	}

	httpClient := &http.Client{Timeout: 2 * time.Second}
	if resp, err := httpClient.Get("http://127.0.0.1:20242/metrics"); err == nil {
		body, _ := io.ReadAll(resp.Body)
		resp.Body.Close()
		if m := cfURLRegex.Find(body); m != nil {
			return string(m)
		}
	}

	return scanLastURLFromLog(logSSHPath)
}

// GetTunnelURLs returns full JSON payload for /api/tunnel-urls.
func GetTunnelURLs() map[string]interface{} {
	return map[string]interface{}{
		"http_url":   GetActiveURL(),
		"ssh_url":    GetSSHURL(),
		"server_lan": "192.168.1.222",
		"ssh_port":   8022,
		"updated_at": time.Now().Format("2006-01-02 15:04:05"),
	}
}

// tunnelEdgeState queries the cloudflared metrics endpoint and reports whether
// the tunnel has at least one live connection to Cloudflare's edge. When this
// count drops to 0, every request to the public URL returns the Cloudflare
// "Error 1033 / Argo Tunnel error" page (served as HTTP 530) even though the
// cloudflared process itself is still running.
//
// Returns (hasConnections, known). known=false means the state could not be
// determined (metrics endpoint unreachable, or the metric is missing on older
// cloudflared builds) — callers must treat that as "no data".
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
// Origin health check
// ──────────────────────────────────────────────────────────────────────────────

// waitForOrigin blocks until the local Laravel/origin server is accepting connections
// on targetURL, or until the timeout expires. Returns true if the origin came up.
func waitForOrigin(targetURL string, timeout time.Duration) bool {
	deadline := time.Now().Add(timeout)
	client := &http.Client{Timeout: 2 * time.Second}
	for time.Now().Before(deadline) {
		resp, err := client.Get(targetURL)
		if err == nil && resp.StatusCode < 500 {
			resp.Body.Close()
			log.Printf("[Tunnel] Origin %s is ready ✓", targetURL)
			return true
		}
		log.Printf("[Tunnel] Waiting for origin %s …", targetURL)
		time.Sleep(3 * time.Second)
	}
	log.Printf("[Tunnel] Origin %s did not become ready within %s", targetURL, timeout)
	return false
}

// ──────────────────────────────────────────────────────────────────────────────
// Post-restart actions
// ──────────────────────────────────────────────────────────────────────────────

// applyNewURL runs all side-effects after detecting a new tunnel URL.
func applyNewURL(httpURL, sshURL string) {
	log.Printf("[Tunnel] New URL detected: %s (SSH: %s)", httpURL, sshURL)

	// 1. Update Status
	Status.mu.Lock()
	Status.URL = httpURL
	Status.SSHURL = sshURL
	Status.Online = true
	Status.UpdatedAt = time.Now().Format("2006-01-02 15:04:05")
	Status.mu.Unlock()

	// 2. Update .env (APP_URL + LINE_CALLBACK_URL)
	if err := UpdateEnv(map[string]string{
		"APP_URL":           httpURL,
		"LINE_CALLBACK_URL": "https://gitnonta.github.io/uni-activity/callback.html",
	}); err != nil {
		log.Printf("[Tunnel][ENV] Failed to update .env: %v", err)
	} else {
		log.Printf("[Tunnel][ENV] .env updated → APP_URL=%s", httpURL)
	}

	// 3. Write local docs/active_url.json
	jsonPath := filepath.Join(config.AppConfig.ProjectRoot, "docs", "active_url.json")
	_ = os.MkdirAll(filepath.Dir(jsonPath), 0755)
	jsonData, _ := json.MarshalIndent(map[string]interface{}{
		"url":        httpURL,
		"ssh_url":    sshURL,
		"updated_at": time.Now().Format("2006-01-02 15:04:05"),
	}, "", "  ")
	if err := os.WriteFile(jsonPath, jsonData, 0644); err != nil {
		log.Printf("[Tunnel][LOCAL] Failed to write active_url.json: %v", err)
	} else {
		log.Printf("[Tunnel][LOCAL] active_url.json written")
	}

	// 4. Push to GitHub Pages (async)
	go PushActiveURLToGitHub(httpURL, sshURL)

	// 5. Update LINE Webhook (async)
	go updateLINEWebhook(httpURL)

	// 6. Clear Laravel cache (async)
	go clearLaravelCache()
}

// clearLaravelCache runs artisan cache commands.
func clearLaravelCache() {
	artisan := filepath.Join(config.AppConfig.ProjectRoot, "artisan")
	cmds := []string{"config:cache", "route:cache", "view:cache"}
	for _, c := range cmds {
		out, err := exec.Command("php", artisan, c).CombinedOutput()
		if err != nil {
			log.Printf("[Tunnel][ARTISAN] %s failed: %v — %s", c, err, strings.TrimSpace(string(out)))
		} else {
			log.Printf("[Tunnel][ARTISAN] %s → OK", c)
		}
	}
}

// updateLINEWebhook updates the LINE OA Webhook URL.
func updateLINEWebhook(httpURL string) {
	token := ReadEnv("LINE_CHANNEL_ACCESS_TOKEN")
	if token == "" {
		log.Println("[Tunnel][LINE] LINE_CHANNEL_ACCESS_TOKEN not found — skipping")
		return
	}

	webhook := httpURL + "/line/callback"
	body, _ := json.Marshal(map[string]string{"endpoint": webhook})

	req, err := http.NewRequest("PUT", "https://api.line.me/v2/bot/channel/webhook/endpoint", bytes.NewBuffer(body))
	if err != nil {
		return
	}
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "UniActivity-Monitor-Go")

	client := &http.Client{Timeout: 10 * time.Second, Transport: &http.Transport{Proxy: nil}}
	resp, err := client.Do(req)
	if err != nil {
		log.Printf("[Tunnel][LINE] Webhook update failed: %v", err)
		return
	}
	defer resp.Body.Close()
	if resp.StatusCode == 200 {
		log.Printf("[Tunnel][LINE] Webhook updated → %s", webhook)
	} else {
		log.Printf("[Tunnel][LINE] Webhook update HTTP %d", resp.StatusCode)
	}
}

// ──────────────────────────────────────────────────────────────────────────────
// GitHub Pages push
// ──────────────────────────────────────────────────────────────────────────────

// PushActiveURLToGitHub updates docs/active_url.json on GitHub via Contents API.
func PushActiveURLToGitHub(httpURL, sshURL string) {
	pat := ReadEnv("GITHUB_PAT")
	if pat == "" {
		log.Println("[Tunnel][GH] GITHUB_PAT not found — skipping GitHub update")
		return
	}

	const (
		owner  = "GitNonta"
		repo   = "uni-activity"
		ghPath = "docs/active_url.json"
	)
	apiURL := fmt.Sprintf("https://api.github.com/repos/%s/%s/contents/%s", owner, repo, ghPath)
	headers := map[string]string{
		"Authorization": "token " + pat,
		"Accept":        "application/vnd.github.v3+json",
		"User-Agent":    "UniActivity-Monitor-Go",
	}

	client := &http.Client{Timeout: 15 * time.Second}

	// 1. Get current SHA
	var sha string
	getReq, _ := http.NewRequest("GET", apiURL, nil)
	for k, v := range headers {
		getReq.Header.Set(k, v)
	}
	if resp, err := client.Do(getReq); err == nil {
		var res map[string]interface{}
		_ = json.NewDecoder(resp.Body).Decode(&res)
		resp.Body.Close()
		if s, ok := res["sha"].(string); ok {
			sha = s
		}
	}

	// 2. Build payload
	content, _ := json.MarshalIndent(map[string]interface{}{
		"url":        httpURL,
		"ssh_url":    sshURL,
		"updated_at": time.Now().Format("2006-01-02 15:04:05"),
	}, "", "  ")

	payload := map[string]interface{}{
		"message": fmt.Sprintf("chore: update active tunnel URL to %s [auto-sync]", httpURL),
		"content": base64.StdEncoding.EncodeToString(content),
	}
	if sha != "" {
		payload["sha"] = sha
	}

	bodyBytes, _ := json.Marshal(payload)
	putReq, _ := http.NewRequest("PUT", apiURL, bytes.NewBuffer(bodyBytes))
	for k, v := range headers {
		putReq.Header.Set(k, v)
	}
	putReq.Header.Set("Content-Type", "application/json")

	resp, err := client.Do(putReq)
	if err != nil {
		log.Printf("[Tunnel][GH] Push failed: %v", err)
		return
	}
	defer resp.Body.Close()
	if resp.StatusCode == 200 || resp.StatusCode == 201 {
		log.Printf("[Tunnel][GH] active_url.json pushed to GitHub Pages (%s)", httpURL)
	} else {
		body, _ := io.ReadAll(resp.Body)
		log.Printf("[Tunnel][GH] Push HTTP %d: %s", resp.StatusCode, string(body)[:min(200, len(body))])
	}
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}

// isCFManagerAlive reports whether the dedicated cf-manager process is running.
// cf-manager owns the tunnel lifecycle when present (rate-limit cooldown +
// verified restarts), so other components must not spawn conflicting
// cloudflared instances or race it for the metrics ports.
func isCFManagerAlive() bool {
	out, err := exec.Command("pgrep", "-f", "cf-manager").Output()
	return err == nil && len(strings.TrimSpace(string(out))) > 0
}

// ──────────────────────────────────────────────────────────────────────────────
// Tunnel restart (fixes Error 1033)
// ──────────────────────────────────────────────────────────────────────────────

// DoRestartTunnel kills all cloudflared processes, waits for origin readiness,
// then starts two new tunnel processes (HTTP + SSH), waits for their URLs and
// VERIFIES the new URL actually serves traffic before propagating it.
//
// FIX for Error 1033:
//   - Always waits for the origin (TunnelTargetURL) to respond before starting
//     cloudflared, so cloudflared never starts pointing at a dead origin.
//   - Uses last-occurrence URL detection to avoid picking up stale log entries.
//   - Verifies the fresh URL answers with HTTP < 530 before updating .env /
//     active_url.json / LINE webhook, so users never get pointed at a URL that
//     still shows the Cloudflare 1033 error page.
func DoRestartTunnel() (string, error) {
	log.Println("[Tunnel] 🔄 Restarting Cloudflare Tunnel…")

	// Step 0: If cf-manager is running, it owns tunnel lifecycle. Kill the
	// cloudflared processes and let cf-manager's health watcher spawn a fresh,
	// verified tunnel — spawning our own here would race cf-manager for the
	// metrics ports (20241/20242) and can leave two conflicting tunnels.
	if isCFManagerAlive() {
		log.Println("[Tunnel] cf-manager detected — deferring tunnel spawn to it")
		_ = exec.Command("pkill", "-9", "-f", "cloudflared").Run()
		telegram.Send("🔄 <b>Tunnel restart requested</b>\ncf-manager is spawning a fresh, verified tunnel…")
		return "", nil
	}

	// Step 1: Kill existing cloudflared processes
	_ = exec.Command("pkill", "-9", "-f", "cloudflared").Run()
	time.Sleep(2 * time.Second)

	// Step 2: Truncate old logs so we only parse the new run
	_ = os.WriteFile(logHTTPPath, []byte(""), 0644)
	_ = os.WriteFile(logSSHPath, []byte(""), 0644)

	// Step 3: Determine origin URL
	targetURL := config.AppConfig.TunnelTargetURL
	if targetURL == "" {
		targetURL = "http://127.0.0.1:8088"
	}

	// Step 4: WAIT for origin to be ready (fixes Error 1033)
	log.Printf("[Tunnel] Waiting for origin %s before starting cloudflared…", targetURL)
	if !waitForOrigin(targetURL, 60*time.Second) {
		// Origin not ready — still try; cloudflared will handle it eventually
		log.Printf("[Tunnel] ⚠️  Origin not ready within 60s — starting tunnel anyway")
	}

	// Step 5: Start HTTP tunnel
	httpCmd := fmt.Sprintf(
		"nohup cloudflared tunnel --url %s --no-autoupdate --metrics 127.0.0.1:20241 > %s 2>&1 &",
		targetURL, logHTTPPath,
	)
	if err := exec.Command("sh", "-c", httpCmd).Start(); err != nil {
		log.Printf("[Tunnel] ⚠️  Failed to start HTTP tunnel: %v", err)
	}
	time.Sleep(1 * time.Second)

	// Step 6: Start SSH tunnel (expose sshd via :80 on Android proot)
	sshCmd := fmt.Sprintf(
		"nohup cloudflared tunnel --url http://127.0.0.1:80 --no-autoupdate --metrics 127.0.0.1:20242 > %s 2>&1 &",
		logSSHPath,
	)
	if err := exec.Command("sh", "-c", sshCmd).Start(); err != nil {
		log.Printf("[Tunnel] ⚠️  Failed to start SSH tunnel: %v", err)
	}

	// Step 7: Poll logs for new URLs (max 50s)
	var newURL, sshURL string
	for i := 0; i < 50; i++ {
		time.Sleep(1 * time.Second)
		if newURL == "" {
			newURL = scanLastURLFromLog(logHTTPPath)
		}
		if sshURL == "" {
			sshURL = scanLastURLFromLog(logSSHPath)
		}
		if newURL != "" && sshURL != "" {
			break
		}
	}

	if newURL == "" {
		return "", fmt.Errorf("timeout: could not detect new HTTP tunnel URL within 50s")
	}

	// Step 8: VERIFY the new URL is actually live before propagating (Error 1033 guard)
	verifyClient := &http.Client{
		Timeout: 8 * time.Second,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			return http.ErrUseLastResponse
		},
	}
	verified := false
	for i := 0; i < 30; i++ {
		resp, err := verifyClient.Head(newURL)
		if err == nil && resp.StatusCode < 530 {
			resp.Body.Close()
			verified = true
			break
		}
		if err == nil {
			resp.Body.Close()
		}
		time.Sleep(3 * time.Second)
	}
	if !verified {
		telegram.Send(fmt.Sprintf(
			"⚠️ <b>Tunnel URL NOT live</b>\n━━━━━━━━━━━━━━━━━━━━\n🔗 <b>URL:</b> %s\n❗ New tunnel still serving Cloudflare error page (possible Error 1033) — URL not propagated",
			newURL,
		))
		return "", fmt.Errorf("new tunnel URL %s still not serving after 90s (possible Error 1033 persistence)", newURL)
	}

	// Step 9: Apply all side-effects
	applyNewURL(newURL, sshURL)

	telegram.Send(fmt.Sprintf(
		"🌐 <b>Cloudflare Tunnel Restarted</b>\n━━━━━━━━━━━━━━━━━━━━\n🔗 <b>URL:</b> %s\n🔒 <b>SSH:</b> %s",
		newURL, sshURL,
	))

	return newURL, nil
}

// ──────────────────────────────────────────────────────────────────────────────
// Background URL watcher (detects new URLs even without explicit restart)
// ──────────────────────────────────────────────────────────────────────────────

// StartURLWatcher polls cloudflared logs every 15s and applies side-effects
// whenever the tunnel URL changes. This is the passive counterpart to DoRestartTunnel.
func StartURLWatcher() {
	go func() {
		time.Sleep(10 * time.Second)
		var lastURL string

		for {
			time.Sleep(15 * time.Second)

			cur := scanLastURLFromLog(logHTTPPath)
			if cur == "" {
				// Also try metrics port
				cur = GetActiveURL()
			}
			if cur == "" || cur == lastURL {
				continue
			}
			if !isURLAlive(cur) {
				continue
			}

			sshCur := scanLastURLFromLog(logSSHPath)
			lastURL = cur
			applyNewURL(cur, sshCur)
		}
	}()
}

// ──────────────────────────────────────────────────────────────────────────────
// Background health watcher (pings tunnel every 15s, auto-restarts on failure)
// ──────────────────────────────────────────────────────────────────────────────

// StartTunnelWatcher pings the active tunnel URL periodically and triggers
// DoRestartTunnel after 3 consecutive failures (with a 2-minute cooldown).
func StartTunnelWatcher() {
	// Start passive URL watcher too
	StartURLWatcher()

	go func() {
		time.Sleep(10 * time.Second)
		failCount := 0

		client := &http.Client{
			Timeout: 8 * time.Second,
			CheckRedirect: func(req *http.Request, via []*http.Request) error {
				return http.ErrUseLastResponse
			},
		}

		for {
			time.Sleep(15 * time.Second)

			url := GetActiveURL()
			if url == "" || strings.Contains(url, "localhost") || strings.Contains(url, "127.0.0.1") {
				Status.mu.Lock()
				Status.Online = false
				Status.Error = "NO_URL"
				Status.mu.Unlock()
				continue
			}

			t0 := time.Now()
			resp, err := client.Head(url)
			latency := int(time.Since(t0).Milliseconds())

			if err == nil && resp.StatusCode < 530 {
				resp.Body.Close()
				failCount = 0
				Status.mu.Lock()
				Status.Online = true
				Status.PingMS = latency
				Status.Error = ""
				Status.URL = url
				Status.mu.Unlock()
			} else if err == nil && resp.StatusCode >= 530 {
				// Tunnel-level failure (HTTP 530 = Cloudflare error page, specifically Error
				// 1033 "Cloudflare Tunnel error / Argo Tunnel error"). Auto-restart is delegated to cf-manager
				// (single source of truth with rate-limit cooldown); go-monitor only
				// tracks online/offline telemetry and raises alerts.
				resp.Body.Close()
				Status.mu.Lock()
				Status.Online = false
				Status.PingMS = 0
				Status.URL = url
				if resp.StatusCode == 530 {
					Status.Error = "CLOUDFLARE_ERROR_1033"
				} else if edgeOK, known := tunnelEdgeState(metricsHTTP); known && !edgeOK {
					Status.Error = "EDGE_DISCONNECTED_1033"
				} else {
					Status.Error = fmt.Sprintf("HTTP_%d", resp.StatusCode)
				}
				Status.mu.Unlock()
				failCount++
				log.Printf("[Tunnel] ⚠️  Tunnel status: OFFLINE (%s) — managed by cf-manager", Status.Error)
			} else {
				failCount++
				errStr := classifyError(err, resp)

				Status.mu.Lock()
				Status.Online = false
				Status.PingMS = 0
				Status.Error = errStr
				Status.URL = url
				Status.mu.Unlock()

				log.Printf("[Tunnel] ⚠️  Tunnel status: OFFLINE (%s) — managed by cf-manager", errStr)
			}
		}
	}()
}

func classifyError(err error, resp *http.Response) string {
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
	if resp != nil {
		return fmt.Sprintf("HTTP_%d", resp.StatusCode)
	}
	return "UNKNOWN"
}
