package telegram

import (
	"bytes"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"time"

	"uni-activity/go-monitor/config"
)

var msgChan = make(chan string, 100)

func init() {
	go worker()
}

func worker() {
	client := &http.Client{Timeout: 10 * time.Second}
	for text := range msgChan {
		token, chatID := config.AppConfig.GetTelegramCreds()
		if token == "" || chatID == "" {
			continue
		}

		url := fmt.Sprintf("https://api.telegram.org/bot%s/sendMessage", token)
		body, _ := json.Marshal(map[string]interface{}{
			"chat_id":                  chatID,
			"text":                     text,
			"parse_mode":               "HTML",
			"disable_web_page_preview": true,
		})

		for attempt := 0; attempt < 3; attempt++ {
			resp, err := client.Post(url, "application/json", bytes.NewBuffer(body))
			if err == nil {
				sc := resp.StatusCode
				resp.Body.Close()
				if sc == 200 {
					log.Printf("📨 Telegram message sent successfully (HTTP %d)", sc)
					break
				}
				log.Printf("⚠️ Telegram API returned HTTP %d", sc)
			} else {
				log.Printf("⚠️ Telegram POST error (attempt %d): %v", attempt+1, err)
			}
			time.Sleep(1 * time.Second)
		}
		// Throttle between messages to avoid 429
		time.Sleep(300 * time.Millisecond)
	}
}

// Send queues a message for Telegram
func Send(text string) {
	select {
	case msgChan <- text:
	default:
		log.Println("⚠️ Telegram message queue full, dropping message")
	}
}

func SendAlert(id, alertType, message string) {
	icon := "⚠️"
	if alertType == "critical" {
		icon = "🚨"
	}
	ts := time.Now().Format("2006-01-02 15:04:05")
	text := fmt.Sprintf("%s <b>ALERT: %s</b>\n━━━━━━━━━━━━━━━━━━━━\n🕐 %s\n📝 %s", icon, alertType, ts, message)
	Send(text)
}

func SendResolved(id, message string) {
	ts := time.Now().Format("2006-01-02 15:04:05")
	text := fmt.Sprintf("✅ <b>RESOLVED: %s</b>\n━━━━━━━━━━━━━━━━━━━━\n🕐 %s\n📝 %s", id, ts, message)
	Send(text)
}

func SendStartup(port int) {
	time.Sleep(3 * time.Second)
	ts := time.Now().Format("2006-01-02 15:04:05")
	text := fmt.Sprintf(
		"🟢 <b>Go Monitor Server Started</b>\n"+
			"━━━━━━━━━━━━━━━━━━━━\n"+
			"🕐 %s\n"+
			"🌐 Port: %d (Native Single Binary)\n"+
			"📡 Alerts: <b>Active</b>\n\n"+
			"<i>จะแจ้งเตือนเมื่อ:</i>\n"+
			"• 🚨 Service down\n"+
			"• ⚠️ CPU load &gt; 8.0\n"+
			"• ⚠️ Memory &gt; 90%%\n"+
			"• ⚠️ Disk &gt; 90%%\n"+
			"• ⚠️ Temp &gt; 75°C\n"+
			"• 🌐 Cloudflare offline",
		ts, port,
	)
	Send(text)
}
