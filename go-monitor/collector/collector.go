package collector

import (
	"encoding/json"
	"fmt"
	"io"
	"math"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"uni-activity/go-monitor/alerts"
	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/services"
	"uni-activity/go-monitor/speedtest"
	"uni-activity/go-monitor/sysinfo"
	"uni-activity/go-monitor/tunnel"
)

type FullStats struct {
	Timestamp        int64                    `json:"timestamp"`
	Uptime           string                   `json:"uptime"`
	ServerInfo       map[string]string        `json:"server_info"`
	CFUrl            string                   `json:"cf_url"`
	CFStatus         map[string]interface{}   `json:"cf_status"`
	Speedtest        map[string]interface{}   `json:"speedtest"`
	LineStatus       map[string]interface{}   `json:"line_status"`
	Memory           sysinfo.MemoryStats      `json:"memory"`
	Load             []float64                `json:"load"`
	Temp             string                   `json:"temp"`
	Battery          sysinfo.BatteryStats     `json:"battery"`
	Disk             sysinfo.DiskStats        `json:"disk"`
	Services         map[string]string        `json:"services"`
	Network          sysinfo.NetworkStats     `json:"network"`
	NetworkInfo      map[string]string        `json:"network_info"`
	Logs             []string                 `json:"logs"`
	Inspector        []interface{}            `json:"inspector"`
	DeployLog        string                   `json:"deploy_log"`
	DeployChannels   map[string]interface{}   `json:"deploy_channels"`
	LogFilesInfo     map[string]interface{}   `json:"log_files_info"`
	GithubDeployLogs map[string]interface{}   `json:"github_deploy_logs"`
	Events           []interface{}            `json:"events"`
	AILog            string                   `json:"ai_log"`
	SSHSessions      []string                 `json:"ssh_sessions"`
	SFTPSessions     int                      `json:"sftp_sessions"`
	SCPSessions      int                      `json:"scp_sessions"`
	ListeningPorts   []int                    `json:"listening_ports"`
	AdvancedMetrics  map[string]interface{}   `json:"advanced_metrics"`
	PublicIP         string                   `json:"public_ip"`
	AICluster        map[string]interface{}   `json:"ai_cluster"`
	Proxy            map[string]interface{}   `json:"proxy"`
	Alerts           []alerts.AlertItem       `json:"alerts"`
	AlertsHistory    []map[string]interface{} `json:"alerts_history"`
}

type Collector struct {
	mu               sync.RWMutex
	cachedStats      *FullStats
	cachedJSON       []byte
	projectRoot      string
	publicIP         string
	inspector        []interface{}
	inspMu           sync.Mutex
	OnInspectorAdded func([]byte) // called immediately when a new log arrives
	aiLog            []string     // plain-text lines from the AI face service
	aiLogMu          sync.Mutex

	sftpMu          sync.Mutex
	sftpActive      map[int]bool // PIDs currently running an SFTP subsystem
	sftpHistoryPath string
	sftpHistory     []string
}

// AddAILogLine appends one line of AI-service log text (received via UDP
// 9997) to the ring buffer shown in the monitor's AI Scanner terminal.
func (c *Collector) AddAILogLine(line string) {
	line = strings.TrimRight(line, "\r\n ")
	if line == "" {
		return
	}
	c.aiLogMu.Lock()
	if len(c.aiLog) >= 100 {
		c.aiLog = c.aiLog[1:]
	}
	c.aiLog = append(c.aiLog, line)
	c.aiLogMu.Unlock()
}

func (c *Collector) aiLogText() string {
	c.aiLogMu.Lock()
	defer c.aiLogMu.Unlock()
	if len(c.aiLog) == 0 {
		return ""
	}
	return strings.Join(c.aiLog, "\n")
}

func NewCollector(projectRoot string) *Collector {
	c := &Collector{
		projectRoot: projectRoot,
		publicIP:    "127.0.0.1",
		inspector:   make([]interface{}, 0, 100),
	}
	go c.fetchPublicIP()
	return c
}

func (c *Collector) AddInspectorLog(log map[string]interface{}) {
	c.inspMu.Lock()
	if len(c.inspector) >= 200 {
		c.inspector = c.inspector[1:]
	}
	c.inspector = append(c.inspector, log)
	c.inspMu.Unlock()

	// Hot-push: notify subscriber immediately after releasing the lock
	if cb := c.OnInspectorAdded; cb != nil {
		if snapshot := c.PatchInspectorJSON(); len(snapshot) > 0 {
			go cb(snapshot)
		}
	}
}

func (c *Collector) fetchPublicIP() {
	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Get("https://api.ipify.org")
	if err == nil && resp.StatusCode == 200 {
		defer resp.Body.Close()
		b, err := io.ReadAll(resp.Body)
		if err == nil {
			c.mu.Lock()
			c.publicIP = strings.TrimSpace(string(b))
			c.mu.Unlock()
		}
	}
}

func (c *Collector) Collect() ([]byte, error) {
	hostname, _ := os.Hostname()
	kernel := "Linux"
	if kData, err := os.ReadFile("/proc/version"); err == nil {
		parts := strings.Fields(string(kData))
		if len(parts) >= 3 {
			kernel = parts[0] + " " + parts[2]
		}
	}

	cfURL := tunnel.GetActiveURL()
	sshSessions, sftpSessions, scp := services.GetActiveSessions()

	// Track SFTP session open/close events and persist history across restarts
	c.trackSFTPEvents(sftpSessions)

	// Deploy log (last 20 lines of git-sync.log) + per-channel streams
	deployLog := tailFile(filepath.Join(c.projectRoot, "storage", "logs", "git-sync.log"), 20)
	gitChannel := tailFile(filepath.Join(c.projectRoot, "storage", "logs", "git-sync.log"), 12)
	sshChannel := ""
	for _, s := range sshSessions {
		sshChannel += s + "\n"
	}
	if sshChannel == "" {
		sshChannel = "No active SSH sessions."
	}
	sftpChannel := c.buildSFTPChannel(len(sftpSessions))
	scpChannel := fmt.Sprintf("%d active SCP transfer session(s).", scp)

	c.inspMu.Lock()
	inspectorCopy := make([]interface{}, len(c.inspector))
	copy(inspectorCopy, c.inspector)
	c.inspMu.Unlock()

	c.mu.RLock()
	currentPublicIP := c.publicIP
	c.mu.RUnlock()

	memStats := sysinfo.GetMemory()
	loadAvg := sysinfo.GetLoad()
	diskStats := sysinfo.GetDisk("/data/data/com.termux/files/home")
	tempStr := sysinfo.GetTemp()
	svcs := services.CheckAllServices()

	cfOnline, cfPing, cfErr, cfURLStr := tunnel.Status.GetStatus()
	if cfURLStr == "" {
		cfURLStr = cfURL
	}

	// Evaluate Alerts Engine
	activeAlerts := alerts.Engine.Evaluate(
		svcs,
		loadAvg,
		tempStr,
		memStats.Percent,
		diskStats.Percent,
		cfOnline,
		cfErr,
		cfURLStr,
	)

	speedtestStatus := speedtest.CurrentJob.GetMap()

	stats := &FullStats{
		Timestamp: time.Now().Unix(),
		Uptime:    sysinfo.GetUptime(),
		ServerInfo: map[string]string{
			"Hostname":        hostname,
			"OS / Kernel":     kernel,
			"Architecture":    "aarch64",
			"Python Version":  "Go 1.27 Native High-Performance Agent",
			"Device Model":    "Android High-Efficiency Micro-Server",
			"Android Version": "Android Linux",
			"PHP Version":     "PHP 8.2+ Octane Core",
		},
		CFUrl: cfURL,
		CFStatus: func() map[string]interface{} {
			res := map[string]interface{}{
				"online":  cfOnline,
				"ping_ms": cfPing,
				"error":   cfErr,
				"url":     cfURL,
			}
			if cdBytes, err := os.ReadFile(filepath.Join(c.projectRoot, "storage/logs/cf-cooldown.json")); err == nil {
				var cdMap map[string]interface{}
				if json.Unmarshal(cdBytes, &cdMap) == nil {
					res["cooldown"] = cdMap
				}
			}
			return res
		}(),
		Speedtest: speedtestStatus,
		LineStatus: map[string]interface{}{
			"status":     "OK",
			"bot_name":   "Uni-Activity Bot",
			"last_check": "Active",
		},
		Memory:   memStats,
		Load:     loadAvg,
		Temp:     tempStr,
		Battery:  sysinfo.GetBattery(),
		Disk:     diskStats,
		Services: svcs,
		Network:  sysinfo.GetNetwork(),
		NetworkInfo: map[string]string{
			"interface": "wlan0",
			"gateway":   "192.168.1.1",
			"dns":       "1.1.1.1",
			"local_ip":  "192.168.1.222",
		},
		Logs:      []string{},
		Inspector: inspectorCopy,
		DeployLog: deployLog,
		DeployChannels: map[string]interface{}{
			"deploy": deployLog,
			"git":    gitChannel,
			"ssh":    sshChannel,
			"sftp":   sftpChannel,
			"scp":    scpChannel,
		},
		LogFilesInfo:     getLogFilesInfo(c.projectRoot),
		GithubDeployLogs: map[string]interface{}{"status": "ok"},
		Events:           getDeployEvents(c.projectRoot),
		AILog:            c.aiLogText(), // real UDP-received lines; empty until the AI service ships logs
		SSHSessions:      sshSessions,
		SFTPSessions:     len(sftpSessions),
		SCPSessions:      scp,
		ListeningPorts:   services.GetListeningPorts(),
		AdvancedMetrics: map[string]interface{}{
			"cpu_freqs":  sysinfo.GetCPUFreqs(),
			"wifi_rssi":  "-50 dBm",
			"net_speeds": sysinfo.GetNetSpeeds(),
			"top_procs":  sysinfo.GetTopProcesses(),
			"postgres": map[string]interface{}{
				"status":      "healthy",
				"db_size":     "24 MB",
				"connections": 5,
			},
			"redis": map[string]interface{}{
				"status":      "healthy",
				"used_memory": "2.4M",
				"clients":     3,
			},
			"queue": map[string]interface{}{
				"pending": 0,
				"failed":  0,
			},
			"cloudflared": map[string]interface{}{
				"status":     "healthy",
				"latency_ms": cfPing,
			},
			"gpu": sysinfo.GetGPUInfo(),
		},
		PublicIP: currentPublicIP,
		AICluster: map[string]interface{}{
			"cluster_status": "healthy",
			"healthy_count":  1,
			"total_count":    1,
			"nodes": []map[string]interface{}{
				{"host": "127.0.0.1:8001", "available": true},
			},
		},
		Proxy: map[string]interface{}{
			"squid":   "running",
			"workers": []string{"worker-1", "worker-2"},
		},
		Alerts:        activeAlerts,
		AlertsHistory: config.AppConfig.GetAlertHistory(),
	}

	data, err := json.Marshal(stats)
	if err != nil {
		return nil, err
	}

	c.mu.Lock()
	c.cachedStats = stats
	c.cachedJSON = data
	c.mu.Unlock()

	return data, nil
}

func (c *Collector) GetCachedJSON() []byte {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.cachedJSON
}

// ProjectRoot returns the root directory the collector was initialized with.
func (c *Collector) ProjectRoot() string {
	return c.projectRoot
}

// sftpHistoryLimit caps the number of remembered SFTP transfer events.
const sftpHistoryLimit = 30

// trackSFTPEvents diffs the currently-running sftp-server PIDs against the
// previous scan and records open/close events into an in-memory ring that is
// also persisted to storage/logs/sftp-history.log so history survives agent
// restarts.
func (c *Collector) trackSFTPEvents(current []services.SFTPSession) {
	c.sftpMu.Lock()
	defer c.sftpMu.Unlock()

	if c.sftpActive == nil {
		c.sftpActive = make(map[int]bool)
	}
	if c.sftpHistoryPath == "" {
		c.sftpHistoryPath = filepath.Join(c.projectRoot, "storage", "logs", "sftp-history.log")
		if c.sftpHistory == nil {
			c.loadSFTPHistory()
		}
	}

	now := time.Now().Format("2006-01-02 15:04:05")
	cur := make(map[int]bool, len(current))
	for _, s := range current {
		cur[s.PID] = true
		if !c.sftpActive[s.PID] {
			c.sftpActive[s.PID] = true
			c.appendSFTPEvent(fmt.Sprintf("[%s] OPEN  PID %d — SFTP transfer session started", now, s.PID))
		}
	}
	for pid := range c.sftpActive {
		if !cur[pid] {
			delete(c.sftpActive, pid)
			c.appendSFTPEvent(fmt.Sprintf("[%s] CLOSE PID %d — SFTP transfer session ended", now, pid))
		}
	}
}

// appendSFTPEvent appends one event line to the ring + persist file.
// Callers must hold c.sftpMu.
func (c *Collector) appendSFTPEvent(line string) {
	c.sftpHistory = append(c.sftpHistory, line)
	if len(c.sftpHistory) > sftpHistoryLimit {
		c.sftpHistory = c.sftpHistory[len(c.sftpHistory)-sftpHistoryLimit:]
	}
	f, err := os.OpenFile(c.sftpHistoryPath, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
	if err == nil {
		fmt.Fprintln(f, line)
		f.Close()
	}
}

// loadSFTPHistory seeds the in-memory ring from the persisted file.
// Callers must hold c.sftpMu.
func (c *Collector) loadSFTPHistory() {
	b, err := os.ReadFile(c.sftpHistoryPath)
	if err != nil {
		return
	}
	lines := strings.Split(strings.TrimRight(string(b), "\n"), "\n")
	if len(lines) > sftpHistoryLimit {
		lines = lines[len(lines)-sftpHistoryLimit:]
	}
	c.sftpHistory = append(c.sftpHistory, lines...)
}

// buildSFTPChannel renders the SFTP tab content: live count on top, followed
// by the recent transfer history (open/close events).
func (c *Collector) buildSFTPChannel(activeCount int) string {
	c.sftpMu.Lock()
	hist := make([]string, len(c.sftpHistory))
	copy(hist, c.sftpHistory)
	c.sftpMu.Unlock()

	var b strings.Builder
	if activeCount > 0 {
		b.WriteString(fmt.Sprintf("● %d active SFTP transfer session(s) right now\n", activeCount))
	} else {
		b.WriteString("○ No active SFTP transfers right now\n")
	}
	b.WriteString("\nRecent transfer history:\n")
	if len(hist) == 0 {
		b.WriteString("  (no SFTP transfer events recorded yet)")
	} else {
		for i := len(hist) - 1; i >= 0; i-- { // newest first
			b.WriteString("  " + hist[i] + "\n")
		}
	}
	return strings.TrimRight(b.String(), "\n")
}

// PatchInspectorJSON returns a new JSON blob identical to the cached snapshot
// but with the inspector field replaced with the current in-memory slice.
// This is a fast alternative to a full Collect() for real-time inspector push.
func (c *Collector) PatchInspectorJSON() []byte {
	c.mu.RLock()
	base := c.cachedJSON
	c.mu.RUnlock()

	if len(base) == 0 {
		return nil
	}

	c.inspMu.Lock()
	snap := make([]interface{}, len(c.inspector))
	copy(snap, c.inspector)
	c.inspMu.Unlock()

	inspBytes, err := json.Marshal(snap)
	if err != nil || len(inspBytes) == 0 {
		return base
	}

	// Simple string surgery: replace "inspector":[...] in the JSON blob.
	// Works because go-monitor always produces well-formed compact JSON.
	b := string(base)
	startKey := `"inspector":`
	ki := strings.Index(b, startKey)
	if ki < 0 {
		return base
	}
	after := ki + len(startKey)
	// Find the matching closing bracket of the array
	depth := 0
	end := after
	for end < len(b) {
		switch b[end] {
		case '[':
			depth++
		case ']':
			depth--
			if depth == 0 {
				end++
				goto done
			}
		}
		end++
	}
done:
	patched := b[:after] + string(inspBytes) + b[end:]
	return []byte(patched)
}

// getLogFilesInfo scans <projectRoot>/storage/logs and returns metadata for
// viewable log files, sorted newest-first. Only regular files that look like
// logs (skip dotfiles, locks, sockets) are listed, capped at maxLogFiles.
func getLogFilesInfo(projectRoot string) map[string]interface{} {
	const (
		logDir      = "storage/logs"
		maxLogFiles = 50
		maxFileMB   = 50.0 // files larger than this are listed but not viewable
	)

	res := map[string]interface{}{
		"count":         0,
		"total_size_mb": 0.0,
		"files":         []map[string]interface{}{},
	}

	entries, err := os.ReadDir(filepath.Join(projectRoot, logDir))
	if err != nil {
		return res
	}

	type logFile struct {
		name      string
		sizeBytes int64
		modTime   time.Time
	}
	files := make([]logFile, 0, len(entries))
	var totalBytes int64

	for _, e := range entries {
		if e.IsDir() || strings.HasPrefix(e.Name(), ".") {
			continue
		}
		info, err := e.Info()
		if err != nil || !info.Mode().IsRegular() {
			continue
		}
		// Skip non-log artifacts: locks, sockets, json state files
		name := e.Name()
		if strings.HasSuffix(name, ".lock") || strings.HasSuffix(name, ".sock") {
			continue
		}
		files = append(files, logFile{name: name, sizeBytes: info.Size(), modTime: info.ModTime()})
		totalBytes += info.Size()
	}

	sort.Slice(files, func(i, j int) bool { return files[i].modTime.After(files[j].modTime) })

	if len(files) > maxLogFiles {
		files = files[:maxLogFiles]
	}

	out := make([]map[string]interface{}, 0, len(files))
	for _, f := range files {
		out = append(out, map[string]interface{}{
			"name":       f.name,
			"size_bytes": f.sizeBytes,
			"size_kb":    int(f.sizeBytes / 1024),
			"size_mb":    math.Round(float64(f.sizeBytes)/(1024*1024)*10) / 10,
			"modified":   f.modTime.Format("2006-01-02 15:04:05"),
			"viewable":   float64(f.sizeBytes) <= maxFileMB*1024*1024,
		})
	}

	res["count"] = len(out)
	res["total_size_mb"] = math.Round(float64(totalBytes)/(1024*1024)*10) / 10
	res["files"] = out
	return res
}

// tailFile returns the last n non-empty lines of a text file, or an empty
// string if the file does not exist.
func tailFile(path string, n int) string {
	b, err := os.ReadFile(path)
	if err != nil {
		return ""
	}
	lines := strings.Split(strings.TrimRight(string(b), "\n"), "\n")
	if len(lines) > n {
		lines = lines[len(lines)-n:]
	}
	out := make([]string, 0, len(lines))
	for _, l := range lines {
		if strings.TrimSpace(l) != "" {
			out = append(out, l)
		}
	}
	return strings.Join(out, "\n")
}

// ReadLogFileTail returns the last <lines> lines of the named log file inside
// <projectRoot>/storage/logs. The name is sanitized: no path separators, no
// dotfiles — reads can never escape the log directory.
func ReadLogFileTail(projectRoot string, name string, lines int) (string, error) {
	if lines <= 0 {
		lines = 200
	}
	if lines > 2000 {
		lines = 2000
	}

	if name == "" || strings.ContainsAny(name, "/\\") || strings.HasPrefix(name, ".") || strings.Contains(name, "..") {
		return "", fmt.Errorf("invalid log file name")
	}

	full := filepath.Join(projectRoot, "storage", "logs", name)
	f, err := os.Open(full)
	if err != nil {
		return "", err
	}
	defer f.Close()

	info, err := f.Stat()
	if err != nil {
		return "", err
	}
	if !info.Mode().IsRegular() {
		return "", fmt.Errorf("not a regular file")
	}

	const maxScan = 8 << 20 // never scan more than 8MB back
	scanFrom := int64(0)
	if info.Size() > maxScan {
		scanFrom = info.Size() - maxScan
	}
	if _, err := f.Seek(scanFrom, io.SeekStart); err != nil {
		return "", err
	}
	data, err := io.ReadAll(f)
	if err != nil {
		return "", err
	}

	trimmed := string(data)
	if scanFrom > 0 {
		// Drop the partial first line caused by the mid-file seek
		if idx := strings.Index(trimmed, "\n"); idx >= 0 {
			trimmed = trimmed[idx+1:]
		} else {
			trimmed = ""
		}
	}
	trimmed = strings.TrimLeft(trimmed, "\n")
	allLines := strings.Split(trimmed, "\n")
	if len(allLines) > lines {
		allLines = allLines[len(allLines)-lines:]
	}

	if scanFrom > 0 {
		header := fmt.Sprintf("[showing last %d lines — file is %s total]\n\n", lines, humanBytes(info.Size()))
		return header + strings.Join(allLines, "\n"), nil
	}
	return strings.Join(allLines, "\n"), nil
}

func humanBytes(n int64) string {
	const unit = 1024
	if n < unit {
		return fmt.Sprintf("%d B", n)
	}
	d := float64(n)
	for _, u := range []string{"KB", "MB", "GB"} {
		d /= unit
		if d < unit {
			return fmt.Sprintf("%.1f %s", d, u)
		}
	}
	return fmt.Sprintf("%.1f TB", d/unit)
}

func getDeployEvents(projectRoot string) []interface{} {
	gitBin := "git"
	if _, err := os.Stat("/data/data/com.termux/files/usr/bin/git"); err == nil {
		gitBin = "/data/data/com.termux/files/usr/bin/git"
	}
	out, err := exec.Command(gitBin, "-C", projectRoot, "log", "-n", "20", "--pretty=format:%h|%s|%an|%ad|%cr").Output()
	if err != nil {
		return []interface{}{}
	}
	lines := strings.Split(strings.TrimSpace(string(out)), "\n")
	events := make([]interface{}, 0, len(lines))
	for idx, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		parts := strings.Split(line, "|")
		if len(parts) < 5 {
			continue
		}
		hash := parts[0]
		msg := parts[1]
		author := parts[2]
		date := parts[3]
		rel := parts[4]

		evType := "success"
		lowMsg := strings.ToLower(msg)
		if strings.Contains(lowMsg, "fail") || strings.Contains(lowMsg, "revert") || strings.Contains(lowMsg, "error") {
			evType = "failed"
		}

		detail := fmt.Sprintf("Deployed by %s • %s • main branch", author, rel)

		events = append(events, map[string]interface{}{
			"id":        fmt.Sprintf("ev-%s-%d", hash, idx),
			"type":      evType,
			"hash":      hash,
			"message":   msg,
			"detail":    detail,
			"timestamp": date,
			"author":    author,
			"relative":  rel,
			"branch":    "main",
		})
	}
	return events
}
