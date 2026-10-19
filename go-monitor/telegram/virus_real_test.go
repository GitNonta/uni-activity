package telegram

import (
	"bytes"
	"log"
	"os"
	"regexp"
	"testing"
	"uni-activity/go-monitor/config"
)

var scanDoneRe = regexp.MustCompile(`🦠 \[VirusScan\] done files=(\d+) findings=(\d+) elapsed=([^\s]+)`)

// TestVirusScanReal รันเอนจินบนข้อมูลจริง — เปิดเองด้วย VIRUS_SCAN_REAL=1
func TestVirusScanReal(t *testing.T) {
	if os.Getenv("VIRUS_SCAN_REAL") != "1" {
		t.Skip("set VIRUS_SCAN_REAL=1 เพื่อรันสแกนจริงบน server")
	}
	config.AppConfig.ProjectRoot = "/data/data/com.termux/files/home/uni-activity"
	prev := os.Getenv("TELEGRAM_CHAT_ID")
	os.Setenv("TELEGRAM_CHAT_ID", "0") // กันยิงเข้าแชตจริง (send จะ fail เงียบ ๆ)
	defer os.Setenv("TELEGRAM_CHAT_ID", prev)

	var buf bytes.Buffer
	stdout := log.Writer()
	log.SetOutput(&buf)
	defer log.SetOutput(stdout)

	runVirusScan("0")

	m := scanDoneRe.FindStringSubmatch(buf.String())
	if m == nil {
		t.Fatalf("ไม่เจอ log สแกนจริง (done files=...): %s", buf.String()[:min(400, len(buf.String()))])
	}
	t.Logf("scan done: files=%s findings=%s elapsed=%s", m[1], m[2], m[3])
	files := m[1]
	if files == "0" {
		t.Fatal("สแกนแล้วได้ 0 ไฟล์ — walk ไม่ทำงานจริง")
	}
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}
