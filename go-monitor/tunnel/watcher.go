package tunnel

import (
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

type TunnelStatus struct {
	mu        sync.RWMutex
	Online    bool   `json:"online"`
	PingMS    int    `json:"ping_ms"`
	Error     string `json:"error"`
	URL       string `json:"url"`
	SSHURL    string `json:"ssh_url"`
	UpdatedAt string `json:"updated_at"`
}

var Status = &TunnelStatus{
	Online: true,
}

func (s *TunnelStatus) GetStatus() (bool, int, string, string) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.Online, s.PingMS, s.Error, s.URL
}

var cfURLRegex = regexp.MustCompile(`https://[a-zA-Z0-9-]+\.trycloudflare\.com`)

// GetActiveURL reads docs/active_url.json or falls back to cloudflared metrics / logs
func GetActiveURL() string {
	Status.mu.RLock()
	cur := Status.URL
	Status.mu.RUnlock()
	if cur != "" {
		return cur
	}

	// 1. Try reading docs/active_url.json
	activePath := filepath.Join(config.AppConfig.ProjectRoot, "docs", "active_url.json")
	if f, err := os.Open(activePath); err == nil {
		defer f.Close()
		var d struct {
			URL string `json:"url"`
		}
		if err := json.NewDecoder(f).Decode(&d); err == nil && d.URL != "" {
			return d.URL
		}
	}

	// 2. Try metrics port 20241
	client := &http.Client{Timeout: 2 * time.Second}
	if resp, err := client.Get("http://127.0.0.1:20241/metrics"); err == nil {
		body, _ := io.ReadAll(resp.Body)
		resp.Body.Close()
		matches := cfURLRegex.FindSubmatch(body)
		if len(matches) > 0 {
			return string(matches[0])
		}
	}

	// 3. Try reading cloudflared.log
	logPath := "/data/data/com.termux/files/home/cloudflared.log"
	if content, err := os.ReadFile(logPath); err == nil {
		matches := cfURLRegex.Find(content)
		if len(matches) > 0 {
			return string(matches)
		}
	}

	return ""
}

// GetSSHURL reads active_url.json or metrics port 20242
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
		if err := json.NewDecoder(f).Decode(&d); err == nil && d.SSHURL != "" {
			return d.SSHURL
		}
	}

	client := &http.Client{Timeout: 2 * time.Second}
	if resp, err := client.Get("http://127.0.0.1:20242/metrics"); err == nil {
		body, _ := io.ReadAll(resp.Body)
		resp.Body.Close()
		matches := cfURLRegex.FindSubmatch(body)
		if len(matches) > 0 {
			return string(matches[0])
		}
	}

	logPath := "/data/data/com.termux/files/home/cloudflared-ssh.log"
	if content, err := os.ReadFile(logPath); err == nil {
		matches := cfURLRegex.Find(content)
		if len(matches) > 0 {
			return string(matches)
		}
	}

	return ""
}

// GetTunnelURLs returns full JSON payload for /api/tunnel-urls
func GetTunnelURLs() map[string]interface{} {
	httpURL := GetActiveURL()
	sshURL := GetSSHURL()

	return map[string]interface{}{
		"http_url":   httpURL,
		"ssh_url":    sshURL,
		"server_lan": "192.168.1.222",
		"ssh_port":   8022,
		"updated_at": time.Now().Format("2006-01-02 15:04:05"),
	}
}

// PushActiveURLToGitHub updates docs/active_url.json on GitHub Pages
func PushActiveURLToGitHub(httpURL, sshURL string) {
	pat := ""
	envPath := filepath.Join(config.AppConfig.ProjectRoot, ".env")
	if content, err := os.ReadFile(envPath); err == nil {
		lines := strings.Split(string(content), "\n")
		for _, line := range lines {
			if strings.HasPrefix(line, "GITHUB_PAT=") {
				pat = strings.Trim(strings.TrimPrefix(line, "GITHUB_PAT="), "\"' \r\n")
				break
			}
		}
	}

	if pat == "" {
		return
	}

	owner := "GitNonta"
	repo := "uni-activity"
	path := "docs/active_url.json"
	apiURL := fmt.Sprintf("https://api.github.com/repos/%s/%s/contents/%s", owner, repo, path)

	client := &http.Client{Timeout: 10 * time.Second}
	req, err := http.NewRequest("GET", apiURL, nil)
	if err != nil {
		return
	}
	req.Header.Set("Authorization", "token "+pat)
	req.Header.Set("Accept", "application/vnd.github.v3+json")
	req.Header.Set("User-Agent", "UniActivity-Monitor-Go")

	var sha string
	if resp, err := client.Do(req); err == nil {
		var res map[string]interface{}
		_ = json.NewDecoder(resp.Body).Decode(&res)
		resp.Body.Close()
		if s, ok := res["sha"].(string); ok {
			sha = s
		}
	}

	contentData, _ := json.MarshalIndent(map[string]interface{}{
		"url":        httpURL,
		"ssh_url":    sshURL,
		"updated_at": time.Now().Format("2006-01-02 15:04:05"),
	}, "", "  ")

	payload := map[string]interface{}{
		"message": fmt.Sprintf("chore: update active tunnel URL to %s [auto-sync]", httpURL),
		"content": base64.StdEncoding.EncodeToString(contentData),
	}
	if sha != "" {
		payload["sha"] = sha
	}

	bodyBytes, _ := json.Marshal(payload)
	putReq, err := http.NewRequest("PUT", apiURL, bytes.NewBuffer(bodyBytes))
	if err != nil {
		return
	}
	putReq.Header.Set("Authorization", "token "+pat)
	putReq.Header.Set("Accept", "application/vnd.github.v3+json")
	putReq.Header.Set("Content-Type", "application/json")
	putReq.Header.Set("User-Agent", "UniActivity-Monitor-Go")

	if putResp, err := client.Do(putReq); err == nil {
		putResp.Body.Close()
		log.Printf("☁️ Active tunnel URL pushed to GitHub Pages (%s)", httpURL)
	}
}

// DoRestartTunnel restarts cloudflared processes and captures new URLs
func DoRestartTunnel() (string, error) {
	log.Println("🔄 Restarting Cloudflare Tunnel...")
	_ = exec.Command("pkill", "-9", "cloudflared").Run()
	time.Sleep(2 * time.Second)

	logHTTP := "/data/data/com.termux/files/home/cloudflared.log"
	logSSH := "/data/data/com.termux/files/home/cloudflared-ssh.log"

	_ = os.WriteFile(logHTTP, []byte(""), 0644)
	_ = os.WriteFile(logSSH, []byte(""), 0644)

	targetURL := config.AppConfig.TunnelTargetURL
	if targetURL == "" {
		targetURL = "http://127.0.0.1:8088"
	}

	// Tunnel 1: HTTP -> Load balancer :8088
	cmd1 := exec.Command("sh", "-c", fmt.Sprintf("nohup cloudflared tunnel --url %s --no-autoupdate > %s 2>&1 &", targetURL, logHTTP))
	if err := cmd1.Start(); err != nil {
		log.Printf("⚠️ Failed to start HTTP tunnel: %v", err)
	}
	time.Sleep(1 * time.Second)

	// Tunnel 2: SSH -> :80
	cmd2 := exec.Command("sh", "-c", fmt.Sprintf("nohup cloudflared tunnel --url http://127.0.0.1:80 --no-autoupdate > %s 2>&1 &", logSSH))
	if err := cmd2.Start(); err != nil {
		log.Printf("⚠️ Failed to start SSH tunnel: %v", err)
	}

	var newURL string
	var sshURL string

	for i := 0; i < 40; i++ {
		time.Sleep(1 * time.Second)
		if newURL == "" {
			if content, err := os.ReadFile(logHTTP); err == nil {
				m := cfURLRegex.Find(content)
				if len(m) > 0 {
					newURL = string(m)
				}
			}
		}
		if sshURL == "" {
			if content, err := os.ReadFile(logSSH); err == nil {
				m := cfURLRegex.Find(content)
				if len(m) > 0 {
					sshURL = string(m)
				}
			}
		}
		if newURL != "" && sshURL != "" {
			break
		}
	}

	if newURL != "" {
		Status.mu.Lock()
		Status.URL = newURL
		Status.SSHURL = sshURL
		Status.Online = true
		Status.UpdatedAt = time.Now().Format("2006-01-02 15:04:05")
		Status.mu.Unlock()

		// Update .env
		envPath := filepath.Join(config.AppConfig.ProjectRoot, ".env")
		if content, err := os.ReadFile(envPath); err == nil {
			lines := strings.Split(string(content), "\n")
			for i, line := range lines {
				if strings.HasPrefix(line, "APP_URL=") {
					lines[i] = fmt.Sprintf("APP_URL=%s", newURL)
				}
			}
			_ = os.WriteFile(envPath, []byte(strings.Join(lines, "\n")), 0644)
		}

		// Update docs/active_url.json
		jsonPath := filepath.Join(config.AppConfig.ProjectRoot, "docs", "active_url.json")
		_ = os.MkdirAll(filepath.Dir(jsonPath), 0755)
		data, _ := json.MarshalIndent(map[string]interface{}{
			"url":        newURL,
			"ssh_url":    sshURL,
			"updated_at": time.Now().Format("2006-01-02 15:04:05"),
		}, "", "  ")
		_ = os.WriteFile(jsonPath, data, 0644)

		go PushActiveURLToGitHub(newURL, sshURL)
		telegram.Send(fmt.Sprintf("🌐 <b>Cloudflare Tunnel Restarted</b>\n━━━━━━━━━━━━━━━━━━━━\n🔗 <b>URL:</b> %s\n🔒 <b>SSH:</b> %s", newURL, sshURL))
		return newURL, nil
	}

	return "", fmt.Errorf("timeout waiting for new tunnel URL")
}

// StartTunnelWatcher runs periodic health-check for the active Cloudflare Tunnel
func StartTunnelWatcher() {
	go func() {
		time.Sleep(10 * time.Second)
		failCount := 0
		lastRestartTime := time.Time{}

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

			// Extract domain
			domain := strings.TrimPrefix(url, "https://")
			domain = strings.TrimPrefix(domain, "http://")
			if idx := strings.Index(domain, "/"); idx != -1 {
				domain = domain[:idx]
			}

			t0 := time.Now()
			client := &http.Client{
				Timeout: 8 * time.Second,
				CheckRedirect: func(req *http.Request, via []*http.Request) error {
					return http.ErrUseLastResponse // don't follow redirect
				},
			}

			resp, err := client.Head(url)
			latency := int(time.Since(t0).Milliseconds())

			if err == nil && resp.StatusCode < 500 {
				resp.Body.Close()
				failCount = 0
				Status.mu.Lock()
				Status.Online = true
				Status.PingMS = latency
				Status.Error = ""
				Status.URL = url
				Status.mu.Unlock()
			} else {
				failCount++
				errStr := "HTTP_ERROR"
				if err != nil {
					if strings.Contains(err.Error(), "timeout") {
						errStr = "TIMEOUT"
					} else if strings.Contains(err.Error(), "certificate") || strings.Contains(err.Error(), "tls") {
						errStr = "SSL_ERROR"
					} else {
						errStr = "CONN_REFUSED"
					}
				} else {
					errStr = fmt.Sprintf("HTTP_%d", resp.StatusCode)
				}

				Status.mu.Lock()
				Status.Online = false
				Status.PingMS = 0
				Status.Error = errStr
				Status.URL = url
				Status.mu.Unlock()

				// Auto restart after 3 failures and 120s cooldown
				if failCount >= 3 && time.Since(lastRestartTime) > 120*time.Second {
					lastRestartTime = time.Now()
					telegram.Send(fmt.Sprintf("⚠️ <b>Cloudflare Tunnel Offline (%s)</b> — Triggering auto-restart...", errStr))
					go DoRestartTunnel()
				}
			}
		}
	}()
}
