package telegram

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/services"
	"uni-activity/go-monitor/sysinfo"
)

type updateResponse struct {
	OK     bool `json:"ok"`
	Result []struct {
		UpdateID int `json:"update_id"`
		Message  struct {
			Date int `json:"date"`
			Chat struct {
				ID int64 `json:"id"`
			} `json:"chat"`
			Text string `json:"text"`
		} `json:"message"`
	} `json:"result"`
}

func StartBotPoller() {
	go func() {
		client := &http.Client{Timeout: 35 * time.Second}
		lastUpdateID := 0

		for {
			token, chatID := config.AppConfig.GetTelegramCreds()
			if token == "" || chatID == "" {
				time.Sleep(10 * time.Second)
				continue
			}

			url := fmt.Sprintf(
				"https://api.telegram.org/bot%s/getUpdates?offset=%d&limit=10&timeout=25",
				token, lastUpdateID+1,
			)

			resp, err := client.Get(url)
			if err != nil {
				time.Sleep(3 * time.Second)
				continue
			}

			body, err := io.ReadAll(resp.Body)
			resp.Body.Close()
			if err != nil {
				time.Sleep(1 * time.Second)
				continue
			}

			var data updateResponse
			if err := json.Unmarshal(body, &data); err != nil || !data.OK {
				time.Sleep(3 * time.Second)
				continue
			}

			for _, u := range data.Result {
				lastUpdateID = u.UpdateID
				// Discard old messages older than 2 minutes
				if time.Now().Unix()-int64(u.Message.Date) > 120 {
					continue
				}

				msgChat := strconv.FormatInt(u.Message.Chat.ID, 10)
				if msgChat != chatID {
					Send(fmt.Sprintf("⛔ Unauthorized access attempt from Chat ID: <code>%s</code>", msgChat))
					continue
				}

				text := strings.TrimSpace(u.Message.Text)
				if text != "" {
					log.Printf("🤖 Received Telegram command: %s", text)
					go handleCommand(text)
				}
			}
		}
	}()
}

func handleCommand(cmd string) {
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
			"/status — ภาพรวมเซิร์ฟเวอร์\n" +
			"/uptime — เวลาทำงานต่อเนื่อง\n" +
			"/load — โหลด CPU (1m, 5m, 15m)\n" +
			"/mem — การใช้ RAM\n" +
			"/df — พื้นที่ความจุ Disk\n" +
			"/top — Top 5 processes ที่กิน CPU\n" +
			"/services — สถานะ 8 บริการหลัก\n" +
			"/ports — พอร์ต TCP ที่เปิดอยู่\n\n" +
			"⚙️ <b>การจัดการ</b>\n" +
			"/cf — URL Cloudflare Tunnel ล่าสุด\n" +
			"/logs — ดูบันทึก Laravel ล่าสุด 15 บรรทัด\n" +
			"/kill &lt;pid&gt; — ปิด Process ตาม PID\n" +
			"/sql &lt;query&gt; — คิวรีฐานข้อมูล (Read-Only)"
		Send(helpText)

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
		Send(res)

	case "/uptime":
		Send(fmt.Sprintf("🕐 <b>Uptime:</b> %s", sysinfo.GetUptime()))

	case "/load":
		l := sysinfo.GetLoad()
		Send(fmt.Sprintf("📈 <b>CPU Load:</b> 1m: <code>%.2f</code> | 5m: <code>%.2f</code> | 15m: <code>%.2f</code>", l[0], l[1], l[2]))

	case "/mem":
		m := sysinfo.GetMemory()
		Send(fmt.Sprintf("🧠 <b>Memory:</b> %d / %d MB (<code>%.1f%%</code>)", m.UsedMB, m.TotalMB, m.Percent))

	case "/df":
		d := sysinfo.GetDisk("/data/data/com.termux/files/home")
		Send(fmt.Sprintf("💾 <b>Storage:</b> %.1f / %.1f GB (<code>%.1f%% used</code>)", d.UsedGB, d.TotalGB, d.Percent))

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
		Send(b.String())

	case "/ports":
		ports := services.GetListeningPorts()
		var portStrs []string
		for _, p := range ports {
			portStrs = append(portStrs, strconv.Itoa(p))
		}
		Send(fmt.Sprintf("🔌 <b>Open TCP Ports:</b>\n<code>%s</code>", strings.Join(portStrs, ", ")))

	case "/top", "/ps":
		procs := sysinfo.GetTopProcesses()
		var b strings.Builder
		b.WriteString("🔥 <b>Top Resource Processes</b>\n━━━━━━━━━━━━━━━━━━━━\n")
		for _, p := range procs {
			b.WriteString(fmt.Sprintf("• <code>%-6s</code> %-15s CPU: <b>%.1f%%</b> | RAM: <b>%.1f%%</b>\n", p.PID, p.Name, p.CPU, p.Mem))
		}
		Send(b.String())

	case "/kill":
		if len(parts) < 2 {
			Send("⚠️ กรุณาระบุ PID: <code>/kill 1234</code>")
			return
		}
		pid := parts[1]
		if _, err := strconv.Atoi(pid); err != nil {
			Send("❌ Invalid PID")
			return
		}
		out, err := exec.Command("kill", "-9", pid).CombinedOutput()
		if err != nil {
			Send(fmt.Sprintf("❌ Error killing PID %s: %s", pid, string(out)))
		} else {
			Send(fmt.Sprintf("✅ Process PID <code>%s</code> terminated.", pid))
		}

	case "/cf", "/url":
		activeURL := filepath.Join(config.AppConfig.ProjectRoot, "docs/active_url.json")
		if b, err := exec.Command("cat", activeURL).Output(); err == nil {
			Send(fmt.Sprintf("🌐 <b>Active Tunnel URL:</b>\n<code>%s</code>", string(b)))
		} else {
			Send("⚠️ No active_url.json found.")
		}

	case "/logs":
		logPath := filepath.Join(config.AppConfig.ProjectRoot, "storage/logs/laravel.log")
		out, err := exec.Command("tail", "-n", "15", logPath).CombinedOutput()
		if err != nil || len(out) == 0 {
			Send("ℹ️ No recent Laravel logs or file empty.")
		} else {
			Send(fmt.Sprintf("📋 <b>Recent Laravel Logs:</b>\n<pre>%s</pre>", string(out)))
		}

	case "/sql":
		if len(parts) < 2 {
			Send("⚠️ กรุณาระบุคำสั่ง SQL: <code>/sql SELECT count(*) FROM users;</code>")
			return
		}
		query := strings.Join(parts[1:], " ")
		upper := strings.ToUpper(query)
		if strings.Contains(upper, "DROP") || strings.Contains(upper, "DELETE") || strings.Contains(upper, "UPDATE") || strings.Contains(upper, "TRUNCATE") {
			Send("⛔ เฉพาะคำสั่ง SELECT (Read-Only) เท่านั้น")
			return
		}
		out, err := exec.Command("psql", "-d", "uni_activity", "-t", "-c", query).CombinedOutput()
		if err != nil {
			Send(fmt.Sprintf("❌ SQL Error:\n<pre>%s</pre>", string(out)))
		} else {
			res := strings.TrimSpace(string(out))
			if len(res) > 3000 {
				res = res[:3000] + "..."
			}
			Send(fmt.Sprintf("📊 <b>SQL Result:</b>\n<pre>%s</pre>", res))
		}
	}
}
