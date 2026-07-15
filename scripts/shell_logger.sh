#!/usr/bin/env bash

# Uni-Activity Shell Command Logger
# Sourced in .bashrc / .zshrc to log terminal executions to the monitoring system.

export LAST_LOGGED_CMD=""

log_to_monitor() {
  local exit_code=$?
  local last_cmd=""
  
  # Retrieve last command from history
  last_cmd=$(fc -ln -1 2>/dev/null | sed 's/^[ \t]*//')
  
  # Strip trailing/leading whitespace
  last_cmd=$(echo "$last_cmd" | xargs 2>/dev/null)
  
  # Prevent logging empty commands, recursion, or duplicates
  if [ -n "$last_cmd" ] && [ "$last_cmd" != "$LAST_LOGGED_CMD" ]; then
    export LAST_LOGGED_CMD="$last_cmd"
    
    # Escape quotes for JSON
    local escaped_cmd=$(echo "$last_cmd" | sed 's/"/\\"/g' | tr -d '\n\r')
    local escaped_pwd=$(pwd | sed 's/"/\\"/g')
    local time_str=$(date -Iseconds 2>/dev/null || date)
    local user_str="${USER:-termux}"
    
    # Construct JSON payload
    local payload="{\"method\":\"SHELL\",\"path\":\"$escaped_cmd\",\"ip\":\"$user_str\",\"duration\":0,\"status\":$exit_code,\"time\":\"$time_str\",\"request\":{\"headers\":{\"Working-Dir\":\"$escaped_pwd\",\"Shell\":\"$SHELL\"},\"body\":\"\"},\"response\":{\"headers\":{},\"body\":\"Exit Code: $exit_code\"}}"
    
    # Send UDP message asynchronously using Python to bypass shell socket limitations
    export MONITOR_PAYLOAD="$payload"
    python3 -c "import socket, os; s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM); s.sendto(os.environ.get('MONITOR_PAYLOAD', '').encode('utf-8'), ('127.0.0.1', 9998))" 2>/dev/null &
  fi
}

# Register hook based on shell type
if [ -n "$ZSH_VERSION" ]; then
  autoload -Uz add-zsh-hook 2>/dev/null
  if [ $? -eq 0 ]; then
    add-zsh-hook precmd log_to_monitor 2>/dev/null
  fi
elif [ -n "$BASH_VERSION" ]; then
  if [[ ! "$PROMPT_COMMAND" =~ log_to_monitor ]]; then
    PROMPT_COMMAND="log_to_monitor; $PROMPT_COMMAND"
  fi
fi

# Auto-start monitor server on port 9999 if not running
pgrep -f "monitor_server.py" >/dev/null || nohup python /data/data/com.termux/files/home/uni-activity/py/monitor_server.py </dev/null >/data/data/com.termux/files/home/uni-activity/monitor.log 2>&1 &

