// Package control exposes service start/stop/restart to the dashboard and Telegram.
package control

import (
	"encoding/json"
	"fmt"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"uni-activity/go-monitor/services"
)

type request struct {
	Target string `json:"target"`
	Action string `json:"action"` // start | stop | restart
}

type targetCmds struct {
	start []string   // argv; Dir = dirs[0]
	stop  []string
	dir   string
}

func targets() map[string]targetCmds {
	root := "/data/data/com.termux/files/home/uni-activity"
	if _, err := os.Stat(root); err != nil {
		cwd, _ := os.Getwd()
		root = filepath.Dir(cwd)
	}
	return map[string]targetCmds{
		"nginx": {start: []string{"nginx"}, stop: []string{"nginx", "-s", "quit"}},
		"php_fpm": {start: []string{"php-fpm", "--daemonize"}, stop: []string{"pkill", "-x", "php-fpm"}},
		"postgres": {start: []string{"pg_ctl", "start", "-D", "/data/data/com.termux/files/usr/var/lib/postgresql"}, stop: []string{"pg_ctl", "stop", "-D", "/data/data/com.termux/files/usr/var/lib/postgresql"}},
		"valkey": {start: []string{"valkey-server", "--port", "6379", "--bind", "0.0.0.0", "--daemonize", "yes"}, stop: []string{"valkey-cli", "shutdown", "nosave"}},
		"queue_worker": {start: []string{"php", "artisan", "queue:work"}, stop: []string{"pkill", "-f", "queue:work"}, dir: root},
		"reverb": {start: []string{"php", "artisan", "reverb:start"}, stop: []string{"pkill", "-f", "reverb:start"}, dir: root},
		"web_workers": {start: []string{"php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"}, stop: []string{"pkill", "-f", "artisan serve"}, dir: root},
		"squid": {start: []string{"squid", "-f", "/data/data/com.termux/files/usr/etc/squid/squid.conf", "-N"}, stop: []string{"squid", "-k", "shutdown"}},
		"prometheus": {start: []string{"./prometheus", "--config.file=./prometheus.yml", "--storage.tsdb.path=./data", "--web.listen-address=:9090"}, stop: []string{"pkill", "-x", "prometheus"}, dir: filepath.Join(root, "..", "prometheus")},
		"grafana": {start: []string{"./bin/grafana-server", "--homepath=./", "--config=./conf/defaults.ini"}, stop: []string{"pkill", "-f", "grafana-server"}, dir: filepath.Join(root, "..", "grafana")},
	}
}

// HandleControl: POST /api/control {target, action} or GET /api/control (status).
func HandleControl(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	if r.Method == http.MethodGet {
		json.NewEncoder(w).Encode(map[string]interface{}{"status": "ok", "targets": Status()})
		return
	}
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": "use POST"})
		return
	}
	var req request
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Target == "" || req.Action == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": "body: {target, action}"})
		return
	}
	req.Action = strings.ToLower(req.Action)
	req.Target = strings.ToLower(req.Target)

	if req.Target == "ai_service" {
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": "ai_service runs in proot — manage manually"})
		return
	}
	if req.Target == "go_monitor" || req.Target == "tunnel" {
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": req.Target + " is managed by cf-manager / svc_monitor.sh — refused"})
		return
	}

	all := req.Target == "all"
	if !all {
		if _, ok := targets()[req.Target]; !ok {
			json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": "unknown target: " + req.Target})
			return
		}
	}

	go func() {
		names := []string{req.Target}
		if all {
			names = nil
			for name := range targets() {
				names = append(names, name)
			}
		}
		for _, name := range names {
			t, ok := targets()[name]
			if !ok {
				continue
			}
			switch req.Action {
			case "start":
				startTarget(name, t)
			case "stop":
				stopTarget(t)
			case "restart":
				stopTarget(t)
				time.Sleep(2 * time.Second)
				startTarget(name, t)
			default:
				return
			}
		}
	}()
	json.NewEncoder(w).Encode(map[string]string{"status": "ok", "message": req.Action + " " + req.Target + " initiated"})
}

func startTarget(name string, t targetCmds) {
	if len(t.start) == 0 {
		return
	}
	cmd := exec.Command(t.start[0], t.start[1:]...)
	cmd.Dir = t.dir
	// Long-running foreground servers need their own session to survive us.
	if name == "queue_worker" || name == "reverb" || name == "web_workers" || name == "prometheus" || name == "grafana" {
		cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
	}
	_ = cmd.Run()
}

func stopTarget(t targetCmds) {
	if len(t.stop) == 0 {
		return
	}
	_ = exec.Command(t.stop[0], t.stop[1:]...).Run()
}

// Status returns current state per target for GET /api/control.
func Status() map[string]string {
	res := map[string]string{}
	svcs := services.CheckAllServices()
	nameMap := map[string]string{
		"nginx": "Nginx (Edge Proxy)", "postgres": "PostgreSQL Database",
		"valkey": "Datastore (Valkey)", "queue_worker": "Queue Worker",
		"reverb": "Laravel Reverb (WebSocket)", "web_workers": "Web Workers (artisan serve)",
		"ai_service": "AI Scan Service", "php_fpm": "PHP-FPM",
	}
	for t, svcName := range nameMap {
		if st, ok := svcs[svcName]; ok {
			res[t] = strings.ToLower(strings.SplitN(st, " ", 2)[0])
		}
	}
	res["queue_store"] = res["valkey"]
	if exec.Command("pgrep", "-x", "squid").Run() == nil {
		res["squid"] = "running"
	} else {
		res["squid"] = "stopped"
	}
	// Grafana v10 re-executes as a different process name — probe the port instead.
	res["prometheus"] = portState(9090)
	res["grafana"] = portState(3000)
	res["socks5"] = portState(1080)
	res["tunnel"] = "managed"
	res["go_monitor"] = "running"
	return res
}

// portState probes a local TCP port; stopped when unreachable.
func portState(port int) string {
	conn, err := net.DialTimeout("tcp", fmt.Sprintf("127.0.0.1:%d", port), 500*time.Millisecond)
	if err != nil {
		return "stopped"
	}
	conn.Close()
	return "running"
}
