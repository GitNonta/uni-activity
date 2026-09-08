package config

import (
	"bufio"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"
)

type Config struct {
	mu                sync.RWMutex
	ProjectRoot       string
	StaticDir         string
	EnvPath           string
	TelegramBotToken  string
	TelegramChatID    string
	TunnelTargetURL   string
	StartTime         time.Time
	ActiveAlertIDs    map[string]struct{}
	AlertPending      map[string]int
	AlertResolveCount map[string]int
	AlertsHistory     []map[string]interface{}
}

var AppConfig = &Config{
	StartTime:         time.Now(),
	ActiveAlertIDs:    make(map[string]struct{}),
	AlertPending:      make(map[string]int),
	AlertResolveCount: make(map[string]int),
	AlertsHistory:     make([]map[string]interface{}, 0),
	TunnelTargetURL:   "http://127.0.0.1:8088",
}

func InitConfig(root string) {
	AppConfig.mu.Lock()
	defer AppConfig.mu.Unlock()

	AppConfig.ProjectRoot = root
	AppConfig.StaticDir = filepath.Join(root, "monitor-ui", "dist")
	AppConfig.EnvPath = filepath.Join(root, ".env")

	// Read .env file
	if f, err := os.Open(AppConfig.EnvPath); err == nil {
		defer f.Close()
		scanner := bufio.NewScanner(f)
		for scanner.Scan() {
			line := strings.TrimSpace(scanner.Text())
			if strings.HasPrefix(line, "#") || !strings.Contains(line, "=") {
				continue
			}
			parts := strings.SplitN(line, "=", 2)
			key := strings.TrimSpace(parts[0])
			val := strings.Trim(strings.TrimSpace(parts[1]), "\"'")

			switch key {
			case "TELEGRAM_BOT_TOKEN":
				if AppConfig.TelegramBotToken == "" {
					AppConfig.TelegramBotToken = val
				}
			case "TELEGRAM_CHAT_ID":
				if AppConfig.TelegramChatID == "" {
					AppConfig.TelegramChatID = val
				}
			case "TUNNEL_TARGET_URL":
				AppConfig.TunnelTargetURL = val
			}
		}
	}

	// Environment variable overrides
	if token := os.Getenv("TELEGRAM_BOT_TOKEN"); token != "" {
		AppConfig.TelegramBotToken = token
	}
	if chatID := os.Getenv("TELEGRAM_CHAT_ID"); chatID != "" {
		AppConfig.TelegramChatID = chatID
	}
}

func (c *Config) GetTelegramCreds() (string, string) {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.TelegramBotToken, c.TelegramChatID
}

func (c *Config) AddAlertHistory(item map[string]interface{}) {
	c.mu.Lock()
	defer c.mu.Unlock()
	if len(c.AlertsHistory) >= 100 {
		c.AlertsHistory = c.AlertsHistory[1:]
	}
	c.AlertsHistory = append(c.AlertsHistory, item)
}

func (c *Config) GetAlertHistory() []map[string]interface{} {
	c.mu.RLock()
	defer c.mu.RUnlock()
	res := make([]map[string]interface{}, len(c.AlertsHistory))
	copy(res, c.AlertsHistory)
	return res
}
