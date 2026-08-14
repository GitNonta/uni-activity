"""
patch_monitor.py — แก้ syntax errors ใน py/monitor/ modules หลัง split
"""

fixes = {
    # ── speedtest.py: double with + bad indent ────────────────────────────────
    "py/monitor/speedtest.py": [
        (
            "        with cfg._ext_lock:\n            with cfg._ext_lock:\n            cfg._ext_job.update(kw)",
            "        with cfg._ext_lock:\n            cfg._ext_job.update(kw)"
        ),
    ],
    # ── collectors.py: "global cfg.x" is invalid Python ─────────────────────
    "py/monitor/collectors.py": [
        (
            "    global cfg.line_status_cache\n",
            "    # line_status_cache lives in cfg (no global needed)\n"
        ),
    ],
    # ── alerts.py: "global cfg.x" invalid + missing cfg. prefix on constants─
    "py/monitor/alerts.py": [
        (
            "    global cfg.active_alert_ids\n",
            "    # active_alert_ids lives in cfg (no global needed)\n"
        ),
        (
            "    in_grace   = (time.time() - _monitor_start_time) < STARTUP_GRACE\n",
            "    in_grace   = (time.time() - cfg._monitor_start_time) < cfg.STARTUP_GRACE\n"
        ),
    ],
    # ── http_handler.py: stray ", _ext_lock" line ────────────────────────────
    "py/monitor/http_handler.py": [
        (
            "            , _ext_lock\n",
            ""
        ),
        (
            "                data = json.dumps(_ext_job).encode()\n",
            "                data = json.dumps(cfg._ext_job).encode()\n"
        ),
    ],
}

for filepath, patches in fixes.items():
    content = open(filepath, encoding="utf-8").read()
    for old, new in patches:
        if old in content:
            content = content.replace(old, new)
            print(f"  fixed: {filepath!r} -> {repr(old[:50])}")
        else:
            print(f"  MISS:  {filepath!r} -> {repr(old[:50])}")
    open(filepath, "w", encoding="utf-8").write(content)

print("\nPatches applied. Running syntax check...")

import ast
files = [
    "py/monitor/config.py",
    "py/monitor/telegram.py",
    "py/monitor/tg_commands.py",
    "py/monitor/speedtest.py",
    "py/monitor/tunnel.py",
    "py/monitor/collectors.py",
    "py/monitor/alerts.py",
    "py/monitor/threads.py",
    "py/monitor/http_handler.py",
    "py/monitor/__init__.py",
    "py/monitor_server_new.py",
]
ok = 0
for f in files:
    try:
        ast.parse(open(f, encoding="utf-8").read())
        print(f"  OK  {f}")
        ok += 1
    except SyntaxError as e:
        print(f"  ERR {f}: {e}")

print(f"\n{ok}/{len(files)} files OK")
