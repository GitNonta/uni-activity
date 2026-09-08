package sysinfo

import (
	"bufio"
	"encoding/json"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"sync"
	"syscall"
	"time"
)

type NetworkTracker struct {
	mu         sync.Mutex
	lastRx     uint64
	lastTx     uint64
	lastTime   time.Time
	rxRate     float64
	txRate     float64
}

var netTracker = &NetworkTracker{
	lastTime: time.Now(),
}

// GetUptime reads uptime via direct kernel syscall (syscall.Sysinfo) with uptime -p fallback
func GetUptime() string {
	var si syscall.Sysinfo_t
	if err := syscall.Sysinfo(&si); err == nil && si.Uptime > 0 {
		sec := int(si.Uptime)
		days := sec / 86400
		hours := (sec % 86400) / 3600
		minutes := (sec % 3600) / 60
		weeks := days / 7
		daysRem := days % 7

		var res []string
		if weeks > 0 {
			res = append(res, fmt.Sprintf("%d week%s", weeks, plural(weeks)))
		}
		if daysRem > 0 || weeks > 0 {
			res = append(res, fmt.Sprintf("%d day%s", daysRem, plural(daysRem)))
		}
		if hours > 0 || len(res) > 0 {
			res = append(res, fmt.Sprintf("%d hour%s", hours, plural(hours)))
		}
		res = append(res, fmt.Sprintf("%d minute%s", minutes, plural(minutes)))
		return strings.Join(res, ", ")
	}

	out, err := exec.Command("uptime", "-p").Output()
	if err == nil && len(out) > 0 {
		return strings.TrimPrefix(strings.TrimSpace(string(out)), "up ")
	}
	return "unknown"
}

func plural(n int) string {
	if n == 1 {
		return ""
	}
	return "s"
}

// MemoryStats matches React frontend memory struct
type MemoryStats struct {
	TotalMB     int     `json:"total_mb"`
	AvailableMB int     `json:"available_mb"`
	UsedMB      int     `json:"used_mb"`
	Percent     float64 `json:"percent"`
}

func GetMemory() MemoryStats {
	f, err := os.Open("/proc/meminfo")
	if err != nil {
		return MemoryStats{}
	}
	defer f.Close()

	var totalKB, freeKB, availKB, buffersKB, cachedKB int
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := scanner.Text()
		parts := strings.Fields(line)
		if len(parts) < 2 {
			continue
		}
		val, _ := strconv.Atoi(parts[1])
		switch parts[0] {
		case "MemTotal:":
			totalKB = val
		case "MemFree:":
			freeKB = val
		case "MemAvailable:":
			availKB = val
		case "Buffers:":
			buffersKB = val
		case "Cached:":
			cachedKB = val
		}
	}

	if availKB == 0 {
		availKB = freeKB + buffersKB + cachedKB
	}
	totalMB := totalKB / 1024
	availMB := availKB / 1024
	usedMB := totalMB - availMB
	percent := 0.0
	if totalMB > 0 {
		percent = float64(usedMB) / float64(totalMB) * 100.0
	}
	return MemoryStats{
		TotalMB:     totalMB,
		AvailableMB: availMB,
		UsedMB:      usedMB,
		Percent:     mathRound(percent, 1),
	}
}

// GetLoad reads load average via kernel syscall.Sysinfo with uptime fallback
func GetLoad() []float64 {
	var si syscall.Sysinfo_t
	if err := syscall.Sysinfo(&si); err == nil {
		const scale = 65536.0
		return []float64{
			mathRound(float64(si.Loads[0])/scale, 2),
			mathRound(float64(si.Loads[1])/scale, 2),
			mathRound(float64(si.Loads[2])/scale, 2),
		}
	}

	out, err := exec.Command("uptime").Output()
	if err == nil {
		str := string(out)
		if idx := strings.Index(str, "load average:"); idx != -1 {
			parts := strings.Split(str[idx+13:], ",")
			res := make([]float64, 3)
			for i := 0; i < 3 && i < len(parts); i++ {
				res[i], _ = strconv.ParseFloat(strings.TrimSpace(parts[i]), 64)
			}
			return res
		}
	}
	return []float64{0, 0, 0}
}

// DiskStats matches React frontend disk struct
type DiskStats struct {
	TotalGB float64 `json:"total_gb"`
	UsedGB  float64 `json:"used_gb"`
	Percent float64 `json:"percent"`
}

func GetDisk(path string) DiskStats {
	var stat syscall.Statfs_t
	err := syscall.Statfs(path, &stat)
	if err != nil {
		err = syscall.Statfs("/", &stat)
	}
	if err != nil {
		return DiskStats{}
	}

	totalBytes := stat.Blocks * uint64(stat.Bsize)
	freeBytes := stat.Bavail * uint64(stat.Bsize)
	usedBytes := totalBytes - freeBytes

	totalGB := float64(totalBytes) / (1024 * 1024 * 1024)
	usedGB := float64(usedBytes) / (1024 * 1024 * 1024)
	percent := 0.0
	if totalGB > 0 {
		percent = (usedGB / totalGB) * 100.0
	}

	return DiskStats{
		TotalGB: mathRound(totalGB, 1),
		UsedGB:  mathRound(usedGB, 1),
		Percent: mathRound(percent, 1),
	}
}

// GetTemp returns CPU/SoC temperature string
func GetTemp() string {
	candidates := []string{
		"/sys/class/thermal/thermal_zone0/temp",
		"/sys/class/thermal/thermal_zone1/temp",
		"/sys/devices/virtual/thermal/thermal_zone0/temp",
	}
	for _, c := range candidates {
		data, err := os.ReadFile(c)
		if err == nil {
			tVal, err := strconv.ParseFloat(strings.TrimSpace(string(data)), 64)
			if err == nil {
				if tVal > 1000 {
					tVal = tVal / 1000.0
				}
				return fmt.Sprintf("%.1f", tVal)
			}
		}
	}
	return "40.0"
}

// BatteryStats matches React frontend battery struct
type BatteryStats struct {
	Percent          int    `json:"percent"`
	Status           string `json:"status"`
	CurrentUA        int    `json:"current_ua"`
	VoltageMV        int    `json:"voltage_mv"`
	ChargeCounterUAH int    `json:"charge_counter_uah"`
}

var (
	batMu       sync.RWMutex
	cachedBat   BatteryStats
	lastBatPoll time.Time
)

type termuxBatteryJSON struct {
	Percentage    int    `json:"percentage"`
	Status        string `json:"status"`
	Plugged       string `json:"plugged"`
	Voltage       int    `json:"voltage"`
	Current       int    `json:"current"`
	ChargeCounter int    `json:"charge_counter"`
}

func GetBattery() BatteryStats {
	batMu.RLock()
	if time.Since(lastBatPoll) < 4*time.Second && cachedBat.Percent > 0 {
		b := cachedBat
		batMu.RUnlock()
		return b
	}
	batMu.RUnlock()

	// 1. Try sysfs power supply
	batDir := "/sys/class/power_supply/battery"
	readInt := func(name string) int {
		data, err := os.ReadFile(filepath.Join(batDir, name))
		if err != nil {
			return 0
		}
		v, _ := strconv.Atoi(strings.TrimSpace(string(data)))
		return v
	}
	readStr := func(name string) string {
		data, err := os.ReadFile(filepath.Join(batDir, name))
		if err != nil {
			return ""
		}
		return strings.TrimSpace(string(data))
	}

	capVal := readInt("capacity")
	if capVal > 0 {
		status := readStr("status")
		cur := readInt("current_now")
		volt := readInt("voltage_now")
		charge := readInt("charge_counter")

		res := BatteryStats{
			Percent:          capVal,
			Status:           status,
			CurrentUA:        cur,
			VoltageMV:        volt / 1000,
			ChargeCounterUAH: charge,
		}
		batMu.Lock()
		cachedBat = res
		lastBatPoll = time.Now()
		batMu.Unlock()
		return res
	}

	// 2. Fallback to termux-battery-status (Android OS BatteryManager API)
	if out, err := exec.Command("termux-battery-status").Output(); err == nil && len(out) > 0 {
		var tb termuxBatteryJSON
		if err := json.Unmarshal(out, &tb); err == nil && tb.Percentage >= 0 {
			st := tb.Status
			if st == "" || st == "UNKNOWN" {
				if tb.Plugged != "" && tb.Plugged != "UNPLUGGED" {
					st = "CHARGING"
				} else {
					st = "DISCHARGING"
				}
			}
			chg := tb.ChargeCounter
			if chg == 0 && tb.Percentage > 0 {
				chg = (tb.Percentage * 4000 * 1000) / 100
			}
			res := BatteryStats{
				Percent:          tb.Percentage,
				Status:           st,
				CurrentUA:        tb.Current,
				VoltageMV:        tb.Voltage,
				ChargeCounterUAH: chg,
			}
			batMu.Lock()
			cachedBat = res
			lastBatPoll = time.Now()
			batMu.Unlock()
			return res
		}
	}

	// 3. Fallback default
	return BatteryStats{Percent: 100, Status: "FULL", VoltageMV: 4200, ChargeCounterUAH: 4000000}
}

// NetworkStats matches React frontend network struct
type NetworkStats struct {
	RxRate  string `json:"rx_rate"`
	TxRate  string `json:"tx_rate"`
	TotalRx string `json:"total_rx"`
	TotalTx string `json:"total_tx"`
}

func GetNetwork() NetworkStats {
	netTracker.mu.Lock()
	defer netTracker.mu.Unlock()

	f, err := os.Open("/proc/net/dev")
	if err != nil {
		return NetworkStats{RxRate: "0 B/s", TxRate: "0 B/s", TotalRx: "0 B", TotalTx: "0 B"}
	}
	defer f.Close()

	var totalRx, totalTx uint64
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if !strings.Contains(line, ":") {
			continue
		}
		parts := strings.Split(line, ":")
		iface := strings.TrimSpace(parts[0])
		if iface == "lo" {
			continue
		}
		fields := strings.Fields(parts[1])
		if len(fields) >= 9 {
			rx, _ := strconv.ParseUint(fields[0], 10, 64)
			tx, _ := strconv.ParseUint(fields[8], 10, 64)
			totalRx += rx
			totalTx += tx
		}
	}

	now := time.Now()
	dt := now.Sub(netTracker.lastTime).Seconds()
	if dt > 0.5 {
		if netTracker.lastRx > 0 && totalRx >= netTracker.lastRx {
			netTracker.rxRate = float64(totalRx-netTracker.lastRx) / dt
		}
		if netTracker.lastTx > 0 && totalTx >= netTracker.lastTx {
			netTracker.txRate = float64(totalTx-netTracker.lastTx) / dt
		}
		netTracker.lastRx = totalRx
		netTracker.lastTx = totalTx
		netTracker.lastTime = now
	}

	return NetworkStats{
		RxRate:  formatBytes(uint64(netTracker.rxRate)) + "/s",
		TxRate:  formatBytes(uint64(netTracker.txRate)) + "/s",
		TotalRx: formatBytes(totalRx),
		TotalTx: formatBytes(totalTx),
	}
}

// GetCPUFreqs returns array of CPU core frequencies in MHz
func GetCPUFreqs() []int {
	freqs := make([]int, 0, 8)
	for i := 0; i < 16; i++ {
		path := fmt.Sprintf("/sys/devices/system/cpu/cpu%d/cpufreq/scaling_cur_freq", i)
		data, err := os.ReadFile(path)
		if err != nil {
			break
		}
		khz, _ := strconv.Atoi(strings.TrimSpace(string(data)))
		freqs = append(freqs, khz/1000)
	}
	return freqs
}

func formatBytes(b uint64) string {
	const unit = 1024
	if b < unit {
		return fmt.Sprintf("%d B", b)
	}
	div, exp := uint64(unit), 0
	for n := b / unit; n >= unit; n /= unit {
		div *= unit
		exp++
	}
	return fmt.Sprintf("%.1f %cB", float64(b)/float64(div), "KMGTPE"[exp])
}

func mathRound(val float64, precision int) float64 {
	p := 1.0
	for i := 0; i < precision; i++ {
		p *= 10.0
	}
	return float64(int(val*p+0.5)) / p
}

type TopProc struct {
	PID  string  `json:"pid"`
	Name string  `json:"name"`
	CPU  float64 `json:"cpu"`
	Mem  float64 `json:"mem"`
}

var (
	topProcsCache []TopProc
	topProcsTime  time.Time
	topProcsMu    sync.Mutex
)

// GetTopProcesses returns top resource-consuming processes
func GetTopProcesses() []TopProc {
	topProcsMu.Lock()
	defer topProcsMu.Unlock()

	if time.Since(topProcsTime) < 4*time.Second && len(topProcsCache) > 0 {
		return topProcsCache
	}

	out, err := exec.Command("ps", "-A", "-o", "pid,comm,pcpu,pmem").Output()
	if err != nil {
		return topProcsCache
	}

	lines := strings.Split(string(out), "\n")
	var procs []TopProc
	for i, line := range lines {
		if i == 0 {
			continue
		}
		fields := strings.Fields(line)
		if len(fields) >= 4 {
			pid := fields[0]
			comm := fields[1]
			cpu, _ := strconv.ParseFloat(fields[2], 64)
			mem, _ := strconv.ParseFloat(fields[3], 64)

			if comm == "ps" || comm == "top" || comm == "grep" || comm == "ss" {
				continue
			}

			// Clean up command name
			cleanName := comm
			if strings.HasPrefix(cleanName, "/") {
				cleanName = filepath.Base(cleanName)
			}
			if cmdBytes, err := os.ReadFile(fmt.Sprintf("/proc/%s/cmdline", pid)); err == nil && len(cmdBytes) > 0 {
				cleanCmd := strings.TrimRight(string(cmdBytes), "\x00")
				args := strings.Split(cleanCmd, "\x00")
				if len(args) > 0 && len(args[0]) > 0 {
					base := filepath.Base(args[0])
					if base == "python" || base == "python3" || base == "php" {
						if len(args) > 1 && len(args[1]) > 0 {
							cleanName = fmt.Sprintf("%s %s", base, filepath.Base(args[1]))
						} else {
							cleanName = base
						}
					} else {
						cleanName = base
					}
				}
			}

			procs = append(procs, TopProc{
				PID:  pid,
				Name: cleanName,
				CPU:  cpu,
				Mem:  mem,
			})
		}
	}

	sort.Slice(procs, func(i, j int) bool {
		return procs[i].CPU > procs[j].CPU
	})

	if len(procs) > 5 {
		procs = procs[:5]
	}

	topProcsCache = procs
	topProcsTime = time.Now()
	return procs
}

// GetNetSpeeds returns bandwidth rate in KB/s
func GetNetSpeeds() map[string]float64 {
	netTracker.mu.Lock()
	defer netTracker.mu.Unlock()
	return map[string]float64{
		"rx_kbps": mathRound(netTracker.rxRate/1024.0, 1),
		"tx_kbps": mathRound(netTracker.txRate/1024.0, 1),
	}
}
