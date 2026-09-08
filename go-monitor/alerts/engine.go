package alerts

import (
	"fmt"
	"strconv"
	"strings"
	"sync"
	"time"

	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/telegram"
)

type AlertItem struct {
	ID      string `json:"id"`
	Type    string `json:"type"` // "critical" | "warning"
	Message string `json:"message"`
	Time    string `json:"time,omitempty"`
}

type AlertEngine struct {
	mu           sync.Mutex
	activeAlerts map[string]AlertItem
	pending      map[string]int
	resolveCount map[string]int
}

var Engine = &AlertEngine{
	activeAlerts: make(map[string]AlertItem),
	pending:      make(map[string]int),
	resolveCount: make(map[string]int),
}

// Evaluate evaluates telemetry stats against thresholds
func (e *AlertEngine) Evaluate(
	services map[string]string,
	load []float64,
	tempStr string,
	memPercent float64,
	diskPercent float64,
	cfOnline bool,
) []AlertItem {
	e.mu.Lock()
	defer e.mu.Unlock()

	var detected []AlertItem

	// 1. Service crash
	var stoppedSvcs []string
	for name, st := range services {
		if st == "Stopped" {
			stoppedSvcs = append(stoppedSvcs, name)
		}
	}
	if len(stoppedSvcs) > 0 {
		detected = append(detected, AlertItem{
			ID:      "service_crash",
			Type:    "critical",
			Message: fmt.Sprintf("Service(s) Offline: %s", strings.Join(stoppedSvcs, ", ")),
		})
	}

	// 2. High CPU Load (> 8.0)
	if len(load) > 0 {
		curLoad := load[0]
		_, isHigh := e.activeAlerts["high_load"]
		if curLoad > 8.0 || (isHigh && curLoad >= 5.0) {
			detected = append(detected, AlertItem{
				ID:      "high_load",
				Type:    "warning",
				Message: fmt.Sprintf("High CPU Load: %.2f", curLoad),
			})
		}
	}

	// 3. Overheating (> 75 C)
	if tVal, err := strconv.ParseFloat(tempStr, 64); err == nil {
		_, isOverheated := e.activeAlerts["high_temp"]
		if tVal > 75.0 || (isOverheated && tVal >= 65.0) {
			detected = append(detected, AlertItem{
				ID:      "high_temp",
				Type:    "warning",
				Message: fmt.Sprintf("Server Overheating: %.1f°C", tVal),
			})
		}
	}

	// 4. High Memory (> 90%)
	_, isHighMem := e.activeAlerts["high_mem"]
	if memPercent > 90.0 || (isHighMem && memPercent >= 80.0) {
		detected = append(detected, AlertItem{
			ID:      "high_mem",
			Type:    "warning",
			Message: fmt.Sprintf("High Memory Usage: %.1f%%", memPercent),
		})
	}

	// 5. High Storage (> 90%)
	_, isHighDisk := e.activeAlerts["high_disk"]
	if diskPercent > 90.0 || (isHighDisk && diskPercent >= 80.0) {
		detected = append(detected, AlertItem{
			ID:      "high_disk",
			Type:    "warning",
			Message: fmt.Sprintf("Disk Space Low: %.1f%% used", diskPercent),
		})
	}

	// 6. Cloudflare Offline (after 90s grace period)
	if time.Since(config.AppConfig.StartTime) > 90*time.Second && !cfOnline {
		detected = append(detected, AlertItem{
			ID:      "cf_offline",
			Type:    "critical",
			Message: "Cloudflare Tunnel is Offline",
		})
	}

	// Debounce: must be detected >= 3 times before firing
	currentDetectedMap := make(map[string]AlertItem)
	var debounced []AlertItem

	for _, a := range detected {
		currentDetectedMap[a.ID] = a
		e.pending[a.ID]++
		if _, alreadyActive := e.activeAlerts[a.ID]; alreadyActive || e.pending[a.ID] >= 3 {
			debounced = append(debounced, a)
		}
	}

	// Clean stale pending
	for id := range e.pending {
		if _, exists := currentDetectedMap[id]; !exists {
			delete(e.pending, id)
		}
	}

	// Check new alerts to send Telegram
	nowStr := time.Now().Format("2006-01-02 15:04:05")
	for _, a := range debounced {
		if _, exists := e.activeAlerts[a.ID]; !exists {
			a.Time = nowStr
			config.AppConfig.AddAlertHistory(map[string]interface{}{
				"id":      a.ID,
				"type":    a.Type,
				"message": a.Message,
				"time":    a.Time,
			})
			telegram.SendAlert(a.ID, a.Type, a.Message)
		}
	}

	// Check resolved alerts (must be gone >= 6 times before resolving)
	for id, activeItem := range e.activeAlerts {
		if _, stillDetected := currentDetectedMap[id]; !stillDetected {
			e.resolveCount[id]++
			if e.resolveCount[id] >= 6 {
				delete(e.activeAlerts, id)
				delete(e.resolveCount, id)
				delete(e.pending, id)
				telegram.SendResolved(id, fmt.Sprintf("%s กลับสู่สภาวะปกติแล้ว", activeItem.Message))
			}
		} else {
			delete(e.resolveCount, id)
		}
	}

	for _, a := range debounced {
		e.activeAlerts[a.ID] = a
	}

	return debounced
}
