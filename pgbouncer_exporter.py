#!/usr/bin/env python3
"""
PostgreSQL + PgBouncer Exporter for Prometheus
Uses subprocess psql calls (reliable on Termux)
Port: 9188
"""
import subprocess
import time
import os
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler

EXPORTER_PORT = 9188
PGPASS = 'UniActivityPostgres2026!'
ENV = dict(os.environ, PGPASSWORD=PGPASS)
PSQL = '/data/data/com.termux/files/usr/bin/psql'


def psql_query(host, port, user, db, sql):
    """Run a psql query and return output."""
    try:
        result = subprocess.run(
            [PSQL, '-h', host, '-p', str(port), '-U', user, '-d', db,
             '-t', '-A', '-F', '|', '-c', sql],
            capture_output=True, text=True, timeout=10, env=ENV
        )
        return result.stdout.strip()
    except Exception as e:
        return ''


def collect_metrics():
    lines = []

    # ── PgBouncer: SHOW POOLS ──
    pools_raw = psql_query('127.0.0.1', 6432, 'postgres', 'pgbouncer', 'SHOW POOLS')
    for row in pools_raw.split('\n'):
        parts = row.split('|')
        if len(parts) < 14:
            continue
        db = parts[0].strip()
        if db == 'pgbouncer' or not db:
            continue
        labels = f'database="{db}"'
        lines.append(f'pgbouncer_client_active{{{labels}}} {parts[2].strip()}')
        lines.append(f'pgbouncer_client_waiting{{{labels}}} {parts[3].strip()}')
        lines.append(f'pgbouncer_server_active{{{labels}}} {parts[6].strip()}')
        lines.append(f'pgbouncer_server_idle{{{labels}}} {parts[8].strip()}')
        lines.append(f'pgbouncer_server_used{{{labels}}} {parts[9].strip()}')
        lines.append(f'pgbouncer_max_wait_seconds{{{labels}}} {parts[12].strip()}')

    # ── PgBouncer: SHOW STATS ──
    stats_raw = psql_query('127.0.0.1', 6432, 'postgres', 'pgbouncer', 'SHOW STATS')
    for row in stats_raw.split('\n'):
        parts = row.split('|')
        if len(parts) < 11:
            continue
        db = parts[0].strip()
        if db == 'pgbouncer' or not db:
            continue
        labels = f'database="{db}"'
        lines.append(f'pgbouncer_total_transactions{{{labels}}} {parts[2].strip()}')
        lines.append(f'pgbouncer_total_queries{{{labels}}} {parts[3].strip()}')
        lines.append(f'pgbouncer_bytes_received{{{labels}}} {parts[4].strip()}')
        lines.append(f'pgbouncer_bytes_sent{{{labels}}} {parts[5].strip()}')
        avg_xact = parts[16].strip() if len(parts) > 16 else '0'
        avg_query = parts[17].strip() if len(parts) > 17 else '0'
        lines.append(f'pgbouncer_avg_xact_time_ms{{{labels}}} {avg_xact}')
        lines.append(f'pgbouncer_avg_query_time_ms{{{labels}}} {avg_query}')

    # ── PgBouncer: SHOW LISTS ──
    lists_raw = psql_query('127.0.0.1', 6432, 'postgres', 'pgbouncer', 'SHOW LISTS')
    list_metrics = {}
    for row in lists_raw.split('\n'):
        parts = row.split('|')
        if len(parts) >= 2:
            key = parts[0].strip().replace(' ', '_')
            val = parts[1].strip()
            try:
                list_metrics[key] = int(val)
                lines.append(f'pgbouncer_{key} {val}')
            except ValueError:
                pass

    # ── PgBouncer: SHOW SERVERS (count) ──
    servers_raw = psql_query('127.0.0.1', 6432, 'postgres', 'pgbouncer', 'SHOW SERVERS')
    server_count = servers_raw.count('\n') if servers_raw else 0
    lines.append(f'pgbouncer_active_server_connections {server_count}')

    # ── PgBouncer: SHOW CLIENTS (count) ──
    clients_raw = psql_query('127.0.0.1', 6432, 'postgres', 'pgbouncer', 'SHOW CLIENTS')
    client_count = clients_raw.count('\n') if clients_raw else 0
    lines.append(f'pgbouncer_active_client_connections {client_count}')

    # ── Connection utilization ──
    max_client = 200
    max_db = 30
    used_clients = list_metrics.get('used_clients', 0)
    free_clients = list_metrics.get('free_clients', 0)
    total_clients = used_clients + free_clients
    used_servers = list_metrics.get('used_servers', 0)
    free_servers = list_metrics.get('free_servers', 0)
    total_servers = used_servers + free_servers
    if max_client > 0:
        lines.append(f'pgbouncer_client_utilization_percent {(used_clients / max_client) * 100:.1f}')
    if max_db > 0:
        lines.append(f'pgbouncer_server_utilization_percent {(used_servers / max_db) * 100:.1f}')

    # ── PostgreSQL: connection states ──
    pg_states = psql_query('127.0.0.1', 5432, 'postgres', 'uni_activity',
        "SELECT coalesce(state, 'unknown') || '|' || count(*) FROM pg_stat_activity GROUP BY state")
    for row in pg_states.split('\n'):
        parts = row.split('|')
        if len(parts) == 2:
            state = parts[0].strip() or 'unknown'
            count = parts[1].strip()
            lines.append(f'pg_connections_by_state{{state="{state}"}} {count}')

    # ── PostgreSQL: total connections ──
    total = psql_query('127.0.0.1', 5432, 'postgres', 'uni_activity',
        "SELECT count(*) FROM pg_stat_activity")
    if total:
        lines.append(f'pg_total_connections {total}')

    # ── PostgreSQL: database size ──
    db_size = psql_query('127.0.0.1', 5432, 'postgres', 'uni_activity',
        "SELECT pg_database_size('uni_activity')")
    if db_size:
        lines.append(f'pg_database_size_bytes {db_size}')

    # ── PostgreSQL: table row counts ──
    tables = psql_query('127.0.0.1', 5432, 'postgres', 'uni_activity',
        "SELECT relname || '|' || n_live_tup FROM pg_stat_user_tables ORDER BY n_live_tup DESC LIMIT 10")
    for row in tables.split('\n'):
        parts = row.split('|')
        if len(parts) == 2:
            table = parts[0].strip()
            rows = parts[1].strip()
            lines.append(f'pg_table_rows{{table="{table}"}} {rows}')

    # ── PostgreSQL: transactions/queries per second ──
    xact = psql_query('127.0.0.1', 5432, 'postgres', 'uni_activity',
        "SELECT xact_commit + xact_rollback FROM pg_stat_database WHERE datname = 'uni_activity'")
    if xact:
        lines.append(f'pg_total_xact_count {xact}')

    queries = psql_query('127.0.0.1', 5432, 'postgres', 'uni_activity',
        "SELECT tup_returned + tup_fetched + tup_inserted + tup_updated + tup_deleted FROM pg_stat_database WHERE datname = 'uni_activity'")
    if queries:
        lines.append(f'pg_total_query_count {queries}')

    # ── Up metrics ──
    lines.append('pgbouncer_up 1')
    lines.append('pg_up 1')

    return '\n'.join(lines)


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/metrics':
            metrics = collect_metrics()
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            self.end_headers()
            self.wfile.write(metrics.encode())
        elif self.path == '/health':
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'OK')
        else:
            self.send_response(404)
            self.end_headers()

    def log_message(self, fmt, *args):
        pass


if __name__ == '__main__':
    print(f'PgBouncer exporter starting on port {EXPORTER_PORT}', flush=True)
    server = HTTPServer(('0.0.0.0', EXPORTER_PORT), Handler)
    print(f'Listening on http://0.0.0.0:{EXPORTER_PORT}/metrics', flush=True)
    server.serve_forever()
