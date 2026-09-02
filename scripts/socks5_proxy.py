#!/usr/bin/env python3
"""
Lightweight SOCKS5 proxy server for Termux.
Usage: python3 socks5_proxy.py [listen_port] [bind_ip]
"""
import socket
import threading
import sys
import select

LISTEN_PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 1080
BIND_IP = sys.argv[2] if len(sys.argv) > 2 else "0.0.0.0"
BUFFER_SIZE = 8192
TIMEOUT = 30

def handle_client(client_socket, addr):
    try:
        # SOCKS5 handshake
        data = client_socket.recv(256)
        if not data or data[0] != 0x05:
            client_socket.close()
            return

        # No auth required
        client_socket.sendall(b'\x05\x00')

        # SOCKS5 request
        data = client_socket.recv(256)
        if not data or len(data) < 4:
            client_socket.close()
            return

        ver, cmd, _, atyp = data[0], data[1], data[2], data[3]
        if ver != 0x05 or cmd != 0x01:
            client_socket.sendall(b'\x05\x07\x00\x01\x00\x00\x00\x00\x00\x00')
            client_socket.close()
            return

        # Parse address
        if atyp == 0x01:  # IPv4
            dst_addr = socket.inet_ntoa(data[4:8])
            dst_port = int.from_bytes(data[8:10], 'big')
        elif atyp == 0x03:  # Domain
            domain_len = data[4]
            dst_addr = data[5:5+domain_len].decode()
            dst_port = int.from_bytes(data[5+domain_len:5+domain_len+2], 'big')
        elif atyp == 0x04:  # IPv6
            dst_addr = socket.inet_ntop(socket.AF_INET6, data[4:20])
            dst_port = int.from_bytes(data[20:22], 'big')
        else:
            client_socket.sendall(b'\x05\x08\x00\x01\x00\x00\x00\x00\x00\x00')
            client_socket.close()
            return

        # Connect to target
        remote_socket = socket.socket(socket.AF_INET if ':' not in dst_addr else socket.AF_INET6, socket.SOCK_STREAM)
        remote_socket.settimeout(TIMEOUT)
        try:
            remote_socket.connect((dst_addr, dst_port))
        except Exception:
            client_socket.sendall(b'\x05\x05\x00\x01\x00\x00\x00\x00\x00\x00')
            client_socket.close()
            return

        # Success reply
        client_socket.sendall(b'\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00')

        # Forward data
        sockets = [client_socket, remote_socket]
        while True:
            readable, _, exceptional = select.select(sockets, [], sockets, TIMEOUT)
            if exceptional:
                break
            if not readable:
                break
            for sock in readable:
                try:
                    other = remote_socket if sock is client_socket else client_socket
                    data = sock.recv(BUFFER_SIZE)
                    if not data:
                        break
                    other.sendall(data)
                except:
                    break
        remote_socket.close()
    except Exception:
        pass
    finally:
        client_socket.close()

def main():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEPORT, 1)
    server.bind((BIND_IP, LISTEN_PORT))
    server.listen(128)
    print(f"SOCKS5 proxy listening on {BIND_IP}:{LISTEN_PORT}")

    while True:
        client, addr = server.accept()
        t = threading.Thread(target=handle_client, args=(client, addr), daemon=True)
        t.start()

if __name__ == "__main__":
    main()
