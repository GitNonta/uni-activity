package proxy

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"

	"uni-activity/go-monitor/config"
)

type BlockItem struct {
	ID        string `json:"id"`
	Target    string `json:"target"`
	Type      string `json:"type"` // "domain" | "ip"
	Reason    string `json:"reason"`
	CreatedAt string `json:"created_at"`
	Enabled   bool   `json:"enabled"`
}

type BlocklistData struct {
	BlockedDomains []string    `json:"blocked_domains"`
	BlockedIPs     []string    `json:"blocked_ips"`
	Items          []BlockItem `json:"items"`
	UpdatedAt      int64       `json:"updated_at"`
}

var mu sync.Mutex

var ipRegex = regexp.MustCompile(`^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$`)
var cidrRegex = regexp.MustCompile(`^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}$`)

func NormalizeTarget(raw string) (string, string) {
	t := strings.TrimSpace(raw)
	if t == "" {
		return "", "domain"
	}

	if cidrRegex.MatchString(t) {
		return t, "ip"
	}

	host := t
	if strings.Contains(t, "://") {
		if u, err := url.Parse(t); err == nil {
			host = u.Hostname()
		}
	} else if strings.Contains(t, "/") {
		if u, err := url.Parse("http://" + t); err == nil {
			host = u.Hostname()
		}
	}

	if strings.Contains(host, ":") && !strings.HasPrefix(host, "[") {
		parts := strings.Split(host, ":")
		host = parts[0]
	}

	host = strings.ToLower(strings.Trim(host, "/."))
	host = strings.TrimPrefix(host, "*.")

	if host == "" {
		return "", "domain"
	}

	if ipRegex.MatchString(host) {
		return host, "ip"
	}

	return host, "domain"
}

func getBlocklistPath() string {
	return filepath.Join(config.AppConfig.ProjectRoot, "storage", "proxy_blocklist.json")
}

func LoadBlocklist() BlocklistData {
	mu.Lock()
	defer mu.Unlock()

	p := getBlocklistPath()
	var data BlocklistData

	if content, err := os.ReadFile(p); err == nil {
		if err := json.Unmarshal(content, &data); err == nil {
			return data
		}
	}

	// Default template
	data = BlocklistData{
		BlockedDomains: []string{"tiktok.com", "doubleclick.net"},
		BlockedIPs:     []string{},
		Items: []BlockItem{
			{
				ID:        "blk-1",
				Target:    "tiktok.com",
				Type:      "domain",
				Reason:    "Social media video streaming bandwidth restriction",
				CreatedAt: "2026-09-04 16:45:00",
				Enabled:   true,
			},
			{
				ID:        "blk-2",
				Target:    "doubleclick.net",
				Type:      "domain",
				Reason:    "Tracking and telemetry banner blocker",
				CreatedAt: "2026-09-04 16:45:00",
				Enabled:   true,
			},
		},
		UpdatedAt: time.Now().Unix(),
	}

	_ = saveBlocklistLocked(data)
	return data
}

func SaveBlocklist(data BlocklistData) error {
	mu.Lock()
	defer mu.Unlock()
	return saveBlocklistLocked(data)
}

func saveBlocklistLocked(data BlocklistData) error {
	// Rebuild active domains and ips from enabled items
	activeDomains := make([]string, 0)
	activeIPs := make([]string, 0)
	cleanedItems := make([]BlockItem, 0)

	for _, it := range data.Items {
		cleanTarget, detectedType := NormalizeTarget(it.Target)
		if cleanTarget == "" {
			continue
		}
		it.Target = cleanTarget
		if it.Type != "domain" && it.Type != "ip" {
			it.Type = detectedType
		}
		cleanedItems = append(cleanedItems, it)

		if it.Enabled {
			if it.Type == "ip" {
				activeIPs = append(activeIPs, cleanTarget)
			} else {
				activeDomains = append(activeDomains, cleanTarget)
			}
		}
	}

	data.Items = cleanedItems
	data.BlockedDomains = activeDomains
	data.BlockedIPs = activeIPs
	data.UpdatedAt = time.Now().Unix()

	p := getBlocklistPath()
	_ = os.MkdirAll(filepath.Dir(p), 0755)

	content, err := json.MarshalIndent(data, "", "  ")
	if err != nil {
		return err
	}
	_ = os.WriteFile(p, content, 0644)

	// Sync to Squid files
	syncSquidFiles(activeDomains, activeIPs)
	return nil
}

func syncSquidFiles(domains, ips []string) {
	squidDomainFile := "/data/data/com.termux/files/usr/etc/squid/blocked_domains.txt"
	squidIPFile := "/data/data/com.termux/files/usr/etc/squid/blocked_ips.txt"

	_ = os.MkdirAll(filepath.Dir(squidDomainFile), 0755)

	var dLines []string
	for _, d := range domains {
		clean, _ := NormalizeTarget(d)
		if clean != "" {
			dLines = append(dLines, "."+strings.TrimPrefix(clean, "."))
		}
	}
	_ = os.WriteFile(squidDomainFile, []byte(strings.Join(dLines, "\n")+"\n"), 0644)

	ipLines := ips
	if len(ipLines) == 0 {
		ipLines = []string{"0.0.0.0/32"}
	}
	_ = os.WriteFile(squidIPFile, []byte(strings.Join(ipLines, "\n")+"\n"), 0644)

	// Reconfigure squid
	_ = exec.Command("squid", "-k", "reconfigure").Run()
}

func randomID() string {
	b := make([]byte, 4)
	_, _ = rand.Read(b)
	return "blk-" + hex.EncodeToString(b)
}

// HTTP Handlers

func HandleGetBlocklist(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	data := LoadBlocklist()
	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":        true,
		"blocklist": data,
	})
}

func HandleAddBlocklist(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var req struct {
		Target string `json:"target"`
		Type   string `json:"type"`
		Reason string `json:"reason"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Target == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ok":    false,
			"error": "เป้าหมาย (Domain/IP/URL) ไม่ถูกต้องหรือว่างเปล่า",
		})
		return
	}

	cleanTarget, detectedType := NormalizeTarget(req.Target)
	if cleanTarget == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ok":    false,
			"error": "เป้าหมาย (Domain/IP/URL) ไม่ถูกต้อง",
		})
		return
	}

	targetType := req.Type
	if targetType != "domain" && targetType != "ip" {
		targetType = detectedType
	}

	data := LoadBlocklist()

	// Check if already exists
	for i, it := range data.Items {
		c, _ := NormalizeTarget(it.Target)
		if c == cleanTarget {
			if !it.Enabled {
				data.Items[i].Enabled = true
				if req.Reason != "" {
					data.Items[i].Reason = req.Reason
				}
				_ = SaveBlocklist(data)
				json.NewEncoder(w).Encode(map[string]interface{}{
					"ok":        true,
					"item":      data.Items[i],
					"blocklist": data,
				})
				return
			}
			w.WriteHeader(http.StatusBadRequest)
			json.NewEncoder(w).Encode(map[string]interface{}{
				"ok":    false,
				"error": fmt.Sprintf("'%s' มีอยู่ในรายการบล็อคแล้ว", cleanTarget),
			})
			return
		}
	}

	newItem := BlockItem{
		ID:        randomID(),
		Target:    cleanTarget,
		Type:      targetType,
		Reason:    req.Reason,
		CreatedAt: time.Now().Format("2006-01-02 15:04:05"),
		Enabled:   true,
	}
	if newItem.Reason == "" {
		newItem.Reason = "Administrative block"
	}

	data.Items = append(data.Items, newItem)
	_ = SaveBlocklist(data)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":        true,
		"item":      newItem,
		"blocklist": data,
	})
}

func HandleRemoveBlocklist(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var req struct {
		ID     string `json:"id"`
		Target string `json:"target"`
	}
	_ = json.NewDecoder(r.Body).Decode(&req)

	targetID := req.ID
	if targetID == "" {
		targetID = req.Target
	}
	if targetID == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ok":    false,
			"error": "Target or ID cannot be empty",
		})
		return
	}

	cleanTarget, _ := NormalizeTarget(targetID)
	data := LoadBlocklist()

	var removed *BlockItem
	var newItems []BlockItem

	for _, it := range data.Items {
		c, _ := NormalizeTarget(it.Target)
		if strings.EqualFold(it.ID, targetID) || strings.EqualFold(it.Target, targetID) || (cleanTarget != "" && c == cleanTarget) {
			itemCopy := it
			removed = &itemCopy
		} else {
			newItems = append(newItems, it)
		}
	}

	if removed == nil {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ok":    false,
			"error": fmt.Sprintf("ไม่พบรายการ '%s' ในระบบ", targetID),
		})
		return
	}

	data.Items = newItems
	_ = SaveBlocklist(data)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":        true,
		"removed":   removed,
		"blocklist": data,
	})
}

func HandleToggleBlocklist(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var req struct {
		ID     string `json:"id"`
		Target string `json:"target"`
	}
	_ = json.NewDecoder(r.Body).Decode(&req)

	targetID := req.ID
	if targetID == "" {
		targetID = req.Target
	}

	cleanTarget, _ := NormalizeTarget(targetID)
	data := LoadBlocklist()

	var found *BlockItem
	for i, it := range data.Items {
		c, _ := NormalizeTarget(it.Target)
		if strings.EqualFold(it.ID, targetID) || strings.EqualFold(it.Target, targetID) || (cleanTarget != "" && c == cleanTarget) {
			data.Items[i].Enabled = !data.Items[i].Enabled
			found = &data.Items[i]
			break
		}
	}

	if found == nil {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ok":    false,
			"error": fmt.Sprintf("ไม่พบรายการ '%s' ในระบบ", targetID),
		})
		return
	}

	_ = SaveBlocklist(data)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":        true,
		"item":      found,
		"blocklist": data,
	})
}

func HandleTraffic(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":        true,
		"timestamp": time.Now().Unix(),
		"traffic": map[string]interface{}{
			"requests": 240,
			"kbytes":   15400,
			"hits":     210,
			"errors":   0,
		},
		"connections": map[string]interface{}{
			"active":  2,
			"idle":    8,
			"max_fd":  1024,
			"used_fd": 32,
		},
		"security": map[string]interface{}{
			"blocked_requests": 0,
			"status":           "active",
		},
		"recent_traffic":   []interface{}{},
		"device_breakdown": []interface{}{},
	})
}

type ChannelResult struct {
	OK         bool    `json:"ok"`
	StatusCode int     `json:"status_code"`
	StatusText string  `json:"status_text"`
	Result     string  `json:"result"` // success, blocked, warning, timeout, error
	LatencyMS  float64 `json:"latency_ms"`
	RemoteIP   string  `json:"remote_ip"`
	Error      *string `json:"error"`
}

func testSingleChannel(targetURL, mode string, timeoutSec int) ChannelResult {
	if !strings.HasPrefix(targetURL, "http://") && !strings.HasPrefix(targetURL, "https://") {
		targetURL = "https://" + targetURL
	}

	cmdArgs := []string{
		"-s", "-S",
		"-o", "/dev/null",
		"-w", "%{http_code} %{time_total} %{remote_ip}",
		"--connect-timeout", strconv.Itoa(timeoutSec),
		"--max-time", strconv.Itoa(timeoutSec * 2),
	}

	if mode == "squid" {
		cmdArgs = append(cmdArgs, "-x", "http://127.0.0.1:3128")
	} else if mode == "socks5" {
		cmdArgs = append(cmdArgs, "-x", "socks5h://127.0.0.1:1080")
	}
	cmdArgs = append(cmdArgs, targetURL)

	t0 := time.Now()
	cmd := exec.Command("curl", cmdArgs...)
	out, err := cmd.CombinedOutput()
	durationMS := float64(time.Since(t0).Microseconds()) / 1000.0

	outStr := strings.TrimSpace(string(out))
	if err == nil && outStr != "" {
		parts := strings.Fields(outStr)
		code := 0
		remoteIP := "-"
		if len(parts) > 0 {
			code, _ = strconv.Atoi(parts[0])
		}
		if len(parts) > 2 {
			remoteIP = parts[2]
		}

		resultTag := "unknown"
		statusText := fmt.Sprintf("Status %d", code)

		if code >= 200 && code < 400 {
			resultTag = "success"
			statusText = "เชื่อมต่อสำเร็จ (OK)"
		} else if code == 403 {
			resultTag = "blocked"
			statusText = "ถูกบล็อคโดยนโยบาย (Blocked/Forbidden)"
		} else if code >= 400 {
			resultTag = "warning"
			statusText = fmt.Sprintf("HTTP %d", code)
		}

		return ChannelResult{
			OK:         true,
			StatusCode: code,
			StatusText: statusText,
			Result:     resultTag,
			LatencyMS:  durationMS,
			RemoteIP:   remoteIP,
			Error:      nil,
		}
	}

	// Analyze error
	errStr := outStr
	if errStr == "" && err != nil {
		errStr = err.Error()
	}
	lower := strings.ToLower(errStr)

	resultTag := "error"
	statusText := "Connection failed"
	if strings.Contains(lower, "403") || strings.Contains(lower, "refused") || strings.Contains(lower, "not allowed") {
		resultTag = "blocked"
		statusText = "ถูกบล็อคโดยนโยบายความปลอดภัย (Blocked / 403)"
	} else if strings.Contains(lower, "timed out") || strings.Contains(lower, "timeout") {
		resultTag = "timeout"
		statusText = "หมดเวลาเชื่อมต่อ (Timeout)"
	} else if strings.Contains(lower, "could not resolve host") {
		resultTag = "error"
		statusText = "ไม่พบโดเมน (DNS Unresolved)"
	}

	return ChannelResult{
		OK:         false,
		StatusCode: 0,
		StatusText: statusText,
		Result:     resultTag,
		LatencyMS:  durationMS,
		RemoteIP:   "-",
		Error:      &errStr,
	}
}

func HandleTestProxies(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var req struct {
		Target  string `json:"target"`
		Timeout int    `json:"timeout"`
	}
	_ = json.NewDecoder(r.Body).Decode(&req)

	targetURL := strings.TrimSpace(req.Target)
	if targetURL == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ok":    false,
			"error": "Missing target URL",
		})
		return
	}

	timeoutSec := req.Timeout
	if timeoutSec <= 0 || timeoutSec > 15 {
		timeoutSec = 5
	}

	var wg sync.WaitGroup
	var directRes, squidRes, socks5Res ChannelResult

	wg.Add(3)
	go func() {
		defer wg.Done()
		directRes = testSingleChannel(targetURL, "direct", timeoutSec)
	}()
	go func() {
		defer wg.Done()
		squidRes = testSingleChannel(targetURL, "squid", timeoutSec)
	}()
	go func() {
		defer wg.Done()
		socks5Res = testSingleChannel(targetURL, "socks5", timeoutSec)
	}()

	wg.Wait()

	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":        true,
		"target":    targetURL,
		"timestamp": time.Now().Unix(),
		"time_str":  time.Now().Format("15:04:05"),
		"results": map[string]ChannelResult{
			"direct": directRes,
			"squid":  squidRes,
			"socks5": socks5Res,
		},
	})
}
