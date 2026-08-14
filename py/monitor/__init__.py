"""
monitor/__init__.py — Public API of the monitor package.
Import order matters: config first, then leaves, then dependents.
"""
from monitor import config
from monitor.telegram import tg_send, tg_alert, tg_resolved, tg_daily_report
from monitor.collectors import *
from monitor.alerts import get_alerts, collect_stats
from monitor.speedtest import (
    run_speedtest_thread, run_ext_speedtest_thread, start_ext_speedtest
)
from monitor.tunnel import ping_url_thread
from monitor.tg_commands import (
    tg_handle_commands, tg_command_poll_thread, _dispatch_command
)
from monitor.threads import (
    udp_receiver_thread, udp_ai_receiver_thread,
    auto_sync_thread, manage_ai_service_thread,
    stats_collector_thread, ws_client_thread, ws_handshake, ws_encode,
)
from monitor.http_handler import MonitorHandler
