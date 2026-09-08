package telegram

import (
	"bytes"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"strings"
	"time"

	"uni-activity/go-monitor/config"
)

type OutgoingMessage struct {
	ChatID string
	Text   string
}

var msgChan = make(chan OutgoingMessage, 200)

func init() {
	go worker()
}

func splitMessage(text string, limit int) []string {
	if len(text) <= limit {
		return []string{text}
	}
	var chunks []string
	remaining := text
	for len(remaining) > 0 {
		if len(remaining) <= limit {
			chunks = append(chunks, remaining)
			break
		}
		cut := strings.LastIndex(remaining[:limit], "\n")
		if cut <= 100 {
			cut = limit
		}
		chunks = append(chunks, remaining[:cut])
		remaining = strings.TrimPrefix(remaining[cut:], "\n")
	}
	return chunks
}

func worker() {
	client := &http.Client{Timeout: 12 * time.Second}
	for item := range msgChan {
		token, defaultChatID := config.AppConfig.GetTelegramCreds()
		if token == "" {
			continue
		}

		targetChat := item.ChatID
		if targetChat == "" {
			targetChat = defaultChatID
		}
		if targetChat == "" {
			continue
		}

		parts := splitMessage(item.Text, 3800)
		for _, part := range parts {
			url := fmt.Sprintf("https://api.telegram.org/bot%s/sendMessage", token)
			body, _ := json.Marshal(map[string]interface{}{
				"chat_id":                  targetChat,
				"text":                     part,
				"parse_mode":               "HTML",
				"disable_web_page_preview": true,
			})

			for attempt := 0; attempt < 3; attempt++ {
				resp, err := client.Post(url, "application/json", bytes.NewBuffer(body))
				if err == nil {
					sc := resp.StatusCode
					resp.Body.Close()
					if sc == 200 {
						log.Printf("📨 Telegram message sent to %s (HTTP 200)", targetChat)
						break
					}
					log.Printf("⚠️ Telegram API returned HTTP %d for chat %s", sc, targetChat)
				} else {
					log.Printf("⚠️ Telegram POST error (attempt %d): %v", attempt+1, err)
				}
				time.Sleep(1 * time.Second)
			}
			// Throttle between messages to avoid 429
			time.Sleep(250 * time.Millisecond)
		}
	}
}

// Send queues a message for the default configured chat
func Send(text string) {
	SendToChat("", text)
}

// SendToChat queues a message for a specific chat ID
func SendToChat(chatID, text string) {
	select {
	case msgChan <- OutgoingMessage{ChatID: chatID, Text: text}:
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
