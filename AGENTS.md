# System Environment & Autonomous Execution Rules

## 1. Environment Context (Crucial)
- **Current OS:** Debian 13 (Trixie) ARM64 running inside a Termux `proot-distro` container as `root@localhost`.
- **Host OS:** Android / Termux (`aarch64`).
- **No `systemd` or Kernel Access:** PID 1 is NOT systemd. Never run `systemctl`, `service`, or commands requiring kernel netlink/TUN (`/dev/net/tun`). Start background services directly as daemons or via `nohup ... &`.
- **Root Privileges:** You are already `root` inside Debian. Do NOT prefix Debian commands with `sudo`.

## 2. Host Termux Bridge (`/usr/local/bin/termux`)
- Whenever a task requires native Android/Termux execution, hardware access, or services that fail inside `proot`, execute them on the Termux host by prefixing the command with `termux`.
- **Examples:**
  - Package management on host: `termux pkg install <package> -y`
  - Service checks on host: `termux pgrep -a <process>`
  - Shared directory path: `/data/data/com.termux/files/home`
- **File System Rule:** Never run `npm install`, `pnpm install`, or `composer install` inside `/sdcard` or shared Android storage due to symlink permission restrictions (`EPERM`). Always work inside native Linux/Termux home directories.

## 3. Network & Proxy Configuration
- Local Cloudflare WARP userspace proxy (`wireproxy`) is available at:
  - SOCKS5: `socks5h://127.0.0.1:40000`
  - HTTP/HTTPS: `http://127.0.0.1:40001`
- If network requests fail or hit rate limits, verify `pgrep -a wireproxy` and route traffic through the proxy if needed.

## 4. Autonomous Workflow & Verification Rules
- **Act Directly:** Do not stop to ask for confirmation on routine file edits, package installations, or test runs. Execute the full task end-to-end.
- **Self-Correction:** If a command returns an error, inspect the logs or stderr immediately, diagnose the root cause based on the `proot`/Termux constraints above, and apply the fix automatically.
- **Verification:** Always verify your changes (e.g., run linters, syntax checks, `curl` tests, or process checks) before reporting that a task is complete.
- **Concise Reporting:** Keep explanations brief and in Thai. Focus on actions taken and verification results.

## 5. Shell Execution Fix
- Shell executable path: `/bin/bash` (real file, also `/usr/bin/bash`). Never assume `systemd`/standard service paths are present without the proot lookup.
- If the tool layer reports `Executable not found in $PATH: "bash"`, the error is in the agent host's exec environment PATH, not missing bash — try the `termux` SSH bridge (`/usr/local/bin/termux`) instead.

## 6. Environment & Host Termux Execution (SSH Bridge)
- You are running inside a Debian proot container (`root@localhost`).
- To execute any command natively on the host Termux environment (outside proot), prefix the command with `termux` (e.g., `termux pkg install ...`, `termux valkey-cli ping`, `termux ps aux`).
- The bridge is a wrapper at `/usr/local/bin/termux` that runs `ssh -i /root/.ssh/tunnel_test_key -p 8022 127.0.0.1 "..."` — passwordless, key-based.
- Shared storage between Debian and Termux is located at `/data/data/com.termux/files/home`.
