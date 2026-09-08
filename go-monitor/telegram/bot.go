package telegram

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/services"
	"uni-activity/go-monitor/sysinfo"
)

type tgUser struct {
	ID        int64  `json:"id"`
	IsBot     bool   `json:"is_bot"`
	FirstName string `json:"first_name"`
	Username  string `json:"username"`
}

type tgChat struct {
	ID    int64  `json:"id"`
	Type  string `json:"type"`
	Title string `json:"title"`
}

type tgMessage struct {
	MessageID int     `json:"message_id"`
	From      *tgUser `json:"from"`
	Chat      tgChat  `json:"chat"`
	Date      int64   `json:"date"`
	Text      string  `json:"text"`
}

type updateResponse struct {
	OK          bool   `json:"ok"`
	ErrorCode   int    `json:"error_code"`
	Description string `json:"description"`
	Result      []struct {
		UpdateID      int        `json:"update_id"`
		Message       *tgMessage `json:"message"`
		EditedMessage *tgMessage `json:"edited_message"`
		ChannelPost   *tgMessage `json:"channel_post"`
	} `json:"result"`
}

func StartBotPoller() {
	go func() {
		client := &http.Client{Timeout: 35 * time.Second}
		lastUpdateID := 0
		initialized := false

		for {
			token, configuredChatID := config.AppConfig.GetTelegramCreds()
			if token == "" || configuredChatID == "" {
				time.Sleep(10 * time.Second)
				continue
			}

			// On initial startup, query with offset=-1 to acknowledge old backlogs
			offsetParam := lastUpdateID + 1
			if !initialized {
				offsetParam = -1
			}

			url := fmt.Sprintf(
				"https://api.telegram.org/bot%s/getUpdates?offset=%d&limit=10&timeout=25",
				token, offsetParam,
			)

			resp, err := client.Get(url)
			if err != nil {
				log.Printf("⚠️ [Telegram Poller] Network error: %v (retrying in 5s)", err)
				time.Sleep(5 * time.Second)
				continue
			}

			body, err := io.ReadAll(resp.Body)
			resp.Body.Close()
			if err != nil {
				time.Sleep(1 * time.Second)
				continue
			}

			var data updateResponse
			if err := json.Unmarshal(body, &data); err != nil {
				log.Printf("⚠️ [Telegram Poller] JSON unmarshal error: %v", err)
				time.Sleep(3 * time.Second)
				continue
			}

			if !data.OK {
				log.Printf("⚠️ [Telegram Poller] API returned not OK (%d: %s)", data.ErrorCode, data.Description)
				if data.ErrorCode == 409 {
					// 409 Conflict: another poller or webhook is active
					time.Sleep(10 * time.Second)
				} else {
					time.Sleep(3 * time.Second)
				}
				continue
			}

			// If first run with offset=-1, record latest update_id and switch to continuous polling
			if !initialized {
				if len(data.Result) > 0 {
					lastUpdateID = data.Result[len(data.Result)-1].UpdateID
					log.Printf("🤖 [Telegram Poller] Synchronized latest update_id=%d", lastUpdateID)
				} else {
					log.Printf("🤖 [Telegram Poller] Poller initialized (empty queue)")
				}
				initialized = true
				continue
			}

			for _, u := range data.Result {
				if u.UpdateID > lastUpdateID {
					lastUpdateID = u.UpdateID
				}

				msg := u.Message
				if msg == nil {
					msg = u.EditedMessage
				}
				if msg == nil {
					msg = u.ChannelPost
				}
				if msg == nil {
					continue
				}

				// Discard messages older than 10 minutes (600 seconds)
				msgAge := time.Now().Unix() - msg.Date
				if msg.Date > 0 && msgAge > 600 {
					log.Printf("ℹ️ [Telegram Poller] Skipped stale message (age %d s): %q", msgAge, msg.Text)
					continue
				}

				msgChatID := strconv.FormatInt(msg.Chat.ID, 10)
				var fromUserID string
				if msg.From != nil {
					fromUserID = strconv.FormatInt(msg.From.ID, 10)
				}

				// Authorization: Chat ID matches OR User ID matches configured owner chat ID
				isAuthorized := (msgChatID == configuredChatID) || (fromUserID != "" && fromUserID == configuredChatID)
				if !isAuthorized {
					log.Printf("⛔ [Telegram] Unauthorized access attempt: Chat=%s From=%s Text=%q", msgChatID, fromUserID, msg.Text)
					SendToChat(configuredChatID, fmt.Sprintf("⛔ <b>Unauthorized access attempt</b>\nChat ID: <code>%s</code>\nUser ID: <code>%s</code>\nMessage: <code>%s</code>", msgChatID, fromUserID, msg.Text))
					continue
				}

				text := strings.TrimSpace(msg.Text)
				if text != "" {
					log.Printf("🤖 [Telegram] Command received from %s: %s", msgChatID, text)
					go handleCommand(msgChatID, text)
				}
			}
		}
	}()
}

func handleCommand(replyChat string, cmd string) {
	parts := strings.Fields(cmd)
	if len(parts) == 0 {
		return
	}
	name := strings.ToLower(parts[0])
	if idx := strings.Index(name, "@"); idx != -1 {
		name = name[:idx]
	}

	switch name {
	case "/start", "/help":
		helpText := "🤖 <b>Uni-Activity Go Monitor Commands</b>\n" +
			"━━━━━━━━━━━━━━━━━━━━\n" +
			"📊 <b>สถานะและการทำงาน</b>\n" +
			"/status — ภาพรวมเซิร์ฟเวอร์ทั้งหมด\n" +
			"/uptime — เวลาทำงานต่อเนื่อง\n" +
			"/load — โหลด CPU (1m, 5m, 15m)\n" +
			"/mem, /memory — การใช้ RAM\n" +
			"/df, /disk — พื้นที่ความจุ Disk\n" +
			"/top, /ps — Top resource processes\n" +
			"/services — สถานะ 8 บริการหลัก\n" +
			"/ports — พอร์ต TCP ที่เปิดอยู่\n" +
			"/redis — สถานะ Redis / Valkey\n" +
			"/db — สถานะ PostgreSQL Database\n" +
			"/network — สถิติ Network Traffic\n" +
			"/alerts — การแจ้งเตือนที่เกิดขึ้น\n\n" +
			"⚙️ <b>การจัดการระบบ & เครือข่าย</b>\n" +
			"/cf, /url, /tunnel — ดู Cloudflare Tunnel URL\n" +
			"/tunnel_restart — สั่งรีสตาร์ท Cloudflare Tunnel\n" +
			"/restart — รีสตาร์ทบริการที่หยุดทำงาน\n" +
			"/clear_cache — ล้าง Laravel Cache ทั้งหมด\n" +
			"/report — บังคับส่งรายงานสรุปประจำวันทันที\n" +
			"/proxy — สถานะ Squid & SOCKS5 Proxy\n" +
			"/logs — ดูบันทึก Laravel ล่าสุด 15 บรรทัด\n" +
			"/kill &lt;pid&gt; — ปิด Process ตาม PID\n" +
			"/sql &lt;query&gt; — คิวรีฐานข้อมูล (Read-Only)"
		SendToChat(replyChat, helpText)

	case "/status", "/ping":
		mem := sysinfo.GetMemory()
		load := sysinfo.GetLoad()
		disk := sysinfo.GetDisk("/data/data/com.termux/files/home")
		temp := sysinfo.GetTemp()
		uptime := sysinfo.GetUptime()
		svcs := services.CheckAllServices()

		runningSvcs := 0
		for _, s := range svcs {
			if s == "Running" {
				runningSvcs++
			}
		}

		res := fmt.Sprintf(
			"⚡ <b>Server Status (Go Native)</b>\n"+
				"━━━━━━━━━━━━━━━━━━━━\n"+
				"🕐 Uptime: %s\n"+
				"🔥 Temp: %s°C\n"+
				"📈 Load: %.2f, %.2f, %.2f\n"+
				"🧠 RAM: %d / %d MB (%.1f%%)\n"+
				"💾 Disk: %.1f / %.1f GB (%.1f%%)\n"+
				"🛡️ Services: %d / %d Running",
			uptime, temp, load[0], load[1], load[2],
			mem.UsedMB, mem.TotalMB, mem.Percent,
			disk.UsedGB, disk.TotalGB, disk.Percent,
			runningSvcs, len(svcs),
		)
		SendToChat(replyChat, res)

	case "/uptime":
		SendToChat(replyChat, fmt.Sprintf("🕐 <b>Uptime:</b> %s", sysinfo.GetUptime()))

	case "/load":
		l := sysinfo.GetLoad()
		SendToChat(replyChat, fmt.Sprintf("📈 <b>CPU Load:</b> 1m: <code>%.2f</code> | 5m: <code>%.2f</code> | 15m: <code>%.2f</code>", l[0], l[1], l[2]))

	case "/mem", "/memory":
		m := sysinfo.GetMemory()
		SendToChat(replyChat, fmt.Sprintf("🧠 <b>Memory:</b> %d / %d MB (<code>%.1f%%</code>)", m.UsedMB, m.TotalMB, m.Percent))

	case "/df", "/disk":
		d := sysinfo.GetDisk("/data/data/com.termux/files/home")
		SendToChat(replyChat, fmt.Sprintf("💾 <b>Storage:</b> %.1f / %.1f GB (<code>%.1f%% used</code>)", d.UsedGB, d.TotalGB, d.Percent))

	case "/services":
		svcs := services.CheckAllServices()
		var b strings.Builder
		b.WriteString("🛡️ <b>Services Status</b>\n━━━━━━━━━━━━━━━━━━━━\n")
		for name, st := range svcs {
			icon := "🟢"
			if st != "Running" {
				icon = "🔴"
			}
			b.WriteString(fmt.Sprintf("%s %s: <b>%s</b>\n", icon, name, st))
		}
		SendToChat(replyChat, b.String())

	case "/ports":
		ports := services.GetListeningPorts()
		var portStrs []string
		for _, p := range ports {
			portStrs = append(portStrs, strconv.Itoa(p))
		}
		SendToChat(replyChat, fmt.Sprintf("🔌 <b>Open TCP Ports:</b>\n<code>%s</code>", strings.Join(portStrs, ", ")))

	case "/top", "/ps":
		procs := sysinfo.GetTopProcesses()
		var b strings.Builder
		b.WriteString("🔥 <b>Top Resource Processes</b>\n━━━━━━━━━━━━━━━━━━━━\n")
		for _, p := range procs {
			b.WriteString(fmt.Sprintf("• <code>%-6s</code> %-15s CPU: <b>%.1f%%</b> | RAM: <b>%.1f%%</b>\n", p.PID, p.Name, p.CPU, p.Mem))
		}
		SendToChat(replyChat, b.String())

	case "/redis", "/valkey":
		out, err := exec.Command("valkey-cli", "ping").CombinedOutput()
		if err != nil {
			out, err = exec.Command("redis-cli", "ping").CombinedOutput()
		}
		st := strings.TrimSpace(string(out))
		if err != nil || st != "PONG" {
			SendToChat(replyChat, fmt.Sprintf("🔴 <b>Valkey/Redis:</b> Offline / Error (%s)", st))
		} else {
			infoOut, _ := exec.Command("valkey-cli", "info", "memory").CombinedOutput()
			SendToChat(replyChat, fmt.Sprintf("🟢 <b>Valkey/Redis:</b> Active (PONG)\n<pre>%s</pre>", strings.TrimSpace(string(infoOut))))
		}

	case "/db":
		out, err := exec.Command("psql", "-d", "uni_activity", "-t", "-c", "SELECT pg_size_pretty(pg_database_size('uni_activity')), count(*) FROM pg_stat_activity WHERE datname='uni_activity';").CombinedOutput()
		if err != nil {
			SendToChat(replyChat, fmt.Sprintf("🔴 <b>PostgreSQL:</b> Error\n<pre>%s</pre>", string(out)))
		} else {
			parts := strings.Fields(string(out))
			size := "N/A"
			conns := "0"
			if len(parts) >= 2 {
				size = parts[0]
				conns = parts[1]
			}
			SendToChat(replyChat, fmt.Sprintf("🐘 <b>PostgreSQL Status</b>\n━━━━━━━━━━━━━━━━━━━━\n💾 Database Size: <b>%s</b>\n🔌 Active Connections: <b>%s</b>", size, conns))
		}

	case "/network":
		netStats := sysinfo.GetNetwork()
		SendToChat(replyChat, fmt.Sprintf(
			"📡 <b>Network Traffic</b>\n━━━━━━━━━━━━━━━━━━━━\n"+
				"⬇️ Inbound: %s (Speed: %s)\n"+
				"⬆️ Outbound: %s (Speed: %s)",
			netStats.TotalRx, netStats.RxRate,
			netStats.TotalTx, netStats.TxRate,
		))

	case "/alerts":
		history := config.AppConfig.GetAlertHistory()
		if len(history) == 0 {
			SendToChat(replyChat, "✅ <b>No Active Alerts</b>\nระบบทำงานในสภาวะปกติ ไม่มีประวัติแจ้งเตือนค้างอยู่")
		} else {
			var b strings.Builder
			b.WriteString(fmt.Sprintf("⚠️ <b>Recent Alerts History (%d)</b>\n━━━━━━━━━━━━━━━━━━━━\n", len(history)))
			for i := len(history) - 1; i >= 0 && i >= len(history)-8; i-- {
				item := history[i]
				b.WriteString(fmt.Sprintf("• [%v] <b>%v</b>: %v\n  🕐 %v\n", item["type"], item["id"], item["message"], item["time"]))
			}
			SendToChat(replyChat, b.String())
		}

	case "/cf", "/url", "/tunnel", "/tunnel_url":
		activeURL := "Checking / Tunnel offline"
		activeURLFile := filepath.Join(config.AppConfig.ProjectRoot, "docs/active_url.json")
		if b, err := os.ReadFile(activeURLFile); err == nil {
			var uObj struct {
				URL string `json:"url"`
			}
			if json.Unmarshal(b, &uObj) == nil && uObj.URL != "" {
				activeURL = uObj.URL
			}
		}
		SendToChat(replyChat, fmt.Sprintf("🌐 <b>Active Tunnel URL:</b>\n<code>%s</code>\n\nSSH: <code>ssh -p 8022 %s</code>", activeURL, activeURL))

	case "/tunnel_restart":
		SendToChat(replyChat, "🔄 Triggering Cloudflare Tunnel restart via cf-manager...")
		go func() {
			out, err := exec.Command("pkill", "-9", "-f", "cloudflared").CombinedOutput()
			if err != nil {
				SendToChat(replyChat, fmt.Sprintf("⚠️ Tunnel restart triggered (kill result: %s)", string(out)))
			} else {
				SendToChat(replyChat, "✅ Cloudflared killed. cf-manager will spawn a fresh instance automatically.")
			}
		}()

	case "/restart":
		SendToChat(replyChat, "🔄 กำลังตรวจสอบและรีสตาร์ทบริการที่หยุดทำงาน...")
		go func() {
			svcs := services.CheckAllServices()
			var restarted []string
			for name, st := range svcs {
				if st != "Running" {
					switch name {
					case "PHP-FPM":
						exec.Command("php-fpm", "--daemonize").Run()
						restarted = append(restarted, name)
					case "Nginx":
						exec.Command("nginx").Run()
						restarted = append(restarted, name)
					case "PostgreSQL":
						exec.Command("pg_ctl", "start", "-D", "/data/data/com.termux/files/usr/var/lib/postgresql").Run()
						restarted = append(restarted, name)
					case "Valkey / Redis":
						exec.Command("valkey-server", "--port", "6379", "--bind", "0.0.0.0", "--daemonize", "yes").Run()
						restarted = append(restarted, name)
					}
				}
			}
			if len(restarted) > 0 {
				SendToChat(replyChat, fmt.Sprintf("✅ <b>Restarted Services:</b> %s", strings.Join(restarted, ", ")))
			} else {
				SendToChat(replyChat, "✅ ทุกบริการหลัก Running อยู่แล้ว ไม่จำเป็นต้องรีสตาร์ท")
			}
		}()

	case "/clear_cache":
		SendToChat(replyChat, "🧹 กำลังล้าง Laravel Cache ทั้งหมด...")
		go func() {
			root := config.AppConfig.ProjectRoot
			cmds := [][]string{
				{"php", "artisan", "config:clear"},
				{"php", "artisan", "cache:clear"},
				{"php", "artisan", "route:clear"},
				{"php", "artisan", "view:clear"},
			}
			var results []string
			for _, c := range cmds {
				cmdObj := exec.Command(c[0], c[1:]...)
				cmdObj.Dir = root
				out, err := cmdObj.CombinedOutput()
				tag := "✅"
				if err != nil || strings.Contains(strings.ToLower(string(out)), "error") {
					tag = "❌"
				}
				results = append(results, fmt.Sprintf("%s %s", tag, c[2]))
			}
			SendToChat(replyChat, fmt.Sprintf("🧹 <b>Clear Cache Results</b>\n━━━━━━━━━━━━━━━━━━━━\n%s", strings.Join(results, "\n")))
		}()

	case "/report", "/force_report":
		mem := sysinfo.GetMemory()
		disk := sysinfo.GetDisk("/data/data/com.termux/files/home")
		load := sysinfo.GetLoad()
		svcs := services.CheckAllServices()
		running := 0
		for _, s := range svcs {
			if s == "Running" {
				running++
			}
		}
		ts := time.Now().Format("2006-01-02 15:04")
		rep := fmt.Sprintf(
			"📊 <b>Server Report</b>\n"+
				"━━━━━━━━━━━━━━━━━━━━\n"+
				"🕐 %s\n"+
				"⚡ Load Avg: <code>%.2f / %.2f / %.2f</code>\n"+
				"🧠 RAM: <b>%d / %d MB (%.1f%%)</b>\n"+
				"💾 Disk: <b>%.1f / %.1f GB (%.1f%%)</b>\n"+
				"🛡️ Services: <b>%d / %d Running</b>\n"+
				"🌐 Uptime: <b>%s</b>",
			ts, load[0], load[1], load[2],
			mem.UsedMB, mem.TotalMB, mem.Percent,
			disk.UsedGB, disk.TotalGB, disk.Percent,
			running, len(svcs),
			sysinfo.GetUptime(),
		)
		SendToChat(replyChat, rep)

	case "/proxy":
		SendToChat(replyChat, "🌐 <b>Proxy System Status</b>\n━━━━━━━━━━━━━━━━━━━━\nSquid HTTP (:3128)\nSOCKS5 (:1080)\n<i>ดูสถิติทราฟฟิกละเอียดได้ที่ Dashboard (:9999/#proxy)</i>")

	case "/kill":
		if len(parts) < 2 {
			SendToChat(replyChat, "⚠️ กรุณาระบุ PID: <code>/kill 1234</code>")
			return
		}
		pid := parts[1]
		if _, err := strconv.Atoi(pid); err != nil {
			SendToChat(replyChat, "❌ Invalid PID")
			return
		}
		out, err := exec.Command("kill", "-9", pid).CombinedOutput()
		if err != nil {
			SendToChat(replyChat, fmt.Sprintf("❌ Error killing PID %s: %s", pid, string(out)))
		} else {
			SendToChat(replyChat, fmt.Sprintf("✅ Process PID <code>%s</code> terminated.", pid))
		}

	case "/logs":
		logPath := filepath.Join(config.AppConfig.ProjectRoot, "storage/logs/laravel.log")
		out, err := exec.Command("tail", "-n", "15", logPath).CombinedOutput()
		if err != nil || len(out) == 0 {
			SendToChat(replyChat, "ℹ️ No recent Laravel logs or file empty.")
		} else {
			SendToChat(replyChat, fmt.Sprintf("📋 <b>Recent Laravel Logs:</b>\n<pre>%s</pre>", string(out)))
		}

	case "/sql":
		if len(parts) < 2 {
			SendToChat(replyChat, "⚠️ กรุณาระบุคำสั่ง SQL: <code>/sql SELECT count(*) FROM users;</code>")
			return
		}
		query := strings.Join(parts[1:], " ")
		upper := strings.ToUpper(query)
		if strings.Contains(upper, "DROP") || strings.Contains(upper, "DELETE") || strings.Contains(upper, "UPDATE") || strings.Contains(upper, "TRUNCATE") {
			SendToChat(replyChat, "⛔ เฉพาะคำสั่ง SELECT (Read-Only) เท่านั้น")
			return
		}
		out, err := exec.Command("psql", "-d", "uni_activity", "-t", "-c", query).CombinedOutput()
		if err != nil {
			SendToChat(replyChat, fmt.Sprintf("❌ SQL Error:\n<pre>%s</pre>", string(out)))
		} else {
			res := strings.TrimSpace(string(out))
			if len(res) > 3000 {
				res = res[:3000] + "..."
			}
			SendToChat(replyChat, fmt.Sprintf("📊 <b>SQL Result:</b>\n<pre>%s</pre>", res))
		}

	default:
		// Unknown command handler - never stay completely silent
		SendToChat(replyChat, fmt.Sprintf(
			"⚠️ ไม่รู้จักคำสั่ง: <code>%s</code>\nพิมพ์ /help เพื่อดูคำสั่งทั้งหมดที่มีในระบบ",
			name,
		))
	}
}
