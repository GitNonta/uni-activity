#!/usr/bin/env python3
"""
auto_update_tunnel_url.py
=========================
Monitors Cloudflare Tunnel log files for URL changes and automatically:
  1. Updates docs/active_url.json on GitHub Pages via GitHub API
  2. Updates APP_URL and LINE_CALLBACK_URL in .env
  3. Clears Laravel config cache
  4. Updates LINE Webhook endpoint

Usage (on Termux server):
  python3 -u auto_update_tunnel_url.py &

Requirements:
  - GITHUB_PAT=<your_personal_access_token> in .env
    (Token needs 'Contents: Read & Write' permission on the repo)
"""
import os
import re
import time
import json
import base64
import subprocess
import urllib.request
import urllib.error

# ─── Configuration ────────────────────────────────────────────────────────────
HOME            = "/data/data/com.termux/files/home"
PROJECT_DIR     = f"{HOME}/uni-activity"
ENV_FILE        = f"{PROJECT_DIR}/.env"
LOCAL_JSON      = f"{PROJECT_DIR}/docs/active_url.json"

LOG_FILES       = [
    f"{HOME}/cloudflared.log",
    f"{PROJECT_DIR}/cloudflared.log",
    f"{HOME}/test_cf.log",
]

GITHUB_OWNER    = "GitNonta"
GITHUB_REPO     = "uni-activity"
GITHUB_PATH     = "docs/active_url.json"
GITHUB_API      = f"https://api.github.com/repos/{GITHUB_OWNER}/{GITHUB_REPO}/contents/{GITHUB_PATH}"

CHECK_INTERVAL  = 15   # seconds between URL checks
# ──────────────────────────────────────────────────────────────────────────────


def read_env(key: str) -> str | None:
    """Read a single value from .env file."""
    try:
        with open(ENV_FILE, "r") as f:
            for line in f:
                if line.startswith(f"{key}="):
                    val = line.split("=", 1)[1].strip()
                    if (val.startswith('"') and val.endswith('"')) or \
                       (val.startswith("'") and val.endswith("'")):
                        val = val[1:-1]
                    return val
    except OSError:
        pass
    return None


def update_env(updates: dict[str, str]) -> None:
    """Replace matching keys in .env with new values."""
    try:
        with open(ENV_FILE, "r") as f:
            lines = f.readlines()
        with open(ENV_FILE, "w") as f:
            for line in lines:
                replaced = False
                for key, val in updates.items():
                    if line.startswith(f"{key}="):
                        f.write(f"{key}={val}\n")
                        replaced = True
                        break
                if not replaced:
                    f.write(line)
        print(f"  [ENV] Updated .env: {list(updates.keys())}", flush=True)
    except OSError as e:
        print(f"  [ENV] Failed to update .env: {e}", flush=True)


def is_url_alive(url: str) -> bool:
    """Check if the given Cloudflare Tunnel URL is currently responding and healthy."""
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Termux-Tunnel-Checker"})
        with urllib.request.urlopen(req, timeout=4) as r:
            return True
    except urllib.error.HTTPError as e:
        # Tunnel status codes like 530 / 1033 indicate dead tunnel, but HTTP 200..404 mean tunnel is alive!
        if e.code in (530, 520, 521, 522, 523, 524, 1033):
            return False
        return True
    except Exception:
        return False


def get_tunnel_url_from_log() -> str | None:
    """Parse trycloudflare URLs from all log files, returning the newest LIVE URL."""
    all_matches = []
    
    for log_path in LOG_FILES:
        if os.path.exists(log_path):
            try:
                with open(log_path, "r") as f:
                    content = f.read()
                matches = re.findall(r'https://[a-zA-Z0-9-]+\.trycloudflare\.com', content)
                all_matches.extend(matches)
            except OSError:
                pass

    if not all_matches:
        return None

    # Filter unique candidate URLs in reverse order (newest first)
    seen = set()
    candidates = []
    for url in reversed(all_matches):
        if url not in seen:
            seen.add(url)
            candidates.append(url)

    # Return first candidate that is actually alive
    for candidate in candidates:
        if is_url_alive(candidate):
            return candidate
    return None


def update_local_json(url: str) -> None:
    """Write active_url.json locally."""
    try:
        os.makedirs(os.path.dirname(LOCAL_JSON), exist_ok=True)
        with open(LOCAL_JSON, "w") as f:
            json.dump({"url": url}, f, indent=2)
        print(f"  [LOCAL] Updated local active_url.json → {url}", flush=True)
    except OSError as e:
        print(f"  [LOCAL] Failed to write local JSON: {e}", flush=True)


def update_github_json(url: str) -> bool:
    """Update docs/active_url.json on GitHub via API. Returns True on success."""
    pat = read_env("GITHUB_PAT")
    if not pat:
        print("  [GITHUB] GITHUB_PAT not found in .env — skipping GitHub update.", flush=True)
        return False

    headers = {
        "Authorization": f"token {pat}",
        "Accept": "application/vnd.github.v3+json",
        "User-Agent": "Termux-Tunnel-Updater",
        "Content-Type": "application/json",
    }

    # Get current file SHA
    sha = None
    try:
        req = urllib.request.Request(GITHUB_API, headers=headers)
        with urllib.request.urlopen(req, timeout=10) as r:
            sha = json.loads(r.read())["sha"]
    except urllib.error.HTTPError as e:
        if e.code != 404:
            print(f"  [GITHUB] Could not fetch SHA: HTTP {e.code}", flush=True)
    except Exception as e:
        print(f"  [GITHUB] Could not fetch SHA: {e}", flush=True)

    # Push updated content
    content_b64 = base64.b64encode(
        json.dumps({"url": url}, indent=2).encode()
    ).decode()

    payload: dict = {
        "message": f"chore: update active tunnel URL to {url}",
        "content": content_b64,
    }
    if sha:
        payload["sha"] = sha

    try:
        data = json.dumps(payload).encode()
        req = urllib.request.Request(GITHUB_API, data=data, method="PUT", headers=headers)
        with urllib.request.urlopen(req, timeout=10) as r:
            if r.status in (200, 201):
                print(f"  [GITHUB] Updated active_url.json on GitHub Pages → {url}", flush=True)
                return True
    except urllib.error.HTTPError as e:
        print(f"  [GITHUB] Push failed: HTTP {e.code} — {e.read().decode()[:200]}", flush=True)
    except Exception as e:
        print(f"  [GITHUB] Push failed: {e}", flush=True)

    return False


def update_line_webhook(url: str) -> None:
    """Update LINE Official Account Webhook URL."""
    token = read_env("LINE_CHANNEL_ACCESS_TOKEN")
    if not token:
        print("  [LINE] LINE_CHANNEL_ACCESS_TOKEN not found — skipping.", flush=True)
        return

    webhook = f"{url}/line/callback"
    try:
        data = json.dumps({"endpoint": webhook}).encode()
        req = urllib.request.Request(
            "https://api.line.me/v2/bot/channel/webhook/endpoint",
            data=data, method="PUT",
            headers={
                "Authorization": f"Bearer {token}",
                "Content-Type": "application/json",
                "User-Agent": "Termux-Tunnel-Updater",
            },
        )
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        with opener.open(req, timeout=10) as r:
            if r.status == 200:
                print(f"  [LINE] Webhook updated → {webhook}", flush=True)
    except Exception as e:
        print(f"  [LINE] Webhook update failed: {e}", flush=True)


def clear_laravel_cache() -> None:
    """Clear and rebuild Laravel config/route/view cache."""
    artisan = f"{PROJECT_DIR}/artisan"
    for cmd in ["config:cache", "route:cache", "view:cache"]:
        try:
            result = subprocess.run(
                ["php", artisan, cmd],
                capture_output=True, text=True, timeout=30
            )
            status = "OK" if result.returncode == 0 else "FAIL"
            print(f"  [ARTISAN] {cmd} → {status}", flush=True)
        except Exception as e:
            print(f"  [ARTISAN] {cmd} failed: {e}", flush=True)


def apply_new_url(url: str) -> None:
    """Full update pipeline for a new tunnel URL."""
    print(f"\n[TUNNEL] New URL detected: {url}", flush=True)
    update_local_json(url)
    update_env({
        "APP_URL": url,
        "LINE_CALLBACK_URL": f"{url}/line/callback",
    })
    update_github_json(url)
    update_line_webhook(url)
    clear_laravel_cache()
    print("[TUNNEL] All updates applied.\n", flush=True)


def main() -> None:
    print("=" * 60, flush=True)
    print("  Cloudflare Tunnel Auto-Updater  (auto_update_tunnel_url.py)", flush=True)
    print(f"  Watching logs: {LOG_FILES}", flush=True)
    print(f"  Interval: {CHECK_INTERVAL}s", flush=True)
    print("=" * 60, flush=True)

    last_url: str | None = None

    while True:
        current_url = get_tunnel_url_from_log()

        if current_url and current_url != last_url:
            apply_new_url(current_url)
            last_url = current_url
        elif not current_url and last_url is None:
            print(f"  [WATCH] Waiting for tunnel URL in logs...", flush=True)

        time.sleep(CHECK_INTERVAL)


if __name__ == "__main__":
    main()
