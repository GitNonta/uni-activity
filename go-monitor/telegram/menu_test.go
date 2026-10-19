package telegram

import (
	"encoding/json"
	"os"
	"testing"
)

func TestMainMenuMarkupValid(t *testing.T) {
	m := mainMenuMarkup()
	var v struct {
		InlineKeyboard [][]map[string]interface{} `json:"inline_keyboard"`
	}
	if err := json.Unmarshal([]byte(m), &v); err != nil {
		t.Fatalf("INVALID markup JSON: %v\n---raw---\n%s", err, m)
	}
	t.Logf("markup OK: %d rows", len(v.InlineKeyboard))
}

func TestMainMenuMarkupDump(t *testing.T) {
	m := mainMenuMarkup()
	if err := os.WriteFile("menu_markup.json", []byte(m), 0o644); err != nil {
		t.Fatal(err)
	}
}
