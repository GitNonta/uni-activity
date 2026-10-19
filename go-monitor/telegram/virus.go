package telegram

import (
	"bytes"
	"fmt"
	"io"
	"log"
	"os"
	"path/filepath"
	"regexp"
	"runtime/debug"
	"strings"
	"time"

	"uni-activity/go-monitor/config"
)

// ---- เอนจินเบา: จับไฟล์ที่น่าสงสัยแบบที่พบบ่อยที่สุดในเว็บเซิร์ฟเวอร์ ----

var phpShellRe = regexp.MustCompile(`(?i)\b(eval|assert|base64_decode|system|shell_exec|passthru|proc_open|gzinflate|str_rot13|preg_replace)\s*\(`)

var doubleExtRe = regexp.MustCompile(`(?i)(\.(php\d?|phtml|asp|aspx|jsp|jspa|sh|bash|exe|bat|cmd|vbs|ps1)(\.[a-z0-9]{1,6}){1,3}$)|(\.[a-z0-9]{1,6}\.(php\d?|phtml|asp|aspx|jsp|jspa|sh|bash|exe|bat|cmd|vbs|ps1)(\.[a-z0-9]{1,6}){0,3}$)`)

var scriptExts = map[string]bool{
	"php": true, "phtml": true, "php3": true, "php4": true, "php5": true, "php7": true,
	"asp": true, "aspx": true, "jsp": true, "py": true, "pl": true, "sh": true,
	"bash": true, "cgi": true, "shtml": true,
}

const (
	scanContentLimit = int64(256) << 10 // อ่านหัวไฟล์เพื่อหา pattern สูงสุด 256KB/ไฟล์
	scanSizeWarn     = int64(80) << 20  // ไฟล์เกิน 80MB = น่าสงสัยระดับขนาด
	scanMaxFiles     = 5000             // จำกัดจำนวนไฟล์ต่อรอบสแกน
	scanMaxFindings  = 15               // จำกัดจำนวนรายการที่แสดงในข้อความ
)

type vFinding struct {
	Path   string
	Reason string
}

// runVirusScan สแกนที่เก็บไฟล์แล้วส่งรายงานเข้า Telegram (รันใน goroutine)
func runVirusScan(chatID string) {
	defer func() {
		if r := recover(); r != nil {
			log.Printf("🦠 [VirusScan] panic: %v\n%s", r, debug.Stack())
			SendToChatFast(chatID, "⚠️ Virus scan crashed internally — ดู log เพื่อตรวจสอบ")
		}
	}()

	start := time.Now()
	var findings []vFinding
	var files int64
	var readErrs int

	roots := []string{
		filepath.Join(config.AppConfig.ProjectRoot, "storage", "app"),
		filepath.Join(config.AppConfig.ProjectRoot, "public", "storage"),
	}
	// ยกเว้นโฟลเดอร์ backups: ไฟล์ backup ใหญ่เป็นธรรมชาติ ไม่นับเป็น suspicious-by-size
	backupDir := filepath.Join(config.AppConfig.ProjectRoot, "storage", "app", "backups")
	var skippedFiles, skippedBytes int64
	if _, err := os.Stat(backupDir); err == nil {
		_ = filepath.Walk(backupDir, func(path string, info os.FileInfo, err error) error {
			if err == nil && info != nil && !info.IsDir() {
				skippedFiles++
				skippedBytes += info.Size()
			}
			return nil
		})
	}

	for _, root := range roots {
		if _, err := os.Stat(root); err != nil {
			continue
		}
		_ = filepath.Walk(root, func(path string, info os.FileInfo, err error) error {
			if err != nil {
				readErrs++
				return nil
			}
			if info.IsDir() {
				if info.Name() == ".git" {
					return filepath.SkipDir
				}
				if path == backupDir {
					return filepath.SkipDir
				}
				return nil
			}
			if info.Mode()&os.ModeSymlink != 0 {
				return nil // ไม่ซ้ำ symlink
			}
			if files >= scanMaxFiles {
				return filepath.SkipAll
			}
			files++
			if f, ok := inspectFile(path, info); ok {
				findings = append(findings, f)
			}
			return nil
		})
	}

	elapsed := time.Since(start).Round(time.Millisecond)
	var b strings.Builder
	b.WriteString("🦠 <b>ผลสแกนไวรัส</b>\n━━━━━━━━━━━━━━━━━━\n")
	b.WriteString(fmt.Sprintf("ไฟล์ที่สแกน: %d | ใช้เวลา: %s\n", files, elapsed))
	if readErrs > 0 {
		b.WriteString(fmt.Sprintf("⚠️ ไฟล์อ่านไม่ได้: %d\n", readErrs))
	}
	if skippedFiles > 0 {
		b.WriteString(fmt.Sprintf("🚫 ยกเว้นตามที่ตั้งค่า: backups — %d ไฟล์ / %d MB\n", skippedFiles, skippedBytes>>20))
	}
	if files == 0 {
		b.WriteString("⚠️ <b>ไม่พบไฟล์ที่สแกนได้</b> — โฟลเดอร์เป้าหมายหายหรือ path ผิด (ProjectRoot=" + config.AppConfig.ProjectRoot + ")")
	} else if len(findings) == 0 {
		b.WriteString("🟢 <b>ไม่พบไฟล์น่าสงสัย</b> — ทุกเป้าหมายผ่าน")
	} else {
		b.WriteString(fmt.Sprintf("🔴 <b>พบไฟล์น่าสงสัย %d รายการ</b>\n", len(findings)))
		for i, f := range findings {
			if i >= scanMaxFindings {
				b.WriteString(fmt.Sprintf("… และอีก %d รายการ (ดู log)\n", len(findings)-scanMaxFindings))
				break
			}
			b.WriteString(fmt.Sprintf("• <code>%s</code>\n  สาเหตุ: %s\n", f.Path, f.Reason))
		}
		b.WriteString("แนะนำ: ลบ/ตรวจสอบไฟล์เหล่านั้นจากการโหลดอีกครั้ง")
	}
	log.Printf("🦠 [VirusScan] done files=%d findings=%d elapsed=%s", files, len(findings), elapsed)
	SendToChatFast(chatID, b.String())
}

// inspectFile ตรวจไฟล์เดียว — คืน (finding, true) เมื่อน่าสงสัย
func inspectFile(path string, info os.FileInfo) (vFinding, bool) {
	lower := strings.ToLower(filepath.Base(path))
	sz := info.Size()

	// 1) double extension เช่น evil.php.jpg
	if doubleExtRe.MatchString(lower) {
		return vFinding{path, "double extension ผิดปกติ (นัยลนาผลสแกนเว็บไฟล์)"}, true
	}

	// 2) ELF / PE binary ในที่เก็บไฟล์
	magic, ok := readHead(path, 8)
	if ok {
		if bytes.HasPrefix(magic, []byte{0x7f, 'E', 'L', 'F'}) {
			return vFinding{path, "ELF executable ในที่เก็บไฟล์ผู้ใช้"}, true
		}
		if bytes.HasPrefix(magic, []byte{'M', 'Z'}) {
			return vFinding{path, "Windows PE binary ในที่เก็บไฟล์ผู้ใช้"}, true
		}
	}

	// 3) ไฟล์ขนาดใหญ่ผิดปกติ
	if sz > scanSizeWarn {
		return vFinding{path, fmt.Sprintf("ไฟล์ใหญ่ผิดปกติ (%d MB)", sz>>20)}, true
	}

	ext := strings.TrimPrefix(filepath.Ext(lower), ".")
	if ext == "zip" || ext == "gz" || ext == "tgz" || ext == "sql" || ext == "bz2" || ext == "xz" {
		return vFinding{}, false // ข้าม archive/dump
	}

	data, ok := readHead(path, sz)
	if !ok {
		return vFinding{}, false
	}

	// 4) pattern ของ webshell ในไฟล์ script
	if scriptExts[ext] && phpShellRe.Match(data) {
		return vFinding{path, "PHP/script shell pattern (eval/system/base64_decode ฯลฯ)"}, true
	}

	// 5) โค้ด PHP ฝังในไฟล์ที่ไม่ควรมี (เช่น jpg/png/pdf)
	if !scriptExts[ext] && bytes.Contains(data, []byte("<?php")) {
		return vFinding{path, "มีโค้ด PHP ฝังในไฟล์ที่ไม่ควรมี"}, true
	}

	return vFinding{}, false
}

// readHead อ่านหัวไฟล์ให้จำกัดถึงขนาด (กันเปลือง RAM บนเครื่องสมองน้อยยอดเยี่อก)
func readHead(path string, max int64) ([]byte, bool) {
	f, err := os.Open(path)
	if err != nil {
		return nil, false
	}
	defer f.Close()
	if max > scanContentLimit {
		max = scanContentLimit
	}
	data, err := io.ReadAll(io.LimitReader(f, max))
	if err != nil {
		return nil, false
	}
	return data, true
}
