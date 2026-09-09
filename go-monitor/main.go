package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/signal"
	"path/filepath"
	"strings"
	"sync"
	"syscall"
	"time"

	"uni-activity/go-monitor/collector"
	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/server"
	"uni-activity/go-monitor/telegram"
	"uni-activity/go-monitor/tunnel"
)

func main() {
	portFlag := flag.Int("port", 9999, "HTTP port to listen on")
	flag.Parse()

	log.Printf("🚀 Starting Pure Go Monitor Agent on port %d...", *portFlag)

	// Determine project root and static dist directory
	projectRoot := "/data/data/com.termux/files/home/uni-activity"
	if _, err := os.Stat(projectRoot); err != nil {
		cwd, _ := os.Getwd()
		projectRoot = filepath.Dir(cwd)
	}

	staticDir := filepath.Join(projectRoot, "monitor-ui", "dist")
	log.Printf("📂 Project Root: %s", projectRoot)
	log.Printf("🎨 Static UI Dir: %s", staticDir)

	// Initialize Configuration & Environment (.env)
	config.InitConfig(projectRoot)

	// Start Background Engines
	log.Println("🤖 Starting Telegram Bot Poller...")
	telegram.StartBotPoller()

	log.Println("☁️ Starting Cloudflare Tunnel Watcher...")
	tunnel.StartTunnelWatcher()

	// Initialize Telemetry Collector
	col := collector.NewCollector(projectRoot)

	// Initial collection snapshot
	initialData, err := col.Collect()
	if err != nil {
		log.Printf("⚠️ Initial collection warning: %v", err)
	} else {
		log.Printf("✅ Initial telemetry snapshot collected (%d bytes)", len(initialData))
	}

	wakeChan := make(chan struct{}, 1)
	wake := func() {
		select {
		case wakeChan <- struct{}{}:
		default:
		}
	}

	// Create WebSocket Hub with Instant Push on Connect
	var hub *server.WSHub
	hub = server.NewWSHub(
		func(c net.Conn) {
			// Instant Push to newly connected client
			cached := col.GetCachedJSON()
			if len(cached) > 0 {
				_ = hub.SendDirect(c, cached)
			}
			log.Printf("🔌 Web Client Connected (Active clients: %d)", hub.ClientCount())
			wake()
		},
		func() {
			log.Printf("🔌 Web Client Disconnected (Remaining clients: %d)", hub.ClientCount())
		},
	)

	// ── 1. UDP Receiver Goroutines (Ports 9998 & 9997) ────────────────────────
	startUDPReceiver(9998, func(data []byte) {
		var item map[string]interface{}
		if err := json.Unmarshal(data, &item); err == nil {
			enrichInspectorItem(item)
			col.AddInspectorLog(item)
		}
	})

	startUDPReceiver(9997, func(data []byte) {
		var item map[string]interface{}
		if err := json.Unmarshal(data, &item); err == nil {
			enrichInspectorItem(item)
			col.AddInspectorLog(item)
		}
	})

	// ── 1b. Real-time Inspector Push ──────────────────────────────────────────
	// Hook into collector: whenever a new Inspector log arrives via UDP,
	// immediately broadcast the patched JSON to all connected WS clients.
	col.OnInspectorAdded = func(patched []byte) {
		if hub.ClientCount() > 0 {
			hub.Broadcast(patched)
			log.Printf("📡 [Inspector] Hot-pushed %d bytes to %d client(s)", len(patched), hub.ClientCount())
		}
	}

	// ── 2. Adaptive Stats Collector & Realtime Streaming Goroutine ───────────
	go func() {
		for {
			clients := hub.ClientCount()
			if clients > 0 {
				select {
				case <-time.After(2500 * time.Millisecond):
					data, err := col.Collect()
					if err == nil {
						hub.Broadcast(data)
					}
				}
			} else {
				// Idle mode: sleep 30s OR wake instantly when client connects
				select {
				case <-time.After(30 * time.Second):
					_, _ = col.Collect()
				case <-wakeChan:
					// Instantly woken up by incoming client
				}
			}
		}
	}()

	// ── 3. HTTP & WebSocket Server (Port 9999 or specified) ───────────────────
	httpServer := server.NewHTTPServer(staticDir, col, hub)
	srv := &http.Server{
		Addr:         fmt.Sprintf(":%d", *portFlag),
		Handler:      httpServer,
		ReadTimeout:  10 * time.Second,
		WriteTimeout: 10 * time.Second,
	}

	go func() {
		log.Printf("🌐 Serving React Dashboard and WebSocket on http://0.0.0.0:%d", *portFlag)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("❌ HTTP server error: %v", err)
		}
	}()

	// Notify Telegram of Startup
	go telegram.SendStartup(*portFlag)

	// ── 4. Graceful Shutdown ──────────────────────────────────────────────────
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, os.Interrupt, syscall.SIGTERM)
	<-sigChan

	log.Println("🛑 Shutting down Go Monitor gracefully...")
	_ = srv.Close()
	log.Println("👋 Shutdown complete.")
}

func startUDPReceiver(port int, handler func([]byte)) {
	addr := net.UDPAddr{
		Port: port,
		IP:   net.ParseIP("0.0.0.0"),
	}
	conn, err := net.ListenUDP("udp", &addr)
	if err != nil {
		log.Printf("⚠️ Could not bind UDP port %d: %v", port, err)
		return
	}
	log.Printf("📡 Listening for UDP telemetry on port %d", port)

	go func() {
		defer conn.Close()
		buf := make([]byte, 65535)
		for {
			n, _, err := conn.ReadFromUDP(buf)
			if err != nil {
				break
			}
			if n > 0 {
				handler(buf[:n])
			}
		}
	}()
}

var (
	logSeq   uint64
	logSeqMu sync.Mutex
)

var countryNames = map[string]string{
	"TH": "Thailand",
	"US": "United States",
	"SG": "Singapore",
	"JP": "Japan",
	"GB": "United Kingdom",
	"DE": "Germany",
	"FR": "France",
	"AU": "Australia",
	"NL": "Netherlands",
	"HK": "Hong Kong",
	"TW": "Taiwan",
	"KR": "South Korea",
	"CN": "China",
	"VN": "Vietnam",
	"MY": "Malaysia",
	"ID": "Indonesia",
	"PH": "Philippines",
	"IN": "India",
	"CA": "Canada",
	"RU": "Russia",
	"BR": "Brazil",
	"SE": "Sweden",
	"FI": "Finland",
	"NO": "Norway",
	"CH": "Switzerland",
	"IE": "Ireland",
}

type GeoDetail struct {
	City        string `json:"city"`
	Region      string `json:"region"`
	Country     string `json:"country"`
	CountryCode string `json:"country_code"`
	ISP         string `json:"isp"`
	Location    string `json:"location"`
	Origin      string `json:"origin"`
	OriginType  string `json:"origin_type"`
}

var (
	geoCache  sync.Map
	geoClient = &http.Client{Timeout: 1200 * time.Millisecond}
)

func resolveGeoDetail(ipStr string, countryCode string, cityHint string, regionHint string) *GeoDetail {
	ip := net.ParseIP(ipStr)
	if ip == nil || ip.IsLoopback() || ipStr == "127.0.0.1" || ipStr == "::1" || ipStr == "localhost" {
		return &GeoDetail{
			City:        "Localhost",
			Region:      "Internal",
			Country:     "Localhost",
			CountryCode: "LOCAL",
			ISP:         "System Loopback",
			Location:    "Localhost (Server Internal)",
			Origin:      "Localhost",
			OriginType:  "loopback",
		}
	}

	// Check Private / LAN ranges
	if ip.IsPrivate() || strings.HasPrefix(ipStr, "192.168.") || strings.HasPrefix(ipStr, "10.") || strings.HasPrefix(ipStr, "172.16.") || strings.HasPrefix(ipStr, "172.17.") || strings.HasPrefix(ipStr, "172.18.") || strings.HasPrefix(ipStr, "172.19.") || strings.HasPrefix(ipStr, "172.2") || strings.HasPrefix(ipStr, "172.3") || strings.HasPrefix(ipStr, "100.64.") {
		return &GeoDetail{
			City:        "Local LAN",
			Region:      "Private Subnet",
			Country:     "LAN",
			CountryCode: "LAN",
			ISP:         "Local Area Network",
			Location:    "Local Network (LAN / Wi-Fi)",
			Origin:      "Local Network (LAN)",
			OriginType:  "lan",
		}
	}

	// Check in-memory cache first
	if val, ok := geoCache.Load(ipStr); ok {
		if detail, ok := val.(*GeoDetail); ok {
			return detail
		}
	}

	// If city or region hints were passed from Cloudflare headers
	if cityHint != "" || regionHint != "" {
		cName := countryNames[strings.ToUpper(countryCode)]
		if cName == "" {
			cName = countryCode
		}
		loc := ""
		if cityHint != "" && regionHint != "" && cityHint != regionHint {
			loc = fmt.Sprintf("%s, %s, %s", cityHint, regionHint, cName)
		} else if cityHint != "" {
			loc = fmt.Sprintf("%s, %s", cityHint, cName)
		} else if regionHint != "" {
			loc = fmt.Sprintf("%s, %s", regionHint, cName)
		} else {
			loc = cName
		}
		orig := cName
		if cityHint != "" {
			orig = fmt.Sprintf("%s, %s", cityHint, cName)
		}
		detail := &GeoDetail{
			City:        cityHint,
			Region:      regionHint,
			Country:     cName,
			CountryCode: countryCode,
			ISP:         "Cloudflare Network",
			Location:    loc,
			Origin:      orig,
			OriginType:  "wan",
		}
		geoCache.Store(ipStr, detail)
		return detail
	}

	// Fast lookup via ip-api.com
	resp, err := geoClient.Get(fmt.Sprintf("http://ip-api.com/json/%s?fields=status,country,countryCode,regionName,city,isp", ipStr))
	if err == nil {
		defer resp.Body.Close()
		var res struct {
			Status      string `json:"status"`
			Country     string `json:"country"`
			CountryCode string `json:"countryCode"`
			RegionName  string `json:"regionName"`
			City        string `json:"city"`
			ISP         string `json:"isp"`
		}
		if err := json.NewDecoder(resp.Body).Decode(&res); err == nil && res.Status == "success" {
			loc := ""
			if res.City != "" && res.RegionName != "" && res.City != res.RegionName {
				loc = fmt.Sprintf("%s, %s, %s", res.City, res.RegionName, res.Country)
			} else if res.City != "" {
				loc = fmt.Sprintf("%s, %s", res.City, res.Country)
			} else {
				loc = res.Country
			}
			orig := res.Country
			if res.City != "" {
				orig = fmt.Sprintf("%s, %s", res.City, res.Country)
			}
			detail := &GeoDetail{
				City:        res.City,
				Region:      res.RegionName,
				Country:     res.Country,
				CountryCode: res.CountryCode,
				ISP:         res.ISP,
				Location:    loc,
				Origin:      orig,
				OriginType:  "wan",
			}
			geoCache.Store(ipStr, detail)
			return detail
		}
	}

	// Fallback if lookup failed
	cName := countryNames[strings.ToUpper(countryCode)]
	if cName == "" {
		if countryCode != "" {
			cName = countryCode
		} else {
			cName = "Public Internet"
		}
	}
	detail := &GeoDetail{
		City:        "",
		Region:      "",
		Country:     cName,
		CountryCode: countryCode,
		ISP:         "External Internet",
		Location:    cName,
		Origin:      cName,
		OriginType:  "wan",
	}
	geoCache.Store(ipStr, detail)
	return detail
}

func enrichInspectorItem(item map[string]interface{}) {
	logSeqMu.Lock()
	logSeq++
	seq := logSeq
	logSeqMu.Unlock()

	if id, ok := item["id"].(string); !ok || id == "" {
		item["id"] = fmt.Sprintf("act-%d-%d", time.Now().UnixMilli(), seq)
	}
	if _, ok := item["time"]; !ok {
		item["time"] = time.Now().Format(time.RFC3339)
	}
	if _, ok := item["method"]; !ok {
		item["method"] = "HTTP"
	}
	if _, ok := item["path"]; !ok {
		item["path"] = "/"
	}
	if _, ok := item["status"]; !ok {
		item["status"] = 200
	}
	if _, ok := item["duration"]; !ok {
		item["duration"] = 0
	}

	// Ensure request structure
	req, ok := item["request"].(map[string]interface{})
	if !ok || req == nil {
		req = make(map[string]interface{})
	}
	if _, ok := req["headers"]; !ok {
		headers := map[string]interface{}{
			"Host":       "127.0.0.1",
			"User-Agent": "UniActivity-Client",
		}
		if u, ok := item["url"].(string); ok && u != "" {
			headers["URL"] = u
		}
		req["headers"] = headers
	}
	if _, ok := req["body"]; !ok {
		req["body"] = ""
	}
	if _, ok := req["query"]; !ok {
		rawURL := ""
		if u, ok := item["url"].(string); ok && strings.Contains(u, "?") {
			rawURL = u
		} else if p, ok := item["path"].(string); ok && strings.Contains(p, "?") {
			rawURL = p
		}
		if rawURL != "" {
			parts := strings.SplitN(rawURL, "?", 2)
			if len(parts) > 1 {
				if parsedQuery, err := url.ParseQuery(parts[1]); err == nil && len(parsedQuery) > 0 {
					qMap := make(map[string]interface{})
					for k, v := range parsedQuery {
						if len(v) == 1 {
							qMap[k] = v[0]
						} else {
							qMap[k] = v
						}
					}
					req["query"] = qMap
				}
			}
		}
	}
	item["request"] = req

	// Resolve Real IP, Country, City, Region & Origin
	ipStr, _ := item["ip"].(string)
	countryCode, _ := item["country"].(string)
	cityHint, _ := item["city"].(string)
	regionHint, _ := item["region"].(string)
	rayID, _ := item["ray"].(string)

	headers, _ := req["headers"].(map[string]interface{})
	if headers != nil {
		for k, v := range headers {
			if strings.EqualFold(k, "cf-ipcountry") && countryCode == "" {
				countryCode = fmt.Sprintf("%v", v)
			}
			if strings.EqualFold(k, "cf-ipcity") && cityHint == "" {
				cityHint = fmt.Sprintf("%v", v)
			}
			if strings.EqualFold(k, "cf-region") && regionHint == "" {
				regionHint = fmt.Sprintf("%v", v)
			}
			if strings.EqualFold(k, "cf-ray") && rayID == "" {
				rayID = fmt.Sprintf("%v", v)
			}
			if strings.EqualFold(k, "cf-connecting-ip") {
				val := strings.TrimSpace(fmt.Sprintf("%v", v))
				if val != "" && net.ParseIP(val) != nil {
					ipStr = val
				}
			} else if strings.EqualFold(k, "x-real-ip") {
				val := strings.TrimSpace(fmt.Sprintf("%v", v))
				if val != "" && net.ParseIP(val) != nil && (ipStr == "" || ipStr == "127.0.0.1") {
					ipStr = val
				}
			} else if strings.EqualFold(k, "x-forwarded-for") {
				val := strings.TrimSpace(fmt.Sprintf("%v", v))
				if val != "" && (ipStr == "" || ipStr == "127.0.0.1" || strings.HasPrefix(ipStr, "192.168.")) {
					for _, part := range strings.Split(val, ",") {
						p := strings.TrimSpace(part)
						pip := net.ParseIP(p)
						if pip != nil && !pip.IsLoopback() && !pip.IsPrivate() {
							ipStr = p
							break
						}
					}
				}
			}
		}
	}

	if ipStr == "" {
		ipStr = "127.0.0.1"
	}
	item["ip"] = ipStr

	// Protocol & HTTPS detection
	proto, _ := item["protocol"].(string)
	isHTTPS, _ := item["is_https"].(bool)
	urlStr, _ := item["url"].(string)

	if !isHTTPS {
		if strings.HasPrefix(strings.ToLower(urlStr), "https://") || rayID != "" || strings.EqualFold(proto, "HTTPS") {
			isHTTPS = true
			proto = "HTTPS"
		}
	}
	if proto == "" {
		if isHTTPS {
			proto = "HTTPS"
		} else {
			proto = "HTTP"
		}
	}
	item["protocol"] = proto
	item["is_https"] = isHTTPS

	detail := resolveGeoDetail(ipStr, countryCode, cityHint, regionHint)
	item["origin"] = detail.Origin
	item["origin_type"] = detail.OriginType
	item["location"] = detail.Location
	item["city"] = detail.City
	item["region"] = detail.Region
	item["country"] = detail.CountryCode
	item["country_name"] = detail.Country
	item["isp"] = detail.ISP

	gateway := "Direct HTTP"
	if rayID != "" || isHTTPS || (headers != nil && headers["cf-connecting-ip"] != nil) {
		gateway = "Cloudflare Tunnel (HTTPS)"
		if rayID != "" {
			item["ray"] = rayID
		}
	} else if detail.OriginType == "loopback" {
		gateway = "Internal Loopback"
	} else if detail.OriginType == "lan" {
		gateway = "Local Subnet / LAN"
	}
	item["gateway"] = gateway

	// Ensure User Identity (Who)
	user, ok := item["user"].(map[string]interface{})
	methodStr, _ := item["method"].(string)
	pathStr, _ := item["path"].(string)
	if !ok || user == nil {
		if methodStr == "SHELL" || methodStr == "ARTISAN" {
			user = map[string]interface{}{
				"is_authenticated": true,
				"id":               nil,
				"name":             "System Console (ส่วนกลาง)",
				"role":             "system",
				"student_id":       nil,
				"email":            nil,
			}
		} else {
			user = map[string]interface{}{
				"is_authenticated": false,
				"id":               nil,
				"name":             "Guest Visitor (ผู้เยี่ยมชม)",
				"role":             "guest",
				"student_id":       nil,
				"email":            nil,
			}
		}
	}
	item["user"] = user

	// Ensure Action & Section (What & Which section)
	if act, _ := item["action"].(string); act == "" {
		if methodStr == "ARTISAN" {
			item["action"] = "รันคำสั่ง Artisan CLI"
		} else if methodStr == "SHELL" {
			item["action"] = "รันคำสั่ง Shell Command"
		} else if pathStr == "/" || pathStr == "" {
			item["action"] = "เข้าสู่หน้าหลัก (Visit Homepage)"
		} else {
			item["action"] = fmt.Sprintf("เข้าชมข้อมูล (%s %s)", methodStr, pathStr)
		}
	}

	if sec, _ := item["section"].(string); sec == "" {
		if methodStr == "ARTISAN" || methodStr == "SHELL" {
			item["section"] = "CLI / System Console"
		} else if strings.HasPrefix(pathStr, "admin") {
			item["section"] = "Admin Management (ส่วนผู้ดูแลระบบ)"
		} else if strings.HasPrefix(pathStr, "student") {
			item["section"] = "Student Portal (ส่วนนักศึกษา)"
		} else if strings.HasPrefix(pathStr, "activities") {
			item["section"] = "Activities Hub (ส่วนกิจกรรม)"
		} else if strings.Contains(pathStr, "scanner") || strings.Contains(pathStr, "checkin") {
			item["section"] = "AI Face Scanner (ส่วนสแกนใบหน้า)"
		} else if strings.HasPrefix(pathStr, "login") || strings.HasPrefix(pathStr, "password") {
			item["section"] = "Authentication & Security (ระบบเข้าสู่ระบบ)"
		} else {
			item["section"] = "General / Public (ส่วนทั่วไป)"
		}
	}

	if _, ok := item["route"]; !ok {
		item["route"] = ""
	}
	if _, ok := item["controller"]; !ok {
		item["controller"] = ""
	}
	if _, ok := item["referer"]; !ok {
		item["referer"] = ""
	}

	// Ensure response structure
	res, ok := item["response"].(map[string]interface{})
	if !ok || res == nil {
		res = make(map[string]interface{})
	}
	if _, ok := res["headers"]; !ok {
		res["headers"] = map[string]interface{}{}
	}
	if _, ok := res["body"]; !ok {
		res["body"] = fmt.Sprintf("HTTP %v Status", item["status"])
	}
	item["response"] = res
}
