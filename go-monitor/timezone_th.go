package main

import (
	// embed tzdata in binary — Android ไม่มี zoneinfo files
	_ "time/tzdata"
	"time"
)

// thTZ = เวลาประเทศไทย (Asia/Bangkok, UTC+7)
func thTZ() *time.Location {
	if th, err := time.LoadLocation("Asia/Bangkok"); err == nil {
		return th
	}
	return time.FixedZone("+07", 7*3600)
}

// pinThaiTimeLocal บังคับ time.Local ให้เป็นเวลาไทย
// เรียกตั้งแต่บรรทัดแรกของ main() — รับประกันว่า init อื่น (เช่น telegram worker)
// ที่อาจ format เวลาก่อน init() ของไฟล์นี้จะได้ local ที่ถูกต้องเสมอ
func pinThaiTimeLocal() {
	time.Local = thTZ()
}
