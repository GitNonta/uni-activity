package speedtest

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"math"
	"net"
	"net/http"
	"os/exec"
	"regexp"
	"strconv"
	"sync"
	"time"
)

type SpeedtestJob struct {
	mu           sync.RWMutex
	Status       string  `json:"status"` // "idle", "running", "done", "error"
	Stage        string  `json:"stage"`  // "ping", "upload", "download", "Complete"
	Ping         float64 `json:"ping"`
	Jitter       float64 `json:"jitter"`
	PingMin      float64 `json:"ping_min"`
	PingMax      float64 `json:"ping_max"`
	DownloadMbps float64 `json:"download"`
	UploadMbps   float64 `json:"upload"`
	LastTest     int64   `json:"last_test"`
	Error        *string `json:"error"`
}

var CurrentJob = &SpeedtestJob{
	Status: "idle",
	Stage:  "Ready",
}

func (j *SpeedtestJob) GetMap() map[string]interface{} {
	j.mu.RLock()
	defer j.mu.RUnlock()
	return map[string]interface{}{
		"status":        j.Status,
		"stage":         j.Stage,
		"ping_ms":       j.Ping,
		"jitter_ms":     j.Jitter,
		"download_mbps": j.DownloadMbps,
		"upload_mbps":   j.UploadMbps,
		"last_test":     j.LastTest,
	}
}

// HandleUpload handles /api/st/upload by discarding incoming bytes
func HandleUpload(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "application/json")

	n, _ := io.Copy(io.Discard, r.Body)

	resp, _ := json.Marshal(map[string]interface{}{
		"received_bytes": n,
	})
	w.Write(resp)
}

// HandleDownload handles /api/st/download by streaming in-memory bytes
func HandleDownload(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "application/octet-stream")

	sizeStr := r.URL.Query().Get("size")
	size := 100 * 1024 * 1024 // 100 MB default
	if s, err := strconv.Atoi(sizeStr); err == nil && s > 0 {
		if s > 256*1024*1024 {
			s = 256 * 1024 * 1024
		}
		size = s
	}

	w.Header().Set("Content-Length", strconv.Itoa(size))

	// Reusable 64KB dummy chunk
	chunk := make([]byte, 65536)
	for i := range chunk {
		chunk[i] = byte(i % 256)
	}

	sent := 0
	for sent < size {
		toSend := len(chunk)
		if size-sent < toSend {
			toSend = size - sent
		}
		n, err := w.Write(chunk[:toSend])
		if err != nil {
			break
		}
		sent += n
	}
}

var pingRegex = regexp.MustCompile(`time[=<]([\d.]+)\s*ms`)

// HandleLANPing tests ping to target using ICMP -> TCP Dial -> HTTP
func HandleLANPing(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "application/json")

	target := r.URL.Query().Get("target")
	if target == "" {
		target = "192.168.1.45"
	}
	countStr := r.URL.Query().Get("count")
	count := 10
	if c, err := strconv.Atoi(countStr); err == nil && c > 0 && c <= 20 {
		count = c
	}

	// 1. Layer 1: Try ICMP ping
	if out, err := exec.Command("ping", "-c", strconv.Itoa(count), "-W", "1", target).Output(); err == nil {
		matches := pingRegex.FindAllStringSubmatch(string(out), -1)
		if len(matches) > 0 {
			var rtts []float64
			for _, m := range matches {
				if v, err := strconv.ParseFloat(m[1], 64); err == nil {
					rtts = append(rtts, v)
				}
			}
			if len(rtts) > 0 {
				writePingStats(w, target, "ICMP", rtts)
				return
			}
		}
	}

	// 2. Layer 2: TCP Ping on open port
	ports := []int{9999, 80, 443, 22, 8080}
	activePort := 0
	for _, p := range ports {
		conn, err := net.DialTimeout("tcp", net.JoinHostPort(target, strconv.Itoa(p)), 500*time.Millisecond)
		if err == nil {
			conn.Close()
			activePort = p
			break
		}
	}

	if activePort > 0 {
		targetAddr := net.JoinHostPort(target, strconv.Itoa(activePort))
		var rtts []float64
		for i := 0; i < count; i++ {
			t0 := time.Now()
			conn, err := net.DialTimeout("tcp", targetAddr, 1*time.Second)
			if err == nil {
				dt := float64(time.Since(t0).Microseconds()) / 1000.0
				rtts = append(rtts, roundFloat(dt, 2))
				conn.Close()
			}
			time.Sleep(20 * time.Millisecond)
		}
		if len(rtts) > 0 {
			writePingStats(w, target, fmt.Sprintf("TCP:%d", activePort), rtts)
			return
		}
	}

	// 3. Fallback: HTTP GET timing
	httpURLs := []string{
		fmt.Sprintf("http://%s:9999/api/stats", target),
		fmt.Sprintf("http://%s/", target),
	}
	client := &http.Client{Timeout: 1 * time.Second}
	for _, u := range httpURLs {
		var rtts []float64
		for i := 0; i < 5; i++ {
			t0 := time.Now()
			resp, err := client.Get(u)
			if err == nil {
				dt := float64(time.Since(t0).Microseconds()) / 1000.0
				rtts = append(rtts, roundFloat(dt, 2))
				resp.Body.Close()
			}
		}
		if len(rtts) > 0 {
			writePingStats(w, target, "HTTP", rtts)
			return
		}
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":      false,
		"error":   "All methods failed (ICMP blocked, no open TCP port, no HTTP service)",
		"target":  target,
		"samples": 0,
		"ping_ms": 0,
	})
}

func writePingStats(w http.ResponseWriter, target, method string, rtts []float64) {
	sum := 0.0
	minMs := rtts[0]
	maxMs := rtts[0]
	for _, rtt := range rtts {
		sum += rtt
		if rtt < minMs {
			minMs = rtt
		}
		if rtt > maxMs {
			maxMs = rtt
		}
	}
	avg := sum / float64(len(rtts))

	jitter := 0.0
	for i := 1; i < len(rtts); i++ {
		jitter += (math.Abs(rtts[i]-rtts[i-1]) - jitter) / 16.0
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"ok":         true,
		"target":     target,
		"method":     method,
		"ping_ms":    roundFloat(avg, 1),
		"jitter_ms":  roundFloat(jitter, 1),
		"min_ms":     roundFloat(minMs, 1),
		"max_ms":     roundFloat(maxMs, 1),
		"samples":    len(rtts),
		"rtt_values": rtts,
	})
}

// HandleSpeedtestStart starts background speedtest
func HandleSpeedtestStart(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "application/json")

	CurrentJob.mu.Lock()
	if CurrentJob.Status == "running" {
		CurrentJob.mu.Unlock()
		w.WriteHeader(http.StatusConflict)
		json.NewEncoder(w).Encode(map[string]string{"status": "running"})
		return
	}

	CurrentJob.Status = "running"
	CurrentJob.Stage = "ping"
	CurrentJob.Ping = 0
	CurrentJob.Jitter = 0
	CurrentJob.DownloadMbps = 0
	CurrentJob.UploadMbps = 0
	CurrentJob.Error = nil
	CurrentJob.mu.Unlock()

	go runSpeedtestWorker()

	w.WriteHeader(http.StatusAccepted)
	json.NewEncoder(w).Encode(map[string]string{"status": "started"})
}

// HandleExtStatus returns current speedtest state
func HandleExtStatus(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "application/json")

	CurrentJob.mu.RLock()
	defer CurrentJob.mu.RUnlock()

	json.NewEncoder(w).Encode(CurrentJob)
}

func runSpeedtestWorker() {
	// 1. TCP Ping to Cloudflare 1.1.1.1:443
	CurrentJob.mu.Lock()
	CurrentJob.Stage = "ping"
	CurrentJob.mu.Unlock()

	var rtts []float64
	for i := 0; i < 10; i++ {
		t0 := time.Now()
		conn, err := net.DialTimeout("tcp", "1.1.1.1:443", 2*time.Second)
		if err == nil {
			rtt := float64(time.Since(t0).Microseconds()) / 1000.0
			rtts = append(rtts, rtt)
			conn.Close()
		}
		time.Sleep(20 * time.Millisecond)
	}

	if len(rtts) > 2 {
		rtts = rtts[2:] // discard first 2 warmups
		sum := 0.0
		minP := rtts[0]
		maxP := rtts[0]
		for _, r := range rtts {
			sum += r
			if r < minP {
				minP = r
			}
			if r > maxP {
				maxP = r
			}
		}
		avg := sum / float64(len(rtts))
		jitter := 0.0
		for i := 1; i < len(rtts); i++ {
			jitter += (math.Abs(rtts[i]-rtts[i-1]) - jitter) / 16.0
		}

		CurrentJob.mu.Lock()
		CurrentJob.Ping = roundFloat(avg, 1)
		CurrentJob.Jitter = roundFloat(jitter, 1)
		CurrentJob.PingMin = roundFloat(minP, 1)
		CurrentJob.PingMax = roundFloat(maxP, 1)
		CurrentJob.mu.Unlock()
	}

	// 2. Upload test to Cloudflare
	CurrentJob.mu.Lock()
	CurrentJob.Stage = "upload"
	CurrentJob.mu.Unlock()

	uploadClient := &http.Client{Timeout: 8 * time.Second}
	blob := make([]byte, 2*1024*1024) // 2 MB
	var ulTotal int64
	tULStart := time.Now()

	for i := 0; i < 3; i++ {
		req, err := http.NewRequest("POST", "https://speed.cloudflare.com/__up", bytes.NewReader(blob))
		if err == nil {
			req.Header.Set("Content-Type", "application/octet-stream")
			req.Header.Set("User-Agent", "UniActivity-GoSpeed/1.0")
			if resp, err := uploadClient.Do(req); err == nil {
				io.Copy(io.Discard, resp.Body)
				resp.Body.Close()
				ulTotal += int64(len(blob))
			}
		}
	}
	ulDur := time.Since(tULStart).Seconds()
	if ulDur > 0 && ulTotal > 0 {
		ulMbps := (float64(ulTotal) * 8.0 / ulDur) / 1_000_000.0
		CurrentJob.mu.Lock()
		CurrentJob.UploadMbps = roundFloat(ulMbps, 2)
		CurrentJob.mu.Unlock()
	}

	// 3. Download test
	CurrentJob.mu.Lock()
	CurrentJob.Stage = "download"
	CurrentJob.mu.Unlock()

	dlClient := &http.Client{Timeout: 10 * time.Second}
	dlURL := "https://speed.cloudflare.com/__down?bytes=33554432" // 32 MB
	tDLStart := time.Now()
	var dlBytes int64

	if resp, err := dlClient.Get(dlURL); err == nil {
		buf := make([]byte, 65536)
		for {
			n, err := resp.Body.Read(buf)
			if n > 0 {
				dlBytes += int64(n)
			}
			if err != nil {
				break
			}
			if time.Since(tDLStart) >= 6*time.Second {
				break
			}
		}
		resp.Body.Close()
	}

	dlDur := time.Since(tDLStart).Seconds()
	if dlDur > 0 && dlBytes > 0 {
		dlMbps := (float64(dlBytes) * 8.0 / dlDur) / 1_000_000.0
		CurrentJob.mu.Lock()
		CurrentJob.DownloadMbps = roundFloat(dlMbps, 2)
		CurrentJob.mu.Unlock()
	}

	CurrentJob.mu.Lock()
	CurrentJob.Stage = "Complete"
	CurrentJob.Status = "idle"
	CurrentJob.LastTest = time.Now().Unix()
	CurrentJob.mu.Unlock()
}

func roundFloat(val float64, precision int) float64 {
	p := math.Pow10(precision)
	return math.Round(val*p) / p
}
