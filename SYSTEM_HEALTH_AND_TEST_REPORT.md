# 📋 Official System & Services Status Report
> **Source of Truth:** Live Monitor Server Dashboard (`http://192.168.1.222:9999/#dashboard`)  
> **Timestamp:** 2026-08-24 23:07:30 (ICT)  
> **Environment:** Primary Server `s1` (`192.168.1.222`) & Secondary Node `s2` (`192.168.1.140`)

---

## 🟢 1. Official Services Status (Factual Live Dashboard)

| Service Name | Port / Binding | Type / Role | Dashboard Live Status |
| :--- | :--- | :--- | :---: |
| **Nginx (Edge Proxy)** | `0.0.0.0:8080`, `:8088` | Gateway / Reverse Proxy / Load Balancer | 🟢 **Running** |
| **Laravel Application Server** | `:8000`, `:8002`, `:8003` | Active Multi-Worker Engine | 🟢 **Running** |
| **Swoole / OpenSwoole** | High-Performance Engine | In-Memory State & Tables | 🟢 **Running** |
| **Laravel Reverb (WebSocket)** | `0.0.0.0:8082` | Real-time Push & Broadcast Engine | 🟢 **Running** |
| **Datastore (Valkey)** | `0.0.0.0:6379`, `:6380` | Cache, Sessions, Locks, Queue Store | 🟢 **Running** |
| **PostgreSQL 16 Database** | `0.0.0.0:5432` | Core Relational Database (14 MB, 10 conn) | 🟢 **Running** |
| **Redis Queue Worker** | Priority Workers | Background Job Processor (0 failed/pending) | 🟢 **Running** |
| **AI Biometrics Face Service** | `127.0.0.1:8001` (`:8000` mapped) | InsightFace + Liveness Detection Engine | 🟢 **Running** |
| **Cloudflared Tunnel** | Dual Tunnels | HTTP & SSH Secure Ingress (Latency: 20ms) | 🟢 **Running** |
| **SSH / SFTP Server** | `0.0.0.0:8022` | Secure Remote Management Daemon | 🟢 **Running** |

---

## 📊 2. Live Hardware & Telemetry Metrics

- **Memory Usage:** `78.7%` (`2,811 MB Used` / `3,572 MB Total` — `760 MB Available`)
- **Storage (`/data`):** `78.0%` (`41.86 GB Used` / `53.65 GB Total` — `11.79 GB Free`)
- **Battery Status:** `100% FULL` (Voltage: `4,334 mV`)
- **Wi-Fi Signal:** `-62 dBm` (Stable Connection)
- **Active Listening Ports:** `[5432, 6379, 6380, 8000, 8001, 8002, 8003, 8022, 8080, 8082, 8088, 9999, 20241, 20242]`
- **Public IP Address:** `171.97.243.111`

---

## 🌐 3. Cloudflare Ingress & Public Endpoints

- **Public Web Application URL:** [https://folk-timing-intl-seasons.trycloudflare.com](https://folk-timing-intl-seasons.trycloudflare.com) — 🟢 **Online (`HTTP 200`, Latency: 20.0ms)**
- **Public SSH Tunnel URL:** `https://default-bids-resist-essays.trycloudflare.com` — 🟢 **Online**

---

## 🤖 4. AI Biometric Cluster Status

- **Node 1 (Local `127.0.0.1:8001`):** 🟢 **Available (`ok`)**
  - **Latency:** `15ms`
  - **InsightFace Model:** `Loaded (True)`
  - **Liveness Anti-Spoofing:** `Loaded (True)`
  - **Version:** `2.0.0`
- **Node 2 (Remote `192.168.1.140:8001`):** 🟡 **Standby / Down (`TCP port closed`)**
  - Failover automatically routed to Node 1 via circuit breaker.

---

## ⚡ 5. Database & Queue Telemetry

- **PostgreSQL Database:** Size: `14 MB` | Active Connections: `10` | Latency: `< 2ms`
- **Valkey Datastore:** Active on `:6379` (Sessions/Cache) and `:6380` (Queue)
- **Background Queue:** Pending Jobs: `0` | Failed Jobs: `0`
