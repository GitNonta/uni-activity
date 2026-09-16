package services

import (
	"bufio"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"uni-activity/go-monitor/config"
)

type ServiceStatusMap map[string]string

func CheckAllServices() ServiceStatusMap {
	res := ServiceStatusMap{
		"Nginx (Edge Proxy)":          "Stopped",
		"Web Workers (artisan serve)": "Stopped",
		"Laravel Reverb (WebSocket)":  "Stopped",
		"Datastore (Valkey)":          "Stopped",
		"Queue Store (Valkey)":        "Stopped",
		"PostgreSQL Database":         "Stopped",
		"Queue Worker":                "Stopped",
		"AI Biometrics Face Service":  "Stopped",
		// key the monitor-ui AiScanner card reads (App.jsx serviceStatus)
		"AI Scan Service":             "Stopped",
	}

	// 1. Port checks via fast TCP connect (50ms timeout)
	checkPort := func(port int) bool {
		conn, err := net.DialTimeout("tcp", fmt.Sprintf("127.0.0.1:%d", port), 50*time.Millisecond)
		if err == nil {
			conn.Close()
			return true
		}
		return false
	}

	if checkPort(8080) {
		res["Nginx (Edge Proxy)"] = "Running"
	}
	if checkPort(5432) {
		res["PostgreSQL Database"] = "Running"
	}
	if checkPort(6379) {
		res["Datastore (Valkey)"] = "Running"
	}
	if checkPort(6380) {
		res["Queue Store (Valkey)"] = "Running"
	}
	if checkPort(8082) {
		res["Laravel Reverb (WebSocket)"] = "Running"
	}
	if checkPort(8000) || checkPort(8002) || checkPort(8003) {
		res["Web Workers (artisan serve)"] = "Running"
	}
	if checkPort(8001) {
		res["AI Biometrics Face Service"] = "Running"
		res["AI Scan Service"] = "Running"
	} else if aiRunning() {
		// AI service lives on a remote host (e.g. the GPU machine) — the
		// localhost port probe cannot see it; ask its /health directly.
		res["AI Biometrics Face Service"] = "Running (remote)"
		res["AI Scan Service"] = "Running (remote)"
	}

	// 2. Scan /proc/[0-9]*/cmdline directly for workers that don't bind ports (like Queue Worker)
	entries, err := os.ReadDir("/proc")
	if err == nil {
		for _, e := range entries {
			if !e.IsDir() {
				continue
			}
			pid, err := strconv.Atoi(e.Name())
			if err != nil || pid <= 0 {
				continue
			}
			cmdBytes, err := os.ReadFile(filepath.Join("/proc", e.Name(), "cmdline"))
			if err != nil {
				continue
			}
			cmd := strings.ReplaceAll(string(cmdBytes), "\x00", " ")
			if strings.Contains(cmd, "queue:work") || strings.Contains(cmd, "queue:listen") {
				res["Queue Worker"] = "Running"
			}
			if strings.Contains(cmd, "reverb:start") {
				res["Laravel Reverb (WebSocket)"] = "Running"
			}
			if strings.Contains(cmd, "uvicorn") && strings.Contains(cmd, "8001") {
				res["AI Biometrics Face Service"] = "Running"
				res["AI Scan Service"] = "Running"
			}
		}
	}

	return res
}

// aiRunning asks the AI face service's /health endpoint over HTTP.
// Returns true on HTTP 200 regardless of whether the service runs on this
// host or a remote GPU machine (the localhost port probe can't see remote).
func aiRunning() bool {
	url := strings.TrimSpace(config.AppConfig.AiServiceURL)
	if url == "" {
		url = "http://127.0.0.1:8001"
	}
	if !strings.Contains(url, "://") {
		url = "http://" + url
	}
	client := &http.Client{Timeout: 2 * time.Second}
	resp, err := client.Get(strings.TrimRight(url, "/") + "/health")
	if err != nil {
		return false
	}
	defer resp.Body.Close()
	_, _ = io.Copy(io.Discard, resp.Body)
	return resp.StatusCode == http.StatusOK
}

// GetListeningPorts reads /proc/net/tcp and tcp6 for state 0A (LISTEN)
func GetListeningPorts() []int {
	portSet := make(map[int]struct{})

	parse := func(filename string) {
		f, err := os.Open(filename)
		if err != nil {
			return
		}
		defer f.Close()

		scanner := bufio.NewScanner(f)
		for scanner.Scan() {
			fields := strings.Fields(scanner.Text())
			if len(fields) < 4 {
				continue
			}
			// State 0A is TCP_LISTEN
			if fields[3] != "0A" {
				continue
			}
			addrParts := strings.Split(fields[1], ":")
			if len(addrParts) == 2 {
				p, err := strconv.ParseInt(addrParts[1], 16, 32)
				if err == nil && p > 0 {
					portSet[int(p)] = struct{}{}
				}
			}
		}
	}

	parse("/proc/net/tcp")
	parse("/proc/net/tcp6")

	var ports []int
	for p := range portSet {
		ports = append(ports, p)
	}
	return ports
}

// GetActiveSessions counts SSH/SFTP/SCP sessions by scanning /proc
func GetActiveSessions() (ssh []string, sftpCount int, scpCount int) {
	ssh = make([]string, 0)
	entries, err := os.ReadDir("/proc")
	if err != nil {
		return
	}
	for _, e := range entries {
		if !e.IsDir() {
			continue
		}
		pid, err := strconv.Atoi(e.Name())
		if err != nil || pid <= 0 {
			continue
		}
		cmdBytes, err := os.ReadFile(filepath.Join("/proc", e.Name(), "cmdline"))
		if err != nil {
			continue
		}
		cmd := strings.ReplaceAll(string(cmdBytes), "\x00", " ")
		if strings.Contains(cmd, "sshd:") && !strings.Contains(cmd, "sshd -D") {
			ssh = append(ssh, fmt.Sprintf("PID %d: %s", pid, strings.TrimSpace(cmd)))
		}
		if strings.Contains(cmd, "sftp-server") {
			sftpCount++
		}
		if strings.Contains(cmd, "scp ") {
			scpCount++
		}
	}
	return
}
