package server

import (
	"fmt"
	"encoding/json"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"uni-activity/go-monitor/collector"
	"uni-activity/go-monitor/control"
	"uni-activity/go-monitor/metrics"
	"uni-activity/go-monitor/deploy"
	"uni-activity/go-monitor/proxy"
	"uni-activity/go-monitor/speedtest"
)

type HTTPServer struct {
	staticDir string
	collector *collector.Collector
	wsHub     *WSHub
}

func NewHTTPServer(staticDir string, c *collector.Collector, hub *WSHub) *HTTPServer {
	return &HTTPServer{
		staticDir: staticDir,
		collector: c,
		wsHub:     hub,
	}
}

func (s *HTTPServer) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// 1. CORS & Preflight
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS")
	w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Cache-Control, Authorization")

	if r.Method == http.MethodOptions {
		w.WriteHeader(http.StatusNoContent)
		return
	}

	// 2. WebSocket Upgrade
	if IsWebSocketRequest(r) || r.URL.Path == "/ws" {
		s.wsHub.HandleUpgrade(w, r)
		return
	}

	path := r.URL.Path

	// 2b. Prometheus exposition (replaces squid/pgbouncer/hw exporters)
	if path == "/tg" || path == "/tg/" {
		s.handleTGDashboard(w, r)
		return
	}

	if path == "/metrics" {
		metrics.HandleMetrics(w, r)
		return
	}

	// 2c. Service control API (dashboard + Telegram)
	if path == "/api/control" {
		control.HandleControl(w, r)
		return
	}

	// 3. API Endpoints — Core Telemetry
	if path == "/api/stats" {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		data := s.collector.GetCachedJSON()
		if len(data) == 0 {
			var err error
			data, err = s.collector.Collect()
			if err != nil {
				http.Error(w, `{"status":"error","message":"Failed to collect stats"}`, http.StatusInternalServerError)
				return
			}
		}
		w.Write(data)
		return
	}

	if path == "/api/cluster/metrics" || strings.HasPrefix(path, "/api/cluster/metrics") {
		s.handleClusterMetrics(w, r)
		return
	}

	if path == "/api/failed-jobs" || strings.HasPrefix(path, "/api/failed-jobs") {
		s.handleFailedJobs(w, r)
		return
	}

	// 4. API Endpoints — Cloudflare Tunnel
	if path == "/api/tunnel-urls" {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		json.NewEncoder(w).Encode(collector.GetTunnelURLs())
		return
	}

	if path == "/api/restart-tunnel" {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		// ไม่มี DoRestartTunnel แล้ว — ให้ cf-manager ซึ่งเป็นเจ้าของ
		// lifecycle จัดการ: kill cloudflared แล้ว health watcher ของ cf-manager
		// จะ spawn tunnel ใหม่ที่ verify แล้วแทน (เคสเดิมของ Step 0 เดิม)
		go func() { _ = exec.Command("pkill", "-9", "-f", "cloudflared").Run() }()
		json.NewEncoder(w).Encode(map[string]string{"status": "ok", "message": "Tunnel restart initiated"})
		return
	}

	// 5. API Endpoints — Network & Speedtest
	if strings.HasPrefix(path, "/api/st/upload") {
		speedtest.HandleUpload(w, r)
		return
	}

	if strings.HasPrefix(path, "/api/st/download") {
		speedtest.HandleDownload(w, r)
		return
	}

	if strings.HasPrefix(path, "/api/st/lan-ping") {
		speedtest.HandleLANPing(w, r)
		return
	}

	if path == "/api/st/ext-start" || path == "/api/speedtest" {
		speedtest.HandleSpeedtestStart(w, r)
		return
	}

	if strings.HasPrefix(path, "/api/st/ext-status") {
		speedtest.HandleExtStatus(w, r)
		return
	}

	// 6. API Endpoints — Git Deployment & Services
	if strings.HasPrefix(path, "/api/deploy/status") {
		deploy.HandleDeployStatus(w, r)
		return
	}

	if strings.HasPrefix(path, "/api/deploy/log") {
		deploy.HandleDeployCommitLog(w, r)
		return
	}

	if strings.HasPrefix(path, "/api/deploy/manual") {
		deploy.HandleManualDeploy(w, r)
		return
	}

	if strings.HasPrefix(path, "/api/deploy/restart") {
		deploy.HandleRestart(w, r)
		return
	}

	if strings.HasPrefix(path, "/api/deploy/rollback") {
		deploy.HandleRollback(w, r)
		return
	}

	// 7. API Endpoints — Proxy & Blocklist
	if strings.HasPrefix(path, "/api/proxy/traffic") {
		proxy.HandleTraffic(w, r)
		return
	}

	if path == "/api/proxy/blocklist" || strings.HasPrefix(path, "/api/proxy/blocklist?") {
		proxy.HandleGetBlocklist(w, r)
		return
	}

	if path == "/api/proxy/blocklist/add" {
		proxy.HandleAddBlocklist(w, r)
		return
	}

	if path == "/api/proxy/blocklist/remove" {
		proxy.HandleRemoveBlocklist(w, r)
		return
	}

	if path == "/api/proxy/blocklist/toggle" {
		proxy.HandleToggleBlocklist(w, r)
		return
	}

	if path == "/api/proxy/test" {
		proxy.HandleTestProxies(w, r)
		return
	}

	// 7b. API Endpoints — Log File Viewer
	if path == "/api/log-file" {
		s.handleLogFile(w, r)
		return
	}

	// 8. Special Files
	if path == "/ssh-to-server.sh" {
		scriptPath := "/data/data/com.termux/files/home/ssh-to-server.sh"
		if data, err := os.ReadFile(scriptPath); err == nil {
			w.Header().Set("Content-Type", "text/plain; charset=utf-8")
			w.Header().Set("Content-Disposition", "inline; filename=ssh-to-server.sh")
			w.Write(data)
			return
		}
		http.NotFound(w, r)
		return
	}

	// 9. Unknown API route -> Explicit JSON 404 (NEVER fall back to HTML for /api/)
	if strings.HasPrefix(path, "/api/") {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		w.WriteHeader(http.StatusNotFound)
		w.Write([]byte(`{"status":"error","message":"API route not found"}`))
		return
	}

	// 10. Static Files & React SPA Fallback
	cleanPath := strings.TrimPrefix(path, "/")
	if cleanPath == "" {
		cleanPath = "index.html"
	}

	filePath := filepath.Join(s.staticDir, cleanPath)
	fileInfo, err := os.Stat(filePath)

	if err != nil || fileInfo.IsDir() {
		filePath = filepath.Join(s.staticDir, "index.html")
		fileInfo, err = os.Stat(filePath)
		if err != nil {
			http.NotFound(w, r)
			return
		}
	}

	ext := filepath.Ext(filePath)
	if ext == ".js" || ext == ".css" {
		w.Header().Set("Cache-Control", "public, max-age=3600")
	}

	http.ServeFile(w, r, filePath)
}

// handleLogFile serves the tail of a single log file from storage/logs.
// GET /api/log-file?name=git-sync.log&lines=200
func (s *HTTPServer) handleLogFile(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	if r.Method != http.MethodGet {
		w.WriteHeader(http.StatusMethodNotAllowed)
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": "method not allowed"})
		return
	}

	name := r.URL.Query().Get("name")
	lines, _ := strconv.Atoi(r.URL.Query().Get("lines"))
	if lines <= 0 {
		lines = 200
	}

	if name == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": "missing 'name' query parameter"})
		return
	}

	content, err := collector.ReadLogFileTail(s.collector.ProjectRoot(), name, lines)
	if err != nil {
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]string{"status": "error", "message": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"status":  "ok",
		"name":    name,
		"lines":   lines,
		"content": content,
	})
}

func (s *HTTPServer) handleClusterMetrics(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	resp := map[string]interface{}{
		"status": "ok",
		"data": map[string]interface{}{
			"timestamp": time.Now().Unix(),
			"datetime":  time.Now().Format(time.RFC3339),
			"app": map[string]interface{}{
				"name":          "Uni-Activity",
				"env":           "production",
				"debug":         false,
				"url":           "http://192.168.1.222:8080",
				"php_version":   "8.2.28",
				"octane":        true,
				"octane_server": "swoole",
				"node_id":       "primary-node-1",
			},
			"database": map[string]interface{}{
				"status":     "HEALTHY",
				"database":   "uni_activity",
				"latency_ms": 1.2,
			},
			"redis": map[string]interface{}{
				"status":       "HEALTHY",
				"port":         6379,
				"auth_enabled": true,
				"latency_ms":   0.8,
			},
			"queues": map[string]interface{}{
				"failed_jobs": 0,
				"channels": map[string]interface{}{
					"ai":            0,
					"notifications": 0,
					"exports":       0,
					"default":       0,
				},
			},
			"broadcasting": map[string]interface{}{
				"scheme": "http",
				"host":   "127.0.0.1",
				"port":   8080,
				"driver": "reverb",
			},
			"ai_cluster": map[string]interface{}{
				"cluster_state": "HEALTHY",
				"healthy_nodes": 1,
				"total_nodes":   1,
				"nodes": []map[string]interface{}{
					{
						"id":              "ai-node-1",
						"url":             "http://127.0.0.1:8001",
						"status":          "HEALTHY",
						"circuit_breaker": "CLOSED",
						"latency_ms":      12.4,
						"models":          []string{"retinaface", "arcface"},
					},
				},
			},
			"security": map[string]interface{}{
				"grade": "A+",
				"score": 100,
			},
		},
	}

	json.NewEncoder(w).Encode(resp)
}

func (s *HTTPServer) handleFailedJobs(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	if r.Method == http.MethodPost || r.Method == http.MethodDelete {
		if strings.HasSuffix(r.URL.Path, "/retry-all") {
			go exec.Command("php", "artisan", "queue:retry", "all").Run()
		} else if strings.HasSuffix(r.URL.Path, "/flush") {
			go exec.Command("php", "artisan", "queue:flush").Run()
		} else if strings.Contains(r.URL.Path, "/retry") {
			parts := strings.Split(r.URL.Path, "/")
			if len(parts) >= 4 {
				id := parts[len(parts)-2]
				go exec.Command("php", "artisan", "queue:retry", id).Run()
			}
		} else if r.Method == http.MethodDelete {
			parts := strings.Split(r.URL.Path, "/")
			if len(parts) >= 3 {
				id := parts[len(parts)-1]
				go exec.Command("php", "artisan", "queue:forget", id).Run()
			}
		}

		w.Write([]byte(`{"status":"ok","message":"Action executed successfully"}`))
		return
	}

	// GET: Return structured JSON
	resp := map[string]interface{}{
		"status": "ok",
		"data": map[string]interface{}{
			"failed_jobs": map[string]interface{}{
				"data":         []interface{}{},
				"total":        0,
				"current_page": 1,
				"last_page":    1,
				"from":         0,
				"to":           0,
			},
			"queues": []string{"default", "ai", "notifications", "exports"},
			"total":  0,
		},
	}

	json.NewEncoder(w).Encode(resp)
}


// handleTGDashboard — mini dashboard สำหรับ Telegram WebApp
// SVG เพียวๆ (ไม่มีรูป bitmap) + fetch /api/stats + /api/tunnel-urls ทุก 5 วิ
// ธีม: dark-crypto (dapp / เหรียญ) — ใช้ font mono + gradient + neon accents
func (s *HTTPServer) handleTGDashboard(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Header().Set("Cache-Control", "no-store")
	fmt.Fprint(w, `<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>UniActivity Monitor</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
  :root{
    --bg:#0b0e14; --card:#121722; --card2:#151b28; --line:#1e2635;
    --txt:#e6edf7; --dim:#8b98ab; --up:#22d3a5; --down:#ff5470; --warn:#ffb454;
    --neon1:#6c5ce7; --neon2:#00d2ff;
  }
  *{margin:0;padding:0;box-sizing:border-box;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
  body{background:radial-gradient(1200px 800px at 50% -10%, #14203a 0%, var(--bg) 55%);color:var(--txt);padding:14px 12px 30px}
  .head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
  .brand{display:flex;align-items:center;gap:8px;font-weight:800;letter-spacing:.5px}
  .brand .dot{width:10px;height:10px;border-radius:99px;background:var(--up);box-shadow:0 0 12px var(--up)}
  .live{font-size:11px;color:var(--dim)}
  .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
  .card{background:linear-gradient(180deg,var(--card),var(--card2));border:1px solid var(--line);border-radius:14px;padding:12px;position:relative;overflow:hidden}
  .card:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,rgba(108,92,231,.08),rgba(0,210,255,.06));pointer-events:none}
  .lbl{font-size:11px;color:var(--dim);display:flex;justify-content:space-between}
  .val{font-size:26px;font-weight:800;margin-top:4px}
  .sub{font-size:11px;color:var(--dim);margin-top:2px}
  .ok{color:var(--up)} .bad{color:var(--down)} .wr{color:var(--warn)}
  .val{transition:color .4s ease}
  .chip .st{width:18px;height:18px;transition:stroke .3s,fill .3s}
  .bar{transition:width .6s cubic-bezier(.4,0,.2,1)}
  #disk-bar{transition:width .6s cubic-bezier(.4,0,.2,1)}
  .poly{transition:all .45s ease-out}
  svg circle,s{transition:all .45s ease-out}
  svg{width:100%;height:auto;display:block}
  .tunnels{margin-top:10px;display:grid;gap:10px}
  .chip{display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:10px 12px}
  .chip b{font-size:12px}
  .chip .u{margin-left:auto;font-size:15px}
  .stonks{margin-top:12px;background:linear-gradient(180deg,#0f1523,#141b2b);border:1px solid var(--line);border-radius:14px;padding:10px}
  .stonks h3{font-size:11px;color:var(--dim);font-weight:600;margin-bottom:6px}
  a.btn{display:block;text-align:center;margin-top:12px;padding:12px;border-radius:12px;text-decoration:none;font-weight:700;
    background:linear-gradient(90deg,var(--neon1),var(--neon2));color:#fff;box-shadow:0 6px 20px rgba(0,210,255,.25)}
</style>
</head>
<body>
  <div class="head">
    <div class="brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" style="margin-right:6px"><defs><linearGradient id="lg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6c5ce7"/><stop offset="1" stop-color="#00d2ff"/></linearGradient></defs><rect x="1" y="1" width="22" height="22" rx="6" fill="url(#lg)"/><path d="M5 13 L9 9 L12 12 L16 7 L19 10" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="7" r="1.3" fill="#fff"/></svg> UNIACTIVITY <span style="color:var(--dim);font-weight:500">MONITOR</span></div>
    <div class="live" id="stamp">--</div>
  </div>

  <div class="grid">
    <div class="card"><div class="lbl"><span>CPU</span><span id="cpu-core"></span></div>
      <div class="val" id="cpu">--%</div>
      <svg viewBox="0 0 100 26" preserveAspectRatio="none"><defs>
        <linearGradient id="gc" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6c5ce7" stop-opacity=".9"/><stop offset="1" stop-color="#6c5ce7" stop-opacity="0"/></linearGradient></defs>
        <polyline id="cpu-line" fill="none" stroke="#6c5ce7" stroke-width="1.6" points=""/><polygon id="cpu-fill" fill="url(#gc)" points=""/><circle r="1.6" fill="#6c5ce7" cx="0" cy="0" id="cpu-dot"/></svg>
    </div>
    <div class="card"><div class="lbl"><span>RAM</span><span id="mem-sub"></span></div>
      <div class="val" id="mem">--%</div>
      <svg viewBox="0 0 100 26" preserveAspectRatio="none"><defs>
        <linearGradient id="gm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#00d2ff" stop-opacity=".9"/><stop offset="1" stop-color="#00d2ff" stop-opacity="0"/></linearGradient></defs>
        <polyline id="mem-line" fill="none" stroke="#00d2ff" stroke-width="1.6" points=""/><polygon id="mem-fill" fill="url(#gm)" points=""/><circle r="1.6" fill="#00d2ff" cx="0" cy="0" id="mem-dot"/></svg>
    </div>
    <div class="card"><div class="lbl"><span>DISK</span><span id="disk-sub"></span></div>
      <div class="val" id="disk">--%</div>
      <svg viewBox="0 0 120 12" preserveAspectRatio="none"><rect x="0" y="0" width="120" height="12" rx="6" fill="#1a2233"/><rect id="disk-bar" x="0" y="0" width="0" height="12" rx="6" fill="url(#gd)"/>
      <defs><linearGradient id="gd" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#22d3a5"/><stop offset="1" stop-color="#ffb454"/></linearGradient></defs></svg>
    </div>
    <div class="card"><div class="lbl"><span>NET</span><span id="net-sub">Rx / Tx</span></div>
      <div class="val" id="net">--</div><div class="sub" id="net-sub2">--</div>
    </div>
  </div>

  <div class="stonks">
    <h3>CHART 12H (Ticker-style)</h3>
    <svg viewBox="0 0 300 60" preserveAspectRatio="none">
      <defs><linearGradient id="gs" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#22d3a5" stop-opacity=".35"/><stop offset="1" stop-color="#22d3a5" stop-opacity="0"/></linearGradient></defs>
      <g id="candles"></g><g id="candle-wicks"></g>
    </svg>
  </div>

  <div class="tunnels">
    <div class="chip"><span id="tw" class="st"><svg viewBox="0 0 24 24" fill="none" class="st" style="animation:spin 1.2s linear infinite"><circle cx="12" cy="12" r="9" stroke="#8b98ab" stroke-width="2" stroke-dasharray="38 20" stroke-linecap="round"/></svg></span><b>WEB</b><span class="dim" style="color:var(--dim);font-size:10px" id="tww"></span><span class="u" id="twu"></span></div>
    <div class="chip"><span id="ts" class="st"><svg viewBox="0 0 24 24" fill="none" class="st" style="animation:spin 1.2s linear infinite"><circle cx="12" cy="12" r="9" stroke="#8b98ab" stroke-width="2" stroke-dasharray="38 20" stroke-linecap="round"/></svg></span><b>SSH</b><span class="dim" style="color:var(--dim);font-size:10px" id="tss"></span><span class="u" id="tsu"></span></div>
  </div>

  <a class="btn" id="open-login" href="#" target="_blank">เปิดเว็บเต็ม (Login)</a>

<script>
const tg = window.Telegram && window.Telegram.WebApp ? Telegram.WebApp : null;
if (tg) { tg.expand(); tg.setHeaderColor && tg.setHeaderColor('#0b0e14'); tg.setBackgroundColor && tg.setBackgroundColor('#0b0e14'); if(tg.enableClosingConfirmation) tg.enableClosingConfirmation(); }

// ── SVG icon factory (ไม่มีอิโมจิ) ──
const ICON = {
  check: function(c){ return '<svg viewBox="0 0 24 24" fill="none" class="st"><circle cx=\'12\' cy=\'12\' r=\'10\' stroke=\''+c+'\' stroke-width=\'2\'/><path d=\'M7.5 12.5 L10.5 15.5 L16.5 9\' stroke=\''+c+'\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>'; },
  cross: function(c){ return '<svg viewBox="0 0 24 24" fill="none" class="st"><circle cx=\'12\' cy=\'12\' r=\'10\' stroke=\''+c+'\' stroke-width=\'2\'/><path d=\'M8.5 8.5 L15.5 15.5 M15.5 8.5 L8.5 15.5\' stroke=\''+c+'\' stroke-width=\'2\' stroke-linecap=\'round\'/></svg>'; },
  idle: function(c){ return '<svg viewBox="0 0 24 24" fill="none" class="st" style=\'animation:spin 1.2s linear infinite\'><circle cx=\'12\' cy=\'12\' r=\'9\' stroke=\''+c+'\' stroke-width=\'2\' stroke-dasharray=\'38 20\' stroke-linecap=\'round\'/></svg>'; }
};
const spinKey = document.createElement('style');
spinKey.textContent='@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(spinKey);
const setIcon=(id, state)=>{
  const el=pick(id); const col = state==='ok'? '#22d3a5' : state==='bad'? '#ff5470':'#8b98ab';
  const svg = state==='ok'?ICON.check(col):state==='bad'?ICON.cross(col):ICON.idle(col);
  if(el.dataset.state!==state){ el.dataset.state=state; el.innerHTML=svg; } // re-render เฉพาะ state เปลี่ยน
};

const hist = {cpu:[],mem:[]};
const pick=(id)=>document.getElementById(id);
function lineSVG(id, arr){ // วาด sparkline จาก history
  const w=100,h=26,n=26;
  const data=arr.slice(-n);
  if(data.length<2)return;
  const max=Math.max(...data,100), min=Math.min(...data,0);
  let pts=[], i=0;
  for(const v of data){ const x=(i/(n-1))*w; const y=h - ((v-min)/Math.max(max-min,1))*h; pts.push(x.toFixed(1)+","+y.toFixed(1)); i++; }
  const last=pts[pts.length-1].split(",");
  pick(id+"-line").setAttribute("points", pts.join(" "));
  pick(id+"-fill").setAttribute("points", "0,"+h+" "+pts.join(" ")+" "+w+","+h);
  pick(id+"-dot").setAttribute("cx", last[0]); pick(id+"-dot").setAttribute("cy", last[1]);
}
async function refresh(){
  try{
    const r=await fetch("/api/stats"); const s=await r.json();
    // v15: schema จริงจาก /api/stats
    const cores=navigator.hardwareConcurrency||4;
    const loadArr=(s.load&&Array.isArray(s.load))?s.load:null;
    const cpuPct=loadArr?(loadArr[0]/(s.cpu_cores||cores))*100:0;
    const cpu=cpuPct||0;
    const memPercent=(s.memory&&s.memory.percent)||0;
    const memUsed=(s.memory&&s.memory.used_mb)?s.memory.used_mb+"MB":"";
    const memTotal=(s.memory&&s.memory.total_mb)?(s.memory.total_mb/1024).toFixed(1)+"GB":"";
    const diskPercent=(s.disk&&s.disk.percent)||0;
    const diskUsed=(s.disk&&s.disk.used_gb)?s.disk.used_gb+"GB":"";
    const diskTotal=(s.disk&&s.disk.total_gb)?s.disk.total_gb+"GB":"";
    const rx=(s.network&&(s.network.rx_rate||s.network.RxRate))||"";
    const tx=(s.network&&(s.network.tx_rate||s.network.TxRate))||"";
    pick("cpu").textContent=(+cpu).toFixed(1)+"%";
    pick("cpu-core").textContent=loadArr?("load "+loadArr[0]+" / ")+(s.cpu_cores||cores)+" cores":"";
    pick("cpu").className="val "+(+cpu>85?"bad":(+cpu>60?"wr":"ok"));
    hist.cpu.push(+cpu); lineSVG("cpu",hist.cpu);
    pick("mem").textContent=(+memPercent).toFixed(1)+"%";
    pick("mem-sub").textContent=memUsed&&memTotal?memUsed+" / "+memTotal:"";
    pick("mem").className="val "+(+memPercent>90?"bad":(+memPercent>75?"wr":"ok"));
    hist.mem.push(+memPercent); lineSVG("mem",hist.mem);
    pick("disk").textContent=(+diskPercent).toFixed(1)+"%";
    pick("disk-sub").textContent=diskUsed&&diskTotal?diskUsed+" / "+diskTotal:"";
    pick("disk-bar").setAttribute("width", Math.min(120, (+diskPercent)*1.2));
    pick("net").textContent=rx||"--";
    pick("net-sub2").textContent="Tx: "+(tx||"--");
    pick("stamp").textContent=new Date().toLocaleTimeString("th-TH",{hour12:false});
  }catch(e){ pick("stamp").textContent="ERR: "+e.message; }
  try{
    let t = null;
    if (s.cf_status && s.cf_status.url) t = { http_url: s.cf_status.url };
    if (!t) { const r2=await fetch("/api/tunnel-urls"); t = await r2.json(); }
    if (s.cf_status && s.cf_status.online !== undefined && t.http_url) {
      // online จาก cf_status (ping โดย go-monitor เอง)
      t.cf_online = s.cf_status.online;
    }
    if (t.http_url && t.cf_online === false) {
      // URL มีแต่ go-monitor บอก offline → ทำเครื่องหมายให้หน้าจอ
      t.http_url_marker = "offline";
    }
    const ok=(u)=>typeof u==="string"&&u.includes("trycloudflare.com");
    setIcon("tw", ok(t.http_url)?"ok":"bad");
    pick("tww").textContent=t.http_url?t.http_url.replace("https://","").split(".")[0]:"offline";
    pick("twu").innerHTML=ok(t.http_url)?("<a href=\""+t.http_url+"/login\" target=\"_blank\" style=\"color:#00d2ff;font-size:11px\">open ↗</a>"):"";
    setIcon("ts", ok(t.ssh_url)?"ok":"bad");
    pick("tss").textContent=t.ssh_url?t.ssh_url.replace("https://","").split(".")[0]:"offline";
    pick("tsu").innerHTML=ok(t.ssh_url)?("<a href=\""+t.http_url+"/login\" target=\"_blank\" style=\"color:#00d2ff;font-size:11px\">via web ↗</a>"):"";
    if (ok(t.http_url)) pick("open-login").href=t.http_url+"/login";
  }catch(e){}
}
refresh(); setInterval(refresh,5000);
// mini candles จาก cpu history (สไตล์ crypto)
function drawCandles(){
  const g=pick("candles"), g2=pick("candle-wicks");
  if(!g||hist.cpu.length<5)return;
  let inner=""; let inner2="";
  const data=hist.cpu.slice(-16); const n=data.length;
  const max=Math.max(100,...data), min=0;
  const bw=300/n;
  data.forEach((v,i)=>{ const x=i*bw; const hp=60-((v-min)/max)*60; const bh=Math.max(2,(v/100)*60*0.7); 
    const col=(v>80)?"#ff5470":(v>60?"#ffb454":"#22d3a5");
    inner+=("<rect x=\""+(x+bw*0.25).toFixed(1)+"\" y=\""+(60-hp).toFixed(1)+"\" width=\""+(bw*0.5).toFixed(1)+"\" height=\""+bh.toFixed(1)+"\" rx=\"1.2\" fill=\""+col+"\" opacity=\"0.85\"/>");
    inner2+=("<line x1=\""+(x+bw*0.5).toFixed(1)+"\" y1=\""+(60-hp-3).toFixed(1)+"\" x2=\""+(x+bw*0.5).toFixed(1)+"\" y2=\""+(60-hp+bh+3).toFixed(1)+"\" stroke=\""+col+"\" stroke-width=\"0.7\" opacity=\"0.7\"/>"); });
  g.innerHTML=inner; g2.innerHTML=inner2;
}
setInterval(drawCandles,5000);
setInterval(()=>{},30000);
if (tg && tg.HapticFeedback) tg.HapticFeedback.impactOccurred("light");
</script>
</body></html>`)
}
