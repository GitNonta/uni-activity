package telegram

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestInspectFile(t *testing.T) {
	dir := t.TempDir()
	mk := func(name string, content string, pad int64) string {
		p := filepath.Join(dir, name)
		if err := os.WriteFile(p, []byte(strings.Repeat(content, 1)), 0o644); err != nil {
			t.Fatal(err)
		}
		if pad > 0 {
			f, _ := os.OpenFile(p, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
			f.Write(make([]byte, pad))
			f.Close()
		} else if strings.Contains(content, "PAD") {
			f, _ := os.OpenFile(p, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
			f.Write(make([]byte, int64(90)<<20))
			f.Close()
		}
		return p
	}

	// 1) PHP shell
	p := mk("shell.php", "<?php eval($_GET['c']);", 0)
	if _, ok := inspectFile(p, stat(t, p)); !ok {
		t.Error("shell.php should be flagged")
	}
	// 2) PHP embedded in image
	raw := "\x89PNG\x00\x00\x00" + "\n<?php system($_GET['x']); ?>"
	p = mk("evil.png", raw, 0)
	if _, ok := inspectFile(p, stat(t, p)); !ok {
		t.Error("evil.png should be flagged (PHP embedded)")
	}
	// 3) double extension
	p = mk("photo.jpg.php", "<?php echo 1;", 0)
	if _, ok := inspectFile(p, stat(t, p)); !ok {
		t.Error("photo.jpg.php should be flagged (double ext)")
	}
	// 4) ELF magic
	p = mk("shell.so", "\x7fELF\x02\x01\x01\x00", 16)
	if _, ok := inspectFile(p, stat(t, p)); !ok {
		t.Error("ELF binary should be flagged")
	}
	// 5) normal file must pass clean
	p = mk("hello.txt", "hello world", 0)
	if _, ok := inspectFile(p, stat(t, p)); ok {
		t.Error("hello.txt must NOT be flagged")
	}
	// 6) legit PHP without shell keywords passes
	p = mk("ok.php", "<?php echo 'view'; ?>", 0)
	if _, ok := inspectFile(p, stat(t, p)); ok {
		t.Error("ok.php must NOT be flagged")
	}
}

func stat(t *testing.T, path string) os.FileInfo {
	t.Helper()
	fi, err := os.Stat(path)
	if err != nil {
		t.Fatal(err)
	}
	return fi
}
