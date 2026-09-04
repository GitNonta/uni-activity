#!/usr/bin/env python3
"""
Lightweight, robust SOCKS5 proxy server for Termux with access logging.
Usage: python3 socks5_proxy.py [listen_port] [bind_ip]
"""
import socket
import threading
import sys
import select
import time
import os

LISTEN_PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 1080
BIND_IP = sys.argv[2] if len(sys.argv) > 2 else "0.0.0.0"
BUFFER_SIZE = 16384
TIMEOUT = 60
LOG_FILE = os.path.expanduser("~/uni-activity/storage/logs/socks5-access.log")
BLOCKLIST_FILE = os.path.expanduser("~/uni-activity/storage/proxy_blocklist.json")
_cached_blocklist = {"domains": set(), "ips": set(), "last_mtime": 0}

def get_blocklist():
    """Read blocked domains and IPs from json with automatic file mtime cache."""
    try:
        if not os.path.exists(BLOCKLIST_FILE):
            return set(), set()
        mtime = os.path.getmtime(BLOCKLIST_FILE)
        if mtime != _cached_blocklist["last_mtime"]:
            import json
            with open(BLOCKLIST_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
            domains = set(d.lower().strip() for d in data.get("blocked_domains", []))
            ips = set(ip.strip() for ip in data.get("blocked_ips", []))
            _cached_blocklist["domains"] = domains
            _cached_blocklist["ips"] = ips
            _cached_blocklist["last_mtime"] = mtime
        return _cached_blocklist["domains"], _cached_blocklist["ips"]
    except Exception:
        return _cached_blocklist["domains"], _cached_blocklist["ips"]

def is_target_blocked(dst_addr, resolved_ip=None):
    """Check if destination address or resolved IP is blocked."""
    domains, ips = get_blocklist()
    addr_lower = dst_addr.lower().strip()
    if addr_lower in domains:
        return True
    for d in domains:
        if d and (addr_lower == d or addr_lower.endswith("." + d)):
            return True
    if dst_addr in ips:
        return True
    if resolved_ip and resolved_ip in ips:
        return True
    return False

def log_access(client_ip, target, status_code, bytes_transferred, duration_ms):
    """Log SOCKS5 traffic in a format compatible with Squid access log."""
    try:
        os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
        now = time.time()
        # Format: timestamp elapsed client code size method url - HIER_DIRECT/target -
        line = f"{now:.3f} {int(duration_ms)} {client_ip} SOCKS5/{status_code} {int(bytes_transferred)} CONNECT {target} - HIER_DIRECT/{target} -\n"
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(line)
    except Exception:
        pass

def handle_client(client_socket, addr):
    client_ip = addr[0]
    start_time = time.time()
    total_bytes = 0
    target_str = "unknown:0"
    status_code = "000"
    remote_socket = None

    try:
        client_socket.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)

        # SOCKS5 handshake
        data = client_socket.recv(256)
        if not data or data[0] != 0x05:
            return

        # No authentication required
        client_socket.sendall(b'\x05\x00')

        # SOCKS5 request
        data = client_socket.recv(256)
        if not data or len(data) < 4:
            return

        ver, cmd, _, atyp = data[0], data[1], data[2], data[3]
        if ver != 0x05 or cmd != 0x01:  # Only CONNECT is supported
            client_socket.sendall(b'\x05\x07\x00\x01\x00\x00\x00\x00\x00\x00')
            return

        # Parse destination address
        if atyp == 0x01:  # IPv4
            dst_addr = socket.inet_ntoa(data[4:8])
            dst_port = int.from_bytes(data[8:10], 'big')
        elif atyp == 0x03:  # Domain name
            domain_len = data[4]
            dst_addr = data[5:5+domain_len].decode('utf-8', errors='replace')
            dst_port = int.from_bytes(data[5+domain_len:5+domain_len+2], 'big')
        elif atyp == 0x04:  # IPv6
            dst_addr = socket.inet_ntop(socket.AF_INET6, data[4:20])
            dst_port = int.from_bytes(data[20:22], 'big')
        else:
            client_socket.sendall(b'\x05\x08\x00\x01\x00\x00\x00\x00\x00\x00')
            return

        target_str = f"{dst_addr}:{dst_port}"

        # 1. Blocklist check on destination host/domain
        if is_target_blocked(dst_addr):
            # 0x02 = connection not allowed by ruleset
            client_socket.sendall(b'\x05\x02\x00\x01\x00\x00\x00\x00\x00\x00')
            log_access(client_ip, target_str, "403", 0, (time.time() - start_time) * 1000)
            return

        # Resolve destination (prefer IPv4 to avoid broken IPv6 routes)
        resolved_addr = None
        try:
            addr_info = socket.getaddrinfo(dst_addr, dst_port, socket.AF_INET, socket.SOCK_STREAM)
            if addr_info:
                resolved_addr = addr_info[0][4]
        except Exception:
            pass

        if not resolved_addr:
            try:
                addr_info = socket.getaddrinfo(dst_addr, dst_port, socket.AF_UNSPEC, socket.SOCK_STREAM)
                if addr_info:
                    resolved_addr = addr_info[0][4]
            except Exception:
                client_socket.sendall(b'\x05\x04\x00\x01\x00\x00\x00\x00\x00\x00')
                status_code = "502"
                return

        # 2. Blocklist check on resolved IP
        if resolved_addr and is_target_blocked(dst_addr, resolved_addr[0]):
            client_socket.sendall(b'\x05\x02\x00\x01\x00\x00\x00\x00\x00\x00')
            log_access(client_ip, target_str, "403", 0, (time.time() - start_time) * 1000)
            return

        # Connect to target
        family = socket.AF_INET6 if ':' in resolved_addr[0] else socket.AF_INET
        remote_socket = socket.socket(family, socket.SOCK_STREAM)
        remote_socket.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
        remote_socket.settimeout(TIMEOUT)
        try:
            remote_socket.connect(resolved_addr)
        except Exception:
            client_socket.sendall(b'\x05\x05\x00\x01\x00\x00\x00\x00\x00\x00')
            status_code = "504"
            return

        # Success reply
        client_socket.sendall(b'\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00')
        status_code = "200"

        # Forward data bidirectionally
        sockets = [client_socket, remote_socket]
        while True:
            readable, _, exceptional = select.select(sockets, [], sockets, TIMEOUT)
            if exceptional or not readable:
                break

            closed = False
            for sock in readable:
                other = remote_socket if sock is client_socket else client_socket
                try:
                    chunk = sock.recv(BUFFER_SIZE)
                    if not chunk:
                        closed = True
                        break
                    total_bytes += len(chunk)
                    other.sendall(chunk)
                except Exception:
                    closed = True
                    break

            if closed:
                break

    except Exception:
        pass
    finally:
        duration_ms = (time.time() - start_time) * 1000
        if target_str != "unknown:0":
            log_access(client_ip, target_str, status_code, total_bytes, duration_ms)
        if client_socket:
            try:
                client_socket.close()
            except Exception:
                pass
        if remote_socket:
            try:
                remote_socket.close()
            except Exception:
                pass

def main():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEPORT, 1)
    except Exception:
        pass
    server.bind((BIND_IP, LISTEN_PORT))
    server.listen(256)
    print(f"SOCKS5 proxy listening on {BIND_IP}:{LISTEN_PORT}")

    while True:
        try:
            client, addr = server.accept()
            t = threading.Thread(target=handle_client, args=(client, addr), daemon=True)
            t.start()
        except KeyboardInterrupt:
            break
        except Exception:
            pass

if __name__ == "__main__":
    main()
