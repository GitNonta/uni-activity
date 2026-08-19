#!/data/data/com.termux/files/usr/bin/bash
set -eu

PROJECT_DIR="${PROJECT_DIR:-$HOME/uni-activity}"
TERMUX_PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
SSH_CONFIG="$TERMUX_PREFIX/etc/ssh/sshd_config"
REDIS_PASSWORD="${REDIS_PASSWORD:-}"

if [ -z "$REDIS_PASSWORD" ] && [ -f "$PROJECT_DIR/.env" ]; then
    REDIS_PASSWORD=$(grep '^REDIS_PASSWORD=' "$PROJECT_DIR/.env" | head -1 | cut -d= -f2-)
    REDIS_PASSWORD="${REDIS_PASSWORD#\"}"
    REDIS_PASSWORD="${REDIS_PASSWORD%\"}"
fi

log() { printf '[security-hardening] %s\n' "$1"; }
warn() { printf '[security-hardening][WARNING] %s\n' "$1" >&2; }

if [ "$(id -u)" -eq 0 ]; then
    IS_ROOT=1
else
    IS_ROOT=0
fi

backup_file() {
    file="$1"
    if [ -f "$file" ]; then
        cp "$file" "$file.bak.$(date +%Y%m%d%H%M%S)"
    fi
}

set_ssh_option() {
    option="$1"
    value="$2"
    if grep -qE "^#?${option}[[:space:]]" "$SSH_CONFIG"; then
        sed -i -E "s/^#?${option}[[:space:]].*/${option} ${value}/" "$SSH_CONFIG"
    else
        printf '%s %s\n' "$option" "$value" >> "$SSH_CONFIG"
    fi
}

configure_ssh() {
    [ -f "$SSH_CONFIG" ] || { warn "SSH config not found: $SSH_CONFIG"; return; }
    backup_file "$SSH_CONFIG"
    set_ssh_option MaxAuthTries 2
    set_ssh_option LoginGraceTime 30
    set_ssh_option MaxStartups 3:30:10
    set_ssh_option PerSourcePenalties yes
    set_ssh_option PerSourceMaxStartups 4
    sshd -t
    if command -v pgrep >/dev/null 2>&1; then
        sshd_pid=$(pgrep -xo sshd || true)
        [ -n "$sshd_pid" ] && kill -HUP "$sshd_pid" || true
    fi
    log "SSH hardening applied"
}

configure_redis() {
    [ -n "$REDIS_PASSWORD" ] || { warn "REDIS_PASSWORD is missing; Redis was not changed"; return; }
    command -v redis-cli >/dev/null 2>&1 || { warn "redis-cli not found"; return; }
    for port in 6379 6380; do
        redis-cli -p "$port" CONFIG SET requirepass "$REDIS_PASSWORD" >/dev/null 2>&1 || true
        redis-cli -a "$REDIS_PASSWORD" -p "$port" --no-auth-warning CONFIG SET bind 127.0.0.1 >/dev/null 2>&1 || true
    done
    log "Redis runtime protection applied to ports 6379 and 6380"

    for file in "$HOME/.termux/boot/start_services.sh" "$HOME/restart_services.sh" "$HOME/start_uni_activity.sh" "$HOME/watchdog.sh" "$HOME/fix_realtime_notifications.sh" "$HOME/server_services_setup.sh"; do
        if [ -f "$file" ]; then
            backup_file "$file"
            sed -i "s/--bind 0\\.0\\.0\\.0/--bind 127.0.0.1 --requirepass ${REDIS_PASSWORD}/g" "$file"
        fi
    done
    log "Redis startup scripts updated"
}

configure_postgres() {
    if [ "$IS_ROOT" -ne 1 ]; then
        warn "PostgreSQL and firewall changes require root; skipped"
        return
    fi
    postgres_conf=$(find "$TERMUX_PREFIX" "$PREFIX" -type f -name postgresql.conf 2>/dev/null | head -1 || true)
    pg_hba=$(find "$TERMUX_PREFIX" "$PREFIX" -type f -name pg_hba.conf 2>/dev/null | head -1 || true)
    if [ -n "$postgres_conf" ]; then
        backup_file "$postgres_conf"
        if grep -qE '^#?listen_addresses[[:space:]]*=' "$postgres_conf"; then
            sed -i -E "s/^#?listen_addresses[[:space:]]*=.*/listen_addresses = '127.0.0.1'/" "$postgres_conf"
        else
            printf "listen_addresses = '127.0.0.1'\n" >> "$postgres_conf"
        fi
    else
        warn "PostgreSQL postgresql.conf not found"
    fi
    if [ -n "$pg_hba" ]; then
        backup_file "$pg_hba"
        sed -i -E 's#^[[:space:]]*host[[:space:]]+all[[:space:]]+all[[:space:]]+0\.0\.0\.0/0[[:space:]]+scram-sha-256#host all all 127.0.0.1/32 scram-sha-256#' "$pg_hba"
    else
        warn "PostgreSQL pg_hba.conf not found"
    fi
    log "PostgreSQL localhost binding configured"
}

configure_firewall() {
    if [ "$IS_ROOT" -ne 1 ]; then
        warn "iptables requires root; firewall was not changed"
        return
    fi
    iptables -F INPUT
    iptables -A INPUT -i lo -j ACCEPT
    iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
    iptables -A INPUT -p tcp --dport 8022 -j ACCEPT
    iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
    iptables -A INPUT -j DROP
    if command -v iptables-save >/dev/null 2>&1; then
        iptables-save > "$HOME/iptables.rules"
    fi
    log "Firewall configured: only SSH 8022 and HTTP 8080 are allowed"
}

configure_brute_guard() {
    guard="$HOME/ssh-brute-guard.sh"
    [ -f "$guard" ] || { warn "SSH brute guard not found"; return; }
    backup_file "$guard"
    if ! grep -q 'iptables -A INPUT -s' "$guard"; then
        sed -i '/echo "\$IP" >> "\$BLOCKED"/a\                iptables -A INPUT -s "$IP" -j DROP 2>/dev/null || echo "[$(date)] firewall unavailable for $IP" >> "$LOG"' "$guard"
    fi
    log "SSH brute guard updated; iptables blocking is active only with root"
}

log "Starting hardening (root=$IS_ROOT)"
configure_ssh
configure_redis
configure_postgres
configure_firewall
configure_brute_guard
log "Hardening complete"
if [ "$IS_ROOT" -ne 1 ]; then
    warn "Run this script as root to apply PostgreSQL and iptables changes"
fi
