package telegram

import (
	"bytes"
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
	"uni-activity/go-monitor/socks5"
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

type tgCallbackQuery struct {
	ID   string  `json:"id"`
	From *tgUser `json:"from"`
	Data string  `json:"data"`
}

type updateResponse struct {
	OK          bool   `json:"ok"`
	ErrorCode   int    `json:"error_code"`
	Description string `json:"description"`
	Result      []struct {
		UpdateID      int        `json:"update_id"`
		Message       *tgMessage `json:"message"`
		EditedMessage *tgMessage `json:"edited_message"`
		ChannelPost   *tgMessage
		CallbackQuery *tgCallbackQuery `json:"callback_query"`
	} `json:"result"`
}

// tgEnvVal อ่านค่าตัวแปรจาก .env ของโปรเจค (crowded: ใช้กับ REDIS_PASSWORD)
func tgEnvVal(key string) string {
	path := config.AppConfig.EnvPath
	b, err := os.ReadFile(path)
	if err != nil {
		return ""
	}
	for _, line := range strings.Split(string(b), "\n") {
		if strings.HasPrefix(line, key+"=") {
			v := strings.TrimSpace(strings.TrimPrefix(line, key+"="))
			return strings.Trim(v, "\"'")
		}
	}
	return ""
}
func StartBotPoller() {
	go func() {
		client := &http.Client{Timeout: 35 * time.Second}
		lastUpdateID := 0
		cmdsRegistered := false
		initialized := false

		for {
			token, configuredChatID := config.AppConfig.GetTelegramCreds()
			if token == "" || configuredChatID == "" {
				time.Sleep(10 * time.Second)
				continue
			}
			if !cmdsRegistered {
				cmds := []map[string]string{
					{"command": "status", "description": "ภาพรวมเซิร์ฟเวอร์ทั้งหมด"},
					{"command": "links", "description": "URL เว็บ+SSH สด (สถานะ per-link)"},
					{"command": "ssh", "description": "รายละเอียด SSH tunnel + วิธีเชื่อมต่อ"},
					{"command": "tunnel_status", "description": "สถานะ web/ssh tunnel แยกกัน"},
					{"command": "restart_web", "description": "รีเซ็ตเฉพาะ web tunnel (ssh ไม่กระทบ)"},
					{"command": "restart_ssh", "description": "รีเซ็ตเฉพาะ ssh tunnel (เว็บไม่กระทบ)"},
					{"command": "tunnel_restart", "description": "รีเซ็ตทั้ง web+ssh (ทุกลิงก์)"},
					{"command": "services", "description": "สถานะบริการหลักทั้งหมด"},
					{"command": "restart", "description": "รีสตาร์ทบริการที่หยุดทำงาน"},
					{"command": "control", "description": "ควบคุม service เช่น /control nginx restart"},
					{"command": "clear_cache", "description": "ล้าง Laravel Cache"},
					{"command": "cleanup", "description": "ล้างพื้นที่ + ไฟล์ขยะ + cache"},
					{"command": "metrics", "description": "Prometheus metrics สำคัญ"},
					{"command": "version", "description": "เวอร์ชัน go-monitor / cloudflared / cf-manager"},
					{"command": "alerts", "description": "การแจ้งเตือนที่เกิดขึ้น"},
					{"command": "report", "description": "ส่งรายงานสรุปทันที"},
					{"command": "db", "description": "สถานะ PostgreSQL"},
					{"command": "redis", "description": "สถานะ Redis/Valkey"},
					{"command": "network", "description": "สถิติ network traffic"},
					{"command": "logs", "description": "บันทึก Laravel ล่าสุด 15 บรรทัด"},
					{"command": "menu", "description": "เมนูปุ่มกด (Inline Keyboard)"},
					{"command": "virus", "description": "สแกนไวรัส/ไฟล์น่าสงสัย (webshell, double ext, ELF)"},
				}
				cj, _ := json.Marshal(map[string]interface{}{"commands": cmds})
				cresp, cerr := http.Post(
					fmt.Sprintf("https://api.telegram.org/bot%s/setMyCommands", token),
					"application/json", bytes.NewReader(cj),
				)
				if cerr == nil {
					b, _ := io.ReadAll(io.LimitReader(cresp.Body, 300))
					cresp.Body.Close()
					var okResp struct {
						OK   bool   `json:"ok"`
						Desc string `json:"description"`
					}
					_ = json.Unmarshal(b, &okResp)
					if cresp.StatusCode == 200 && okResp.OK {
						gcount := -1
						if gresp, gerr := http.Get(fmt.Sprintf("https://api.telegram.org/bot%s/getMyCommands", token)); gerr == nil {
							gb, _ := io.ReadAll(gresp.Body)
							gresp.Body.Close()
							var g struct {
								Result []interface{} `json:"result"`
							}
							if json.Unmarshal(gb, &g) == nil {
								gcount = len(g.Result)
							}
						}
						if gcount == len(cmds) {
							log.Printf("✅ [Telegram] setMyCommands verified (%d commands)", gcount)
							cmdsRegistered = true
						} else {
							log.Printf("⚠️ [Telegram] setMyCommands ok but getMyCommands=%d (expected %d)", gcount, len(cmds))
						}
					} else {
						log.Printf("⚠️ [Telegram] setMyCommands HTTP %d ok=%v desc=%s", cresp.StatusCode, okResp.OK, okResp.Desc)
					}
				}
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

				// callback_query for menu buttons — MUST be checked before the
				// message chain, because button presses carry no message at all
				if cq := u.CallbackQuery; cq != nil && cq.Data != "" {
					chatID := ""
					if cq.From != nil {
						chatID = strconv.FormatInt(cq.From.ID, 10)
					}
					log.Printf("🔘 [Telegram] Callback received: %q from %s", cq.Data, chatID)
					answerBody, _ := json.Marshal(map[string]interface{}{"callback_query_id": cq.ID})
					answerURL := fmt.Sprintf("https://api.telegram.org/bot%s/answerCallbackQuery", (func() string { t, _ := config.AppConfig.GetTelegramCreds(); return t }()))
					if resp, err := http.Post(answerURL, "application/json", bytes.NewBuffer(answerBody)); err == nil {
						resp.Body.Close()
					}
					go func() {
						defer func() {
							if r := recover(); r != nil {
								log.Printf(" Panic(cq) %v", r)
							}
						}()
						handleCommand(chatID, cq.Data)
					}()
					continue
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
					go func() {
						defer func() {
							if r := recover(); r != nil {
								log.Printf(" Panic(msg) %v", r)
							}
						}()
						handleCommand(msgChatID, text)
					}()
				}
			}
		}
	}()
}

// sendDirect sends an immediate message with optional reply_markup (bypasses queue)
func sendDirect(chatID, text, replyMarkup string) {
	token := (func() string { t, _ := config.AppConfig.GetTelegramCreds(); return t }())
	if token == "" || chatID == "" {
		return
	}
	payload := map[string]interface{}{
		"chat_id":                  chatID,
		"text":                     text,
		"parse_mode":               "HTML",
		"disable_web_page_preview": true,
	}
	if replyMarkup != "" {
		payload["reply_markup"] = json.RawMessage(replyMarkup)
	}
	body, _ := json.Marshal(payload)
	url := fmt.Sprintf("https://api.telegram.org/bot%s/sendMessage", token)
	client := &http.Client{Timeout: 15 * time.Second}
	resp, err := client.Post(url, "application/json", bytes.NewBuffer(body))
	if err != nil {
		log.Printf("⚠️ [Telegram] sendDirect HTTP error: %v", err)
		return
	}
	defer resp.Body.Close()
	if resp.StatusCode != 200 {
		b, _ := io.ReadAll(io.LimitReader(resp.Body, 300))
		log.Printf("⚠️ [Telegram] sendDirect HTTP %d: %s", resp.StatusCode, string(b))
	} else {
		log.Printf("📨 [Telegram] direct message sent to %s (HTTP 200)", chatID)
	}
}

func t2(ok bool, a, b string) string {
	if ok {
		return a
	}
	return b
}

// fetchWebURL — web tunnel URL ปัจจุบัน (สด) จาก go-monitor API
func fetchWebURL() string {
	resp, err := http.Get("http://127.0.0.1:9999/api/tunnel-urls")
	if err != nil {
		return ""
	}
	bj, _ := io.ReadAll(resp.Body)
	resp.Body.Close()
	var uj struct {
		HTTPURL string `json:"http_url"`
	}
	if json.Unmarshal(bj, &uj) == nil {
		return uj.HTTPURL
	}
	return ""
}

// webAppRowsJSON — แถวปุ่มเปิดเว็บ (web_app ปุ่มเปิดในแอป + url ปุ่ม browser)
// คืน "" ถ้าไม่มี live web URL
func webAppRowsJSON(webURL string) string {
	if webURL == "" || !strings.Contains(webURL, "trycloudflare.com") {
		return ""
	}
	q := strings.ReplaceAll(webURL, "\\", "\\\\")
	q = strings.ReplaceAll(q, "\"", "\\\"")
	return fmt.Sprintf(`,
		[{"text":"🌐 เปิดเว็บในแอป (WebApp)","web_app":{"url":"%s"}}],
		[{"text":"🔗 เปิดใน Browser","url":"%s"}]`, q, q)
}

// mainMenuMarkup + webApp rows appended tail — ทำให้ /menu มีปุ่มเปิดเว็บ
func mainMenuMarkup() string {
	base := `{"inline_keyboard":[
		[{"text":"📊 สถานะระบบ","callback_data":"cmd:/status"},{"text":"🌐 Tunnel URL","callback_data":"cmd:/url"}],
		[{"text":"🔑 SSH สถานะ/วิธีต่อ","callback_data":"cmd:/ssh"},{"text":"🖥 Services","callback_data":"cmd:/services"}],
		[{"text":"📝 ลิงก์ทั้งหมด","callback_data":"cmd:/links"},{"text":"📊 สถานะ Tunnel","callback_data":"cmd:/tunnel_status"}],
		[{"text":"💾 พื้นที่ Disk","callback_data":"cmd:/df"},{"text":"🧠 RAM","callback_data":"cmd:/mem"}],
		[{"text":"🔌 Ports","callback_data":"cmd:/ports"},{"text":"🗄 Database","callback_data":"cmd:/db"}],
		[{"text":"🚀 Redis/Valkey","callback_data":"cmd:/redis"},{"text":"📡 Network","callback_data":"cmd:/network"}],
		[{"text":"🔄 รีสตาร์ท Tunnel","callback_data":"cmd:/tunnel_restart"},{"text":"🧹 ล้าง Cache","callback_data":"cmd:/clear_cache"}],
		[{"text":"🔔 Alerts","callback_data":"cmd:/alerts"},{"text":"📝 รายงานวันนี้","callback_data":"cmd:/report"}],
		[{"text":"🦠 สแกนไวรัส","callback_data":"cmd:/virus"}],[{"text":"❓ คู่มือคำสั่งทั้งหมด","callback_data":"cmd:/help"}]`
	base += webAppRowsJSON(fetchWebURL())
	base += `
	]}`
	return base
}

func handleCommand(replyChat string, cmd string) {
	defer func() {
		if r := recover(); r != nil {
			log.Printf(" Panic in command %q: %v", cmd, r)
			SendToChatFast(replyChat, fmt.Sprintf("\u26a0\ufe0f Command <code>%s</code> crashed internally \u2014 logged for fix", cmd))
		}
	}()
	// menu buttons send "cmd:/status" — strip the prefix so the switch matches
	if strings.HasPrefix(cmd, "cmd:") {
		cmd = strings.TrimPrefix(cmd, "cmd:")
	}
	// Sanitize invisible Unicode: ZWSP U+200B, ZWNJ U+200C, ZWJ U+200D,
	// BOM U+FEFF, LRM/RLM U+200E/U+200F, NBSP U+00A0 (คีบอร์ดไทยชอบแทรก)
	// ถ้าไม่ตัด /help (มี ZWSP ติด) จะไม่ match case และไม่ตอบ
	zwRep := strings.NewReplacer(
		"\u200b", "", "\u200c", "", "\u200d", "",
		"\ufeff", "", "\u200e", "", "\u200f", "",
	)
	cmd = zwRep.Replace(cmd)
	cmd = strings.ReplaceAll(cmd, "\u00a0", " ")
	parts := strings.Fields(cmd)
	if len(parts) == 0 {
		log.Printf("🤖 [Telegram] Empty command after sanitize (raw=%q)", cmd)
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
			"/links, /web — URL เว็บ + SSH สดทั้งคู่ (สถานะ per-link)\n" +
			"/cf, /url, /tunnel — ดู Web Tunnel URL (จาก API สด)\n" +
			"/ssh, /ssh_status — ดูสถานะ SSH over Cloudflare + วิธีเชื่อมต่อ\n" +
			"/tunnel_restart — สั่งรีสตาร์ท Cloudflare Tunnel\n" +
			"/tunnel_status — สถานะ web/ssh tunnel แยกกัน\n" +
			"/restart — รีสตาร์ทบริการที่หยุดทำงาน\n" +
			"/restart_web — รีเซ็ตเฉพาะ web tunnel (ssh ไม่กระทบ)\n" +
			"/restart_ssh — รีเซ็ตเฉพาะ ssh tunnel (web ไม่กระทบ)\n" +
			"/clear_cache — ล้าง Laravel Cache ทั้งหมด\n" +
			"/cleanup — ล้างพื้นที่ + ไฟล์ขยะ + cache\n" +
			"/report — บังคับส่งรายงานสรุปประจำวันทันที\n" +
			"/proxy — สถานะ Squid & SOCKS5 Proxy\n" +
			"/virus — สแกนไวรัส/ไฟล์น่าสงสัย (webshell, PHP ฝัง, double-ext, ELF/PE)\n" +
			"/logs — ดูบันทึก Laravel ล่าสุด 15 บรรทัด\n" +
			"/kill &lt;pid&gt; — ปิด Process ตาม PID\n" +
			"/sql &lt;query&gt; — คิวรีฐานข้อมูล (Read-Only)\n\n" +
			"💰 <b>เมนู & เมตริก</b>\n" +
			"/menu — เปิดเมนูแบบปุ่มกดได้ (Inline Keyboard)\n" +
			"/metrics — ดู Prometheus Metrics สำคัญล่าสุด\n" +
			"/version — เวอร์ชัน go-monitor / cloudflared / cf-manager\n" +
			"/control &lt;target&gt; &lt;start|stop|restart&gt; — ควบคุม Service\n" +
			" targets: nginx, php_fpm, postgres, valkey, queue_worker, reverb, web_workers, squid, prometheus, grafana, all"
		SendToChatFast(replyChat, helpText)

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
		SendToChatFast(replyChat, res)

	case "/uptime":
		SendToChatFast(replyChat, fmt.Sprintf("🕐 <b>Uptime:</b> %s", sysinfo.GetUptime()))

	case "/load":
		l := sysinfo.GetLoad()
		SendToChatFast(replyChat, fmt.Sprintf("📈 <b>CPU Load:</b> 1m: <code>%.2f</code> | 5m: <code>%.2f</code> | 15m: <code>%.2f</code>", l[0], l[1], l[2]))

	case "/mem", "/memory":
		m := sysinfo.GetMemory()
		SendToChatFast(replyChat, fmt.Sprintf("🧠 <b>Memory:</b> %d / %d MB (<code>%.1f%%</code>)", m.UsedMB, m.TotalMB, m.Percent))

	case "/df", "/disk":
		d := sysinfo.GetDisk("/data/data/com.termux/files/home")
		SendToChatFast(replyChat, fmt.Sprintf("💾 <b>Storage:</b> %.1f / %.1f GB (<code>%.1f%% used</code>)", d.UsedGB, d.TotalGB, d.Percent))

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
		SendToChatFast(replyChat, b.String())

	case "/ports":
		ports := services.GetListeningPorts()
		var portStrs []string
		for _, p := range ports {
			portStrs = append(portStrs, strconv.Itoa(p))
		}
		SendToChatFast(replyChat, fmt.Sprintf("🔌 <b>Open TCP Ports:</b>\n<code>%s</code>", strings.Join(portStrs, ", ")))

	case "/top", "/ps":
		procs := sysinfo.GetTopProcesses()
		var b strings.Builder
		b.WriteString("🔥 <b>Top Resource Processes</b>\n━━━━━━━━━━━━━━━━━━━━\n")
		for _, p := range procs {
			b.WriteString(fmt.Sprintf("• <code>%-6s</code> %-15s CPU: <b>%.1f%%</b> | RAM: <b>%.1f%%</b>\n", p.PID, p.Name, p.CPU, p.Mem))
		}
		SendToChatFast(replyChat, b.String())

	case "/redis", "/valkey":
		pass := tgEnvVal("REDIS_PASSWORD")
		opts := []string{"-p", "6379"}
		if pass != "" {
			opts = append(opts, "-a", pass, "--no-auth-warning")
		}
		pingArgs := append(append([]string{}, opts...), "ping")
		out, err := exec.Command("valkey-cli", pingArgs...).CombinedOutput()
		if err != nil {
			out, err = exec.Command("redis-cli", pingArgs...).CombinedOutput()
		}
		st := strings.TrimSpace(string(out))
		if err != nil || st != "PONG" {
			SendToChatFast(replyChat, fmt.Sprintf("🔴 <b>Valkey/Redis:</b> Offline / Error (%s)", st))
		} else {
			infoArgs := append(append([]string{}, opts...), "info", "memory")
			infoOut, _ := exec.Command("valkey-cli", infoArgs...).CombinedOutput()
			SendToChatFast(replyChat, fmt.Sprintf("🟢 <b>Valkey/Redis:</b> Active (PONG)\n<pre>%s</pre>", strings.TrimSpace(string(infoOut))))
		}

	case "/db":
		out, err := exec.Command("psql", "-d", "uni_activity", "-t", "-c", "SELECT pg_size_pretty(pg_database_size('uni_activity')), count(*) FROM pg_stat_activity WHERE datname='uni_activity';").CombinedOutput()
		if err != nil {
			SendToChatFast(replyChat, fmt.Sprintf("🔴 <b>PostgreSQL:</b> Error\n<pre>%s</pre>", string(out)))
		} else {
			parts := strings.Fields(string(out))
			size := "N/A"
			conns := "0"
			if len(parts) >= 2 {
				size = parts[0]
				conns = parts[1]
			}
			SendToChatFast(replyChat, fmt.Sprintf("🐘 <b>PostgreSQL Status</b>\n━━━━━━━━━━━━━━━━━━━━\n💾 Database Size: <b>%s</b>\n🔌 Active Connections: <b>%s</b>", size, conns))
		}

	case "/network":
		netStats := sysinfo.GetNetwork()
		SendToChatFast(replyChat, fmt.Sprintf(
			"📡 <b>Network Traffic</b>\n━━━━━━━━━━━━━━━━━━━━\n"+
				"⬇️ Inbound: %s (Speed: %s)\n"+
				"⬆️ Outbound: %s (Speed: %s)",
			netStats.TotalRx, netStats.RxRate,
			netStats.TotalTx, netStats.TxRate,
		))

	case "/alerts":
		history := config.AppConfig.GetAlertHistory()
		if len(history) == 0 {
			SendToChatFast(replyChat, "✅ <b>No Active Alerts</b>\nระบบทำงานในสภาวะปกติ ไม่มีประวัติแจ้งเตือนค้างอยู่")
		} else {
			var b strings.Builder
			b.WriteString(fmt.Sprintf("⚠️ <b>Recent Alerts History (%d)</b>\n━━━━━━━━━━━━━━━━━━━━\n", len(history)))
			for i := len(history) - 1; i >= 0 && i >= len(history)-8; i-- {
				item := history[i]
				b.WriteString(fmt.Sprintf("• [%v] <b>%v</b>: %v\n  🕐 %v\n", item["type"], item["id"], item["message"], item["time"]))
			}
			SendToChatFast(replyChat, b.String())
		}

	case "/ssh", "/ssh_url", "/ssh_status":
		// URL live จาก tunnel.GetSSHURL() (metrics 20242) —
		// ไฟล์ ~/cloudflared-ssh.log ว่างเสมอตอนรันผ่าน runsv จึงไม่ใช้แล้ว
		// ดึง URL live ผ่าน API ภายใน (tunnel package ไม่ import กลับได้ — import cycle)
		sshURL := ""
		if resp, err := http.Get("http://localhost:9999/api/tunnel-urls"); err == nil {
			bj, _ := io.ReadAll(resp.Body)
			resp.Body.Close()
			var uj struct {
				SSHURL string `json:"ssh_url"`
			}
			if json.Unmarshal(bj, &uj) == nil {
				sshURL = uj.SSHURL
			}
		}
		procAlive := sshURL != ""
		if !procAlive {
			out, _ := exec.Command("pgrep", "-f", "cloudflared tunnel --url ssh://localhost:8022").Output()
			procAlive = len(strings.TrimSpace(string(out))) > 0
		}
		if sshURL == "" {
			sshURL = "offline"
		}
		status := "❌ OFFLINE"
		readiness := "⚠️ tunnel process not running"
		if procAlive {
			status = "✅ RUNNING"
			readiness = "✅ ready — connect from any device with cloudflared"
		}
		b := strings.Builder{}
		b.WriteString("🔐 <b>SSH over Cloudflare</b>\n")
		b.WriteString(fmt.Sprintf("Status: %s\n", status))
		b.WriteString(fmt.Sprintf("URL: <code>%s</code>\n", sshURL))
		// hostname แบบ copy-paste ได้เลย (ไม่มี https://)
		sshHost := strings.TrimPrefix(sshURL, "https://")
		sshHost = strings.TrimSuffix(sshHost, "/")
		b.WriteString("<b>How to connect (from any external device):</b>\n")
		b.WriteString("1. Install cloudflared on your device\n")
		b.WriteString("2. Run: <code>cloudflared access ssh --hostname " + sshHost + " --url localhost:2222</code>\n")
		b.WriteString("3. Then: <code>ssh -p 2222 u0_a175@localhost</code>\n")
		b.WriteString(fmt.Sprintf("Readiness: %s\n", readiness))
		b.WriteString("ℹ️ URL changes if tunnel restarts — check /ssh again")
		SendToChatFast(replyChat, b.String())

	case "/menu":
		menuMsg := "\U0001F3E0 <b>Uni-Activity Monitor</b>\n" +
			"━━━━━━━━━━━━━━━━━━\n" +
			"\u0e40\u0e25\u0e37\u0e2d\u0e01\u0e40\u0e21\u0e19\u0e39\u0e14\u0e49\u0e32\u0e19\u0e25\u0e48\u0e32\u0e07\u0e44\u0e14\u0e49\u0e40\u0e25\u0e22 \U0001F447\n\n" +
			"\u2705 <b>\u0e23\u0e30\u0e1a\u0e1a\u0e01\u0e33\u0e25\u0e31\u0e07\u0e17\u0e33\u0e07\u0e32\u0e19\u0e1b\u0e01\u0e15\u0e34</b> " + time.Now().Format("15:04 02/01/2006")
		sendDirect(replyChat, menuMsg, mainMenuMarkup())
	case "/cf", "/url", "/tunnel", "/tunnel_url":
		// v2: ดึงจาก API สด (metrics-backed) — ไฟล์ active_url.json อาจเก่ากว่า
		activeURL := "Checking / Tunnel offline"
		if resp, err := http.Get("http://127.0.0.1:9999/api/tunnel-urls"); err == nil {
			bj, _ := io.ReadAll(resp.Body)
			resp.Body.Close()
			var uj struct {
				HTTPURL string `json:"http_url"`
			}
			if json.Unmarshal(bj, &uj) == nil && uj.HTTPURL != "" {
				activeURL = uj.HTTPURL
			}
		}
		if activeURL == "Checking / Tunnel offline" {
			activeURLFile := filepath.Join(config.AppConfig.ProjectRoot, "docs/active_url.json")
			if b, err := os.ReadFile(activeURLFile); err == nil {
				var uObj struct {
					URL string `json:"url"`
				}
				if json.Unmarshal(b, &uObj) == nil && uObj.URL != "" {
					activeURL = uObj.URL
				}
			}
		}
		emoji := "🌐"
		if !strings.Contains(activeURL, "trycloudflare.com") {
			emoji = "⚠️"
		}
		SendToChatFast(replyChat, fmt.Sprintf("%s <b>Web Tunnel URL (live):</b>\n<code>%s</code>\n\n💡 /links = ดู web+ssh พร้อมกัน | /ssh = รายละเอียด SSH", emoji, activeURL))
	case "/links", "/web", "/urls":
		// /links — URL web + ssh สดทั้งคู่ + สถานะ ในข้อความเดียว
		httpURL, sshURL := "offline", "offline"
		if resp, err := http.Get("http://127.0.0.1:9999/api/tunnel-urls"); err == nil {
			bj, _ := io.ReadAll(resp.Body)
			resp.Body.Close()
			var uj struct {
				HTTPURL string `json:"http_url"`
				SSHURL  string `json:"ssh_url"`
			}
			if json.Unmarshal(bj, &uj) == nil {
				if uj.HTTPURL != "" {
					httpURL = uj.HTTPURL
				}
				if uj.SSHURL != "" {
					sshURL = uj.SSHURL
				}
			}
		}
		webOK := strings.Contains(httpURL, "trycloudflare.com")
		sshOK := strings.Contains(sshURL, "trycloudflare.com")
		b := strings.Builder{}
		b.WriteString("🔗 <b>Public Links (live)</b>\n━━━━━━━━━━━━━━━━━━━━\n")
		b.WriteString(fmt.Sprintf("%s <b>Web:</b> <code>%s</code>\n", t2(webOK, "✅", "❌"), httpURL))
		b.WriteString(fmt.Sprintf("%s <b>SSH:</b> <code>%s</code>\n", t2(sshOK, "✅", "❌"), sshURL))
		if webOK {
			b.WriteString("\n📂 เปิดเว็บ: <code>" + httpURL + "/login</code> — แสดง HTML ปกติ\n")
		} else {
			b.WriteString("\n⚠️ web tunnel ยังไม่ขึ้น — cf-manager จะ respawn อัตโนมัติ (เฉพาะ web ไม่กระทบ ssh)\n")
		}
		if sshOK {
			sshHost := strings.TrimPrefix(sshURL, "https://")
			b.WriteString("💻 เชื่อม SSH: <code>cloudflared access ssh --hostname " + sshHost + " --url localhost:2222</code>\n")
		}
		// 🌐 ปุ่มเปิดเว็บในแอป (WebApp) + browser
		if webOK {
			kb := fmt.Sprintf(`{"inline_keyboard":[
				[{"text":"🌐 เปิดเว็บในแอป (WebApp)","web_app":{"url":"%s"}}],
				[{"text":"🔗 เปิดใน Browser","url":"%s"}]]}`, httpURL, httpURL)
			sendDirect(replyChat, b.String(), kb)
		} else {
			SendToChatFast(replyChat, b.String())
		}

	case "/tunnel_status":
		httpURL, sshURL := "offline", "offline"
		if resp, err := http.Get("http://127.0.0.1:9999/api/tunnel-urls"); err == nil {
			bj, _ := io.ReadAll(resp.Body)
			resp.Body.Close()
			var uj struct {
				HTTPURL string `json:"http_url"`
				SSHURL  string `json:"ssh_url"`
			}
			if json.Unmarshal(bj, &uj) == nil {
				httpURL, sshURL = uj.HTTPURL, uj.SSHURL
			}
		}
		webOK := strings.Contains(httpURL, "trycloudflare.com")
		sshOK := strings.Contains(sshURL, "trycloudflare.com")
		msg := fmt.Sprintf("📊 <b>Tunnel Status (per-link independent)</b>\n━━━━━━━━━━━━━━━━━━━━\n"+
			"🌐 <b>Web:</b> %s\n%s\n\n"+
			"🔐 <b>SSH:</b> %s\n%s\n\n"+
			"💡 ตัวใด error → รีเซ็ตเฉพาะตัวนั้น (/restart_web หรือ /restart_ssh) — อีกตัวไม่กระทบ",
			t2(webOK, "✅ UP", "❌ DOWN"), httpURL,
			t2(sshOK, "✅ UP", "❌ DOWN"), sshURL)
		SendToChatFast(replyChat, msg)

	case "/restart_web":
		SendToChatFast(replyChat, "🔄 กำลังรีเซ็ต <b>เฉพาะ WEB tunnel</b> — SSH ยังใช้ URL เดิมได้ต่อ…")
		go func() {
			_ = exec.Command("pkill", "-9", "-f", "cloudflared tunnel --url http://").Run()
			time.Sleep(3 * time.Second)
			// รอ cf-manager จัดการ (ไม่ต้อง spawn เอง — ป้องกัน race)
			SendToChatFast(replyChat, "✅ WEB tunnel killed — cf-manager auto-respawn ใน ≤15 วิ\nรอ ~30 วิ แล้วดึง URL ใหม่ด้วย /links")
		}()

	case "/restart_ssh":
		SendToChatFast(replyChat, "🔄 กำลังรีเซ็ต <b>เฉพาะ SSH tunnel</b> — เว็บไม่กระทบ…")
		go func() {
			_ = exec.Command("pkill", "-9", "-f", "cloudflared tunnel --url ssh://").Run()
			time.Sleep(3 * time.Second)
			SendToChatFast(replyChat, "✅ SSH tunnel killed — cf-manager auto-respawn ใน ≤15 วิ\nรอ ~30 วิ แล้วดึง URL ใหม่ด้วย /links")
		}()

	case "/version":
		gmVer := "unknown"
		if b, err := os.ReadFile("/data/data/com.termux/files/home/uni-activity/go-monitor/VERSION"); err == nil {
			gmVer = strings.TrimSpace(string(b))
		}
		cfVerOut, _ := exec.Command("cloudflared", "--version").Output()
		cfmOut, _ := exec.Command("stat", "-c", "%y", "/data/data/com.termux/files/home/uni-activity/go-monitor/cf-manager-bin").Output()
		msg := fmt.Sprintf("ℹ️ <b>Versions</b>\n━━━━━━━━━━━━━━━━━━━━\n"+
			"🤖 go-monitor: <code>%s</code>\n"+
			"☁️ cloudflared: <code>%s</code>\n"+
			"🔧 cf-manager bin: <code>%s</code>",
			gmVer, strings.TrimSpace(string(cfVerOut)), strings.TrimSpace(string(cfmOut)))
		SendToChatFast(replyChat, msg)

	case "/cleanup":
		SendToChatFast(replyChat, "🧹 กำลังล้างพื้นที่ (Laravel logs/cache + old system tmp)…")
		go func() {
			var lines []string
			// Laravel logs
			logCmd := "find /data/data/com.termux/files/home/uni-activity/storage/logs -name '*.log' -size +10M -exec truncate -s 0 {} +"
			if _, err := exec.Command("sh", "-c", logCmd).CombinedOutput(); err == nil {
				lines = append(lines, "laravel logs >10MB truncated")
			} else {
				lines = append(lines, "laravel logs: "+err.Error())
			}
			// /tmp files > 50MB
			if out, err := exec.Command("sh", "-c",
				"find /data/data/com.termux/files/usr/tmp -type f -size +50M -delete 2>/dev/null; echo ok").CombinedOutput(); err == nil && strings.Contains(string(out), "ok") {
				lines = append(lines, "usr/tmp >50MB cleared")
			}
			if out, err := exec.Command("php", "/data/data/com.termux/files/home/uni-activity/artisan", "cache:clear").CombinedOutput(); err == nil {
				lines = append(lines, "laravel cache:clear OK")
			} else {
				lines = append(lines, "laravel cache:clear err: "+strings.TrimSpace(string(out)))
			}
			// df after
			out, _ := exec.Command("df", "-h", "/data").Output()
			lines = append(lines, "\n<pre>"+strings.TrimSpace(string(out))+"</pre>")
			SendToChatFast(replyChat, "✅ <b>Cleanup เสร็จ</b>\n"+strings.Join(lines, "\n"))
		}()

	case "/tunnel_restart":
		SendToChatFast(replyChat, "🔄 Triggering Cloudflare Tunnel restart via cf-manager...")
		go func() {
			out, err := exec.Command("pkill", "-9", "-f", "cloudflared").CombinedOutput()
			if err != nil {
				SendToChatFast(replyChat, fmt.Sprintf("⚠️ Tunnel restart triggered (kill result: %s)", string(out)))
			} else {
				SendToChatFast(replyChat, "✅ Cloudflared killed. cf-manager will spawn a fresh instance automatically.")
			}
		}()

	case "/restart":
		SendToChatFast(replyChat, "🔄 กำลังตรวจสอบและรีสตาร์ทบริการที่หยุดทำงาน...")
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
				SendToChatFast(replyChat, fmt.Sprintf("✅ <b>Restarted Services:</b> %s", strings.Join(restarted, ", ")))
			} else {
				SendToChatFast(replyChat, "✅ ทุกบริการหลัก Running อยู่แล้ว ไม่จำเป็นต้องรีสตาร์ท")
			}
		}()

	case "/clear_cache":
		SendToChatFast(replyChat, "🧹 กำลังล้าง Laravel Cache ทั้งหมด...")
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
			SendToChatFast(replyChat, fmt.Sprintf("🧹 <b>Clear Cache Results</b>\n━━━━━━━━━━━━━━━━━━━━\n%s", strings.Join(results, "\n")))
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
		SendToChatFast(replyChat, rep)

	case "/proxy":
		sactive, sreq, sdenied, sbytes := socks5.Stats()
		squidOK := exec.Command("pgrep", "-x", "squid").Run() == nil
		sq := "✅ Running"
		if !squidOK {
			sq = "❌ Stopped"
		}
		socks := "✅ Running (built-in Go)"
		if sactive <= 0 && sreq <= 0 {
			socks = "🟢 Ready (built-in Go) — 0 requests yet"
		}
		SendToChatFast(replyChat, fmt.Sprintf(
			"🌐 <b>Proxy System Status</b>\n────────────────────\n"+
				"Squid HTTP (:3128): %s\n"+
				"SOCKS5 (:1080): %s\n"+
				"• active conns: %d • total req: %d • denied: %d\n"+
				"• bytes relayed: %d\n"+
				"<i>ดูสถิติละเอียดที่ Dashboard (:9999/#proxy) • /metrics • /control</i>",
			sq, socks, sactive, sreq, sdenied, sbytes))

	case "/kill":
		if len(parts) < 2 {
			SendToChatFast(replyChat, "⚠️ กรุณาระบุ PID: <code>/kill 1234</code>")
			return
		}
		pid := parts[1]
		if _, err := strconv.Atoi(pid); err != nil {
			SendToChatFast(replyChat, "❌ Invalid PID")
			return
		}
		out, err := exec.Command("kill", "-9", pid).CombinedOutput()
		if err != nil {
			SendToChatFast(replyChat, fmt.Sprintf("❌ Error killing PID %s: %s", pid, string(out)))
		} else {
			SendToChatFast(replyChat, fmt.Sprintf("✅ Process PID <code>%s</code> terminated.", pid))
		}

	case "/virus":
		log.Printf("🦠 [Telegram] Virus scan requested by chat=%s", replyChat)
		SendToChatFast(replyChat, "🦠 เริ่มสแกนไวรัส — รายงานสรุปจะส่งตามมา (ราว 1-2 นาที)")
		go runVirusScan(replyChat)

	case "/logs":
		logPath := filepath.Join(config.AppConfig.ProjectRoot, "storage/logs/laravel.log")
		out, err := exec.Command("tail", "-n", "15", logPath).CombinedOutput()
		if err != nil || len(out) == 0 {
			SendToChatFast(replyChat, "ℹ️ No recent Laravel logs or file empty.")
		} else {
			SendToChatFast(replyChat, fmt.Sprintf("📋 <b>Recent Laravel Logs:</b>\n<pre>%s</pre>", string(out)))
		}

	case "/sql":
		if len(parts) < 2 {
			SendToChatFast(replyChat, "⚠️ กรุณาระบุคำสั่ง SQL: <code>/sql SELECT count(*) FROM users;</code>")
			return
		}
		query := strings.Join(parts[1:], " ")
		upper := strings.ToUpper(query)
		if strings.Contains(upper, "DROP") || strings.Contains(upper, "DELETE") || strings.Contains(upper, "UPDATE") || strings.Contains(upper, "TRUNCATE") {
			SendToChatFast(replyChat, "⛔ เฉพาะคำสั่ง SELECT (Read-Only) เท่านั้น")
			return
		}
		out, err := exec.Command("psql", "-d", "uni_activity", "-t", "-c", query).CombinedOutput()
		if err != nil {
			SendToChatFast(replyChat, fmt.Sprintf("❌ SQL Error:\n<pre>%s</pre>", string(out)))
		} else {
			res := strings.TrimSpace(string(out))
			if len(res) > 3000 {
				res = res[:3000] + "..."
			}
			SendToChatFast(replyChat, fmt.Sprintf("📊 <b>SQL Result:</b>\n<pre>%s</pre>", res))
		}

	case "/metrics":
		mout, merr := exec.Command("curl", "-s", "--max-time", "8", "http://127.0.0.1:9999/metrics").CombinedOutput()
		if merr != nil {
			SendToChatFast(replyChat, "X Fetch metrics failed: "+merr.Error())
			return
		}
		want := []string{"hw_cpu_usage_percent", "hw_memory_usage_percent", "hw_load_avg_1m", "hw_uptime_seconds", "squid_cache_hit_ratio", "pgbouncer_up", "pg_up", "hw_up"}
		var sel []string
		for _, line := range strings.Split(string(mout), "\n") {
			for _, w := range want {
				if strings.HasPrefix(line, w) {
					sel = append(sel, line)
					break
				}
			}
		}
		if len(sel) == 0 {
			sel = []string{"(no matching metrics lines)"}
		}
		SendToChatFast(replyChat, fmt.Sprintf("\U0001f4ca <b>Prometheus Metrics</b>\n<pre>%s</pre>", strings.Join(sel, "\n")))

	case "/control":
		if len(parts) < 3 {
			SendToChatFast(replyChat, "Usage: <code>/control &lt;target&gt; &lt;start|stop|restart&gt;</code>\ntargets: nginx, php_fpm, postgres, valkey, queue_worker, reverb, web_workers, squid, prometheus, grafana, all")
			return
		}
		target, action := parts[1], strings.ToLower(parts[2])
		cbody := fmt.Sprintf(`{"target":"%s","action":"%s"}`, target, action)
		cresp, cerr := http.Post("http://127.0.0.1:9999/api/control", "application/json", strings.NewReader(cbody))
		if cerr != nil {
			SendToChatFast(replyChat, "X control API failed: "+cerr.Error())
			return
		}
		defer cresp.Body.Close()
		var jr map[string]string
		json.NewDecoder(cresp.Body).Decode(&jr)
		cemoji := "\u2705"
		if jr["status"] != "ok" {
			cemoji = "\u274c"
		}
		SendToChatFast(replyChat, cemoji+" <b>/control</b> "+target+" "+action+"\n"+jr["message"])

	default:
		// Unknown command handler - never stay completely silent
		SendToChatFast(replyChat, fmt.Sprintf(
			"⚠️ ไม่รู้จักคำสั่ง: <code>%s</code>\nพิมพ์ /help เพื่อดูคำสั่งทั้งหมดที่มีในระบบ",
			name,
		))
	}
}
