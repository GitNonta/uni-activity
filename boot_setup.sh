#!/data/data/com.termux/files/usr/bin/bash
# Auto-setup on boot - runs via Termux:Boot
# Config via env vars (ตั้งค่าไว้ใน ~/.termux/boot/env หรือ export ก่อนรัน):
#   SSH_PASSWORD   — รหัสผ่าน SSH (ไม่ตั้งค่า = ข้ามการเปลี่ยนรหัส)
#   REPO_URL       — Git repo ที่จะ clone (default: nickzillas/uni-activity)
#   SSH_PORT       — พอร์ต sshd (default: 8022)

LOG="/sdcard/termux_setup.log"
exec > >(tee -a "$LOG") 2>&1

echo "=== Termux:Boot setup started at $(date) ==="

# Update packages
pkg update -y

# Install essential packages
pkg install -y openssh python git php composer nodejs-lts

# Setup SSH
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -A

if [ -n "${SSH_PASSWORD:-}" ]; then
    echo "u0_a175:$SSH_PASSWORD" | chpasswd
    echo "SSH password set"
else
    echo "⚠️  SSH_PASSWORD not set — skipping password change (keep existing)"
fi

sshd

# Clone project
REPO_URL="${REPO_URL:-https://github.com/nickzillas/uni-activity.git}"
cd ~
if [ ! -d "uni-activity" ]; then
    git clone "$REPO_URL"
else
    cd uni-activity && git pull origin main
fi

# Install PHP deps
cd ~/uni-activity
composer install --no-dev --optimize-autoloader 2>/dev/null

# Setup Python AI service
cd ~/uni-activity/ai_service 2>/dev/null
pip3 install -r requirements.txt 2>/dev/null || pip install -r requirements.txt 2>/dev/null

# Start AI service
nohup python server.py > /sdcard/ai_server.log 2>&1 &

echo "=== SETUP COMPLETE ==="
echo "SETUP_FINISHED"
