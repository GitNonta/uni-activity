package telegram

import "testing"

func TestHelpHandlerSmoke(t *testing.T) {
	defer func() {
		if r := recover(); r != nil {
			t.Fatalf("handleCommand('/help) panicked: %v", r)
		}
	}()
	handleCommand("0", "/help") // chat "0" → send จะ fail เงียบ ๆ ไม่ยิงเข้าแชตจริง
}
