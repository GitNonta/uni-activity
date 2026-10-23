#!/data/data/com.termux/files/usr/bin/sh
# termux-boot.sh — ให้ face_api ขึ้นเองหลังเครื่อง S2 บูต
# ติดตั้ง: cp termux-boot.sh ~/.termux/boot/99-face-api.sh && chmod +x ~/.termux/boot/99-face-api.sh
# (ต้องติดตั้งแอป Termux:Boot จาก F-Droid)
termux-wake-lock
bash "$HOME/start_api.sh" 1 8 5
