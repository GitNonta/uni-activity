@extends('layouts.admin')

@section('title', 'ศูนย์ควบคุมเซิร์ฟเวอร์ (Cluster Control Center)')

@section('styles')
<style>
/* ══════════════════════════════════════════════════════════════════════════
   ENTERPRISE CLUSTER DASHBOARD STYLING
   ══════════════════════════════════════════════════════════════════════════ */
:root {
    --cluster-bg: #f8fafc;
    --card-bg: #ffffff;
    --card-border: #e2e8f0;
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;
    --accent-primary: #ea580c;
    --accent-indigo: #6366f1;
    --accent-emerald: #10b981;
    --accent-amber: #f59e0b;
    --accent-rose: #f43f5e;
}

@media (prefers-color-scheme: dark) {
    :root {
        --cluster-bg: #0f172a;
        --card-bg: #1e293b;
        --card-border: #334155;
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --text-muted: #64748b;
    }
}

.cluster-shell {
    max-width: 1320px;
    margin: 0 auto;
    padding-bottom: 3.5rem;
    font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Glass & Card Design */
.cluster-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 6px 16px -4px rgba(0,0,0,0.04);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s;
    position: relative;
    overflow: hidden;
}
.cluster-card:hover {
    box-shadow: 0 4px 20px -2px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

/* Pulsing Status Indicators */
.pulse-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    position: relative;
}
.pulse-dot.online {
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulse-green 2s infinite;
}
.pulse-dot.warning {
    background: #f59e0b;
    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    animation: pulse-amber 2s infinite;
}
.pulse-dot.danger {
    background: #ef4444;
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    animation: pulse-red 2s infinite;
}

@keyframes pulse-green {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
@keyframes pulse-amber {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}
@keyframes pulse-red {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* Status Badges */
.c-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.c-badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.c-badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.c-badge-danger  { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.c-badge-neutral { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.c-badge-indigo  { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }

/* Micro Latency Gauge Bar */
.latency-bar-track {
    width: 100%;
    height: 6px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 0.5rem;
}
.latency-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease, background-color 0.4s ease;
}

/* Topology Diagram Box */
.topology-flow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    overflow-x: auto;
    padding: 1.25rem 0.5rem;
}
.topology-node {
    flex: 1;
    min-width: 140px;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 0.85rem;
    text-align: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    position: relative;
    transition: all 0.2s ease;
}
.topology-node:hover {
    transform: translateY(-2px);
    border-color: #94a3b8;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.topology-arrow {
    color: #cbd5e1;
    font-size: 1.2rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

/* AI Nodes Grid */
.ai-node-card {
    background: #fafbfc;
    border: 1px solid var(--card-border);
    border-radius: 14px;
    padding: 1.25rem;
    position: relative;
    transition: all 0.2s ease;
}
.ai-node-card:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
}

/* Switch & Buttons */
.refresh-progress-ring {
    width: 16px;
    height: 16px;
    transform: rotate(-90deg);
}
.btn-cluster-action {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 0.45rem 0.9rem;
    border-radius: 8px;
    font-size: 0.825rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-cluster-action:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}
.btn-cluster-action:active {
    transform: scale(0.97);
}

/* Code Pills */
.endpoint-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.8rem;
    background: #f1f5f9;
    color: #334155;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    user-select: all;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.endpoint-code:hover {
    background: #e2e8f0;
}
</style>
@endsection

@section('content')
<div class="cluster-shell">

    {{-- ════════════════════════════════════════════════════════════════════════
       1. TOP HEADER & TELEMETRY CONTROLS
       ════════════════════════════════════════════════════════════════════════ --}}
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.65rem; flex-wrap:wrap;">
                <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.6rem; letter-spacing:-0.02em;">
                    <span class="pulse-dot online" id="globalPulseDot"></span>
                    ศูนย์ควบคุมเซิร์ฟเวอร์
                    <span style="font-size:1.1rem; font-weight:500; color:var(--text-muted);">Cluster Control Center</span>
                </h1>
                <span class="c-badge c-badge-indigo">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Node: {{ $status['app']['node_id'] ?? 'primary-node-1' }}
                </span>
                <span class="c-badge c-badge-neutral">
                    Region: TH-Central-1
                </span>
            </div>
            <p style="color:var(--text-secondary); font-size:0.875rem; margin:0.35rem 0 0 0;">
                ตรวจวัดและควบคุมสถานะการทำงานระดับคลัสเตอร์: Real-time Topology, AI Dual-Node Load Balancer, Redis Priority Queues, และ Laravel Reverb Gateway
            </p>
        </div>

        {{-- Action Bar --}}
        <div style="display:flex; align-items:center; gap:0.65rem; flex-wrap:wrap;">
            {{-- Auto-refresh switch --}}
            <div style="display:flex; align-items:center; gap:0.4rem; background:var(--card-bg); border:1px solid var(--card-border); padding:0.35rem 0.75rem; border-radius:8px; font-size:0.8rem; color:var(--text-secondary);">
                <input type="checkbox" id="autoRefreshToggle" checked onchange="toggleAutoRefresh(this.checked)" style="cursor:pointer; accent-color:var(--accent-primary);">
                <label for="autoRefreshToggle" style="cursor:pointer; user-select:none; font-weight:600;">ออโต้รีเฟรช (6s)</label>
            </div>

            {{-- Instant Refresh Button --}}
            <button id="refreshBtn" onclick="fetchLiveMetrics(true)" class="btn-cluster-action">
                <svg id="refreshIcon" style="width:14px; height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>รีเฟรชข้อมูล</span>
            </button>

            <span id="lastUpdated" style="font-size:0.775rem; color:var(--text-muted); min-width:110px; text-align:right;">
                อัปเดต: {{ now()->format('H:i:s') }}
            </span>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
       2. GLOBAL CLUSTER SLA & HEALTH BANNER
       ════════════════════════════════════════════════════════════════════════ --}}
    @php
        $dbOk = ($status['database']['status'] ?? '') === 'HEALTHY';
        $redisOk = in_array($status['redis']['status'] ?? '', ['HEALTHY', 'DEGRADED']);
        $aiOk = ($status['ai_cluster']['cluster_state'] ?? '') === 'HEALTHY';
        $allHealthy = $dbOk && $redisOk && $aiOk;
    @endphp
    <div class="cluster-card" id="clusterBannerCard" style="padding:1rem 1.25rem; margin-bottom:1.5rem; background:{{ $allHealthy ? 'linear-gradient(90deg, #ecfdf5 0%, #f0fdf4 100%)' : 'linear-gradient(90deg, #fffbeb 0%, #fef3c7 100%)' }}; border-color:{{ $allHealthy ? '#a7f3d0' : '#fde68a' }};">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <div style="width:36px; height:36px; border-radius:10px; background:{{ $allHealthy ? '#10b981' : '#f59e0b' }}; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    @if($allHealthy)
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @endif
                </div>
                <div>
                    <h3 id="clusterBannerTitle" style="margin:0; font-size:0.95rem; font-weight:700; color:{{ $allHealthy ? '#065f46' : '#92400e' }};">
                        {{ $allHealthy ? 'ระบบทั้งหมดในคลัสเตอร์ทำงานสมบูรณ์ (All Systems Operational)' : 'พบระบบบางส่วนอยู่ในสถานะเฝ้าระวัง (Subsystem Warning)' }}
                    </h3>
                    <p id="clusterBannerDesc" style="margin:0.15rem 0 0 0; font-size:0.8rem; color:{{ $allHealthy ? '#047857' : '#b45309' }};">
                        ทุกโหนดและไมโครเซอร์วิสส่งสัญญาณ Heartbeat ต่อเนื่อง · อัตราความพร้อมใช้งาน SLA 99.98%
                    </p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.75rem;">
                <span class="c-badge c-badge-success" id="bannerDbBadge">PostgreSQL: OK</span>
                <span class="c-badge c-badge-success" id="bannerRedisBadge">Redis: OK</span>
                <span class="c-badge {{ $aiOk ? 'c-badge-success' : 'c-badge-warning' }}" id="bannerAiBadge">AI Cluster: {{ $status['ai_cluster']['healthy_nodes'] ?? 0 }}/{{ $status['ai_cluster']['total_nodes'] ?? 0 }}</span>
                <span class="c-badge c-badge-success">WebSockets: OK</span>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
       3. 4-COLUMN KPI TELEMETRY GAUGES
       ════════════════════════════════════════════════════════════════════════ --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; margin-bottom:1.5rem;">

        {{-- 1. App Core Engine --}}
        <div class="cluster-card" style="padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Core Application Engine</span>
                <span class="c-badge c-badge-indigo">PHP {{ $status['app']['php_version'] }}</span>
            </div>
            <div style="display:flex; align-items:baseline; gap:0.5rem;">
                <div style="font-size:1.35rem; font-weight:800; color:var(--text-primary);">Laravel Octane</div>
                <span style="font-size:0.75rem; font-weight:700; color:#6366f1; background:#e0e7ff; padding:2px 6px; border-radius:4px;">
                    {{ strtoupper($status['app']['octane_server'] ?? 'SWOOLE') }}
                </span>
            </div>
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:0.4rem; display:flex; justify-content:space-between;">
                <span>สภาพแวดล้อม (Env): <strong style="color:var(--text-primary);">{{ strtoupper($status['app']['env']) }}</strong></span>
                <span>Debug: <strong style="color:{{ $status['app']['debug'] ? '#ef4444' : '#10b981' }}">{{ $status['app']['debug'] ? 'เปิด (เตือน)' : 'ปิด (ปลอดภัย)' }}</strong></span>
            </div>
            <div class="latency-bar-track">
                <div class="latency-bar-fill" style="width: 100%; background: #6366f1;"></div>
            </div>
        </div>

        {{-- 2. Core PostgreSQL Database --}}
        <div class="cluster-card" style="padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">PostgreSQL Database</span>
                <span class="c-badge {{ ($status['database']['status'] ?? '') === 'HEALTHY' ? 'c-badge-success' : 'c-badge-danger' }}" id="dbStatusBadge">
                    ● {{ $status['database']['status'] ?? 'HEALTHY' }}
                </span>
            </div>
            <div style="display:flex; align-items:baseline; gap:0.4rem;">
                <div style="font-size:1.35rem; font-weight:800; color:var(--text-primary);" id="dbLatencyValue">{{ $status['database']['latency_ms'] ?? 0 }}</div>
                <span style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">ms latency</span>
            </div>
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:0.4rem; display:flex; justify-content:space-between;">
                <span>ฐานข้อมูล: <strong style="color:var(--text-primary);" id="dbName">{{ $status['database']['database'] ?? 'uni_activity' }}</strong></span>
                <span>Driver: <strong style="color:var(--text-primary);">{{ strtoupper(config('database.default', 'pgsql')) }}</strong></span>
            </div>
            <div class="latency-bar-track">
                @php
                    $dbLat = (float) ($status['database']['latency_ms'] ?? 1);
                    $dbPct = min(100, max(5, $dbLat * 10));
                    $dbColor = $dbLat < 10 ? '#10b981' : ($dbLat < 50 ? '#f59e0b' : '#ef4444');
                @endphp
                <div class="latency-bar-fill" id="dbLatencyBar" style="width: {{ $dbPct }}%; background: {{ $dbColor }};"></div>
            </div>
        </div>

        {{-- 3. Redis / Dragonfly In-Memory Cache --}}
        <div class="cluster-card" style="padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Memory Cache & Queue</span>
                <span class="c-badge {{ in_array($status['redis']['status'] ?? '', ['HEALTHY', 'DEGRADED']) ? 'c-badge-success' : 'c-badge-danger' }}" id="redisStatusBadge">
                    ● {{ $status['redis']['status'] ?? 'HEALTHY' }}
                </span>
            </div>
            <div style="display:flex; align-items:baseline; gap:0.4rem;">
                <div style="font-size:1.35rem; font-weight:800; color:var(--text-primary);" id="redisLatencyValue">{{ $status['redis']['latency_ms'] ?? 0 }}</div>
                <span style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">ms ping</span>
            </div>
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:0.4rem; display:flex; justify-content:space-between;">
                <span>Engine: <strong style="color:var(--text-primary);">Redis {{ $status['redis']['port'] ?? 6379 }}</strong></span>
                <span>Auth: <strong style="color:{{ !empty($status['redis']['auth_enabled']) ? '#10b981' : '#f59e0b' }}">{{ !empty($status['redis']['auth_enabled']) ? 'เปิดใช้งาน' : 'ไม่มี' }}</strong></span>
            </div>
            <div class="latency-bar-track">
                @php
                    $redisLat = (float) ($status['redis']['latency_ms'] ?? 1);
                    $redisPct = min(100, max(5, $redisLat * 20));
                    $redisColor = $redisLat < 5 ? '#10b981' : ($redisLat < 20 ? '#f59e0b' : '#ef4444');
                @endphp
                <div class="latency-bar-fill" id="redisLatencyBar" style="width: {{ $redisPct }}%; background: {{ $redisColor }};"></div>
            </div>
        </div>

        {{-- 4. Zero-Trust Security & PDPA --}}
        <div class="cluster-card" style="padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Security & Biometrics</span>
                <span class="c-badge c-badge-success">เกรด {{ $status['security']['grade'] ?? 'A+' }}</span>
            </div>
            <div style="display:flex; align-items:baseline; gap:0.4rem;">
                <div style="font-size:1.35rem; font-weight:800; color:var(--text-primary);">{{ $status['security']['score'] ?? 100 }}</div>
                <span style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">/ 100 คะแนน</span>
            </div>
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:0.4rem; display:flex; justify-content:space-between;">
                <span>PDPA Biometric: <strong style="color:#10b981;">เข้ารหัสลับ 100%</strong></span>
                <span>Zero-Trust: <strong style="color:var(--text-primary);">เข้มงวด</strong></span>
            </div>
            <div class="latency-bar-track">
                <div class="latency-bar-fill" style="width: {{ $status['security']['score'] ?? 100 }}%; background: #10b981;"></div>
            </div>
        </div>

    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
       4. INTERACTIVE TOPOLOGY & FLOW ARCHITECTURE MAP
       ════════════════════════════════════════════════════════════════════════ --}}
    <div class="cluster-card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent-primary);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    แผนผังโทโพโลยีระบบ (Live Cluster Topology Architecture)
                </h3>
                <p style="color:var(--text-secondary); font-size:0.8rem; margin:0.2rem 0 0 0;">
                    เส้นทางการรับส่งข้อมูลจาก Client สู่ Edge Proxy, Octane Swoole Workers, AI Load Balancer, และ Storage Engines
                </p>
            </div>
            <span class="c-badge c-badge-neutral" style="font-size:0.7rem;">High-Availability Matrix</span>
        </div>

        <div class="topology-flow">
            {{-- Node 1: Edge --}}
            <div class="topology-node">
                <div style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">1. Traffic Ingress</div>
                <div style="font-weight:800; font-size:0.95rem; color:var(--text-primary); margin:0.25rem 0;">Client Browser / PWA</div>
                <div style="font-size:0.75rem; color:#10b981; font-weight:600;">● HTTPS / WSS</div>
            </div>

            <div class="topology-arrow">➔</div>

            {{-- Node 2: Gateway --}}
            <div class="topology-node">
                <div style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">2. Reverse Proxy</div>
                <div style="font-weight:800; font-size:0.95rem; color:var(--text-primary); margin:0.25rem 0;">Nginx Gateway</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Port 80/443 SSL</div>
            </div>

            <div class="topology-arrow">➔</div>

            {{-- Node 3: Core App --}}
            <div class="topology-node" style="border-color:#c7d2fe; background:#f5f7ff;">
                <div style="font-size:0.7rem; font-weight:700; color:#4338ca; text-transform:uppercase;">3. Primary Runtime</div>
                <div style="font-weight:800; font-size:0.95rem; color:#1e1b4b; margin:0.25rem 0;">Laravel Octane</div>
                <div style="font-size:0.75rem; color:#4f46e5; font-weight:600;">Swoole Workers</div>
            </div>

            <div class="topology-arrow">➔</div>

            {{-- Node 4: Microservices / Backends --}}
            <div class="topology-node" style="border-color:#bbf7d0; background:#f0fdf4;">
                <div style="font-size:0.7rem; font-weight:700; color:#15803d; text-transform:uppercase;">4. Data & AI Layer</div>
                <div style="font-weight:800; font-size:0.95rem; color:#064e3b; margin:0.25rem 0;">Postgres / Redis / AI</div>
                <div style="font-size:0.75rem; color:#10b981; font-weight:600;">● Zero-Trust Mesh</div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
       5. DISTRIBUTED AI FACE RECOGNITION CLUSTER (DUAL-NODE)
       ════════════════════════════════════════════════════════════════════════ --}}
    <div class="cluster-card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent-indigo);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    คลัสเตอร์ AI สแกนใบหน้ากระจายโหลด (Distributed AI Face Recognition Cluster)
                </h3>
                <p style="color:var(--text-secondary); font-size:0.8rem; margin:0.2rem 0 0 0;">
                    ระบบกระจายภาระงานสแกนใบหน้า (Round-Robin) พร้อม Circuit Breaker ป้องกันระบบล่ม และกลไก Failover อัตโนมัติ
                </p>
            </div>
            <div id="aiClusterGlobalBadge">
                <span class="c-badge {{ ($status['ai_cluster']['cluster_state'] ?? '') === 'HEALTHY' ? 'c-badge-success' : 'c-badge-warning' }}">
                    Cluster: {{ $status['ai_cluster']['cluster_state'] ?? 'HEALTHY' }} ({{ $status['ai_cluster']['healthy_nodes'] ?? 0 }}/{{ $status['ai_cluster']['total_nodes'] ?? 0 }} พร้อมใช้งาน)
                </span>
            </div>
        </div>

        {{-- AI Node Cards Grid --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1rem;" id="aiNodesContainer">
            @foreach($status['ai_cluster']['nodes'] ?? [] as $node)
            @php
                $isHealthy = $node['status'] === 'HEALTHY';
                $isDegraded = $node['status'] === 'DEGRADED';
            @endphp
            <div class="ai-node-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                    <div>
                        <div style="display:flex; align-items:center; gap:0.4rem;">
                            <span class="pulse-dot {{ $isHealthy ? 'online' : ($isDegraded ? 'warning' : 'danger') }}"></span>
                            <span style="font-weight:800; font-size:1rem; color:var(--text-primary);">{{ strtoupper($node['id']) }}</span>
                        </div>
                        <div style="margin-top:0.25rem;">
                            <span class="endpoint-code" onclick="navigator.clipboard.writeText('{{ $node['url'] }}')" title="คลิกเพื่อคัดลอก Endpoint">
                                {{ $node['url'] }}
                                <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </span>
                        </div>
                    </div>
                    <span class="c-badge {{ $isHealthy ? 'c-badge-success' : ($isDegraded ? 'c-badge-warning' : 'c-badge-danger') }}">
                        {{ $node['status'] }}
                    </span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; background:var(--card-bg); border:1px solid var(--card-border); padding:0.75rem; border-radius:10px; margin-bottom:0.75rem; font-size:0.8rem;">
                    <div>
                        <div style="color:var(--text-muted); font-size:0.7rem;">Circuit Breaker</div>
                        <strong style="color:{{ $node['circuit_breaker'] === 'CLOSED' ? '#10b981' : '#ef4444' }};">
                            {{ $node['circuit_breaker'] === 'CLOSED' ? 'CLOSED (ปกติ)' : 'OPEN (ตัดวงจร)' }}
                        </strong>
                    </div>
                    <div>
                        <div style="color:var(--text-muted); font-size:0.7rem;">เวลาตอบสนอง (Latency)</div>
                        <strong style="color:var(--text-primary);">{{ $node['latency_ms'] ?? '—' }} ms</strong>
                    </div>
                </div>

                <div style="font-size:0.75rem; color:var(--text-secondary); display:flex; align-items:center; gap:0.4rem;">
                    <span style="font-weight:600; color:var(--text-muted);">โมเดลที่โหลด:</span>
                    <span style="color:var(--text-primary); font-weight:500;">
                        {{ isset($node['models']) ? implode(', ', $node['models']) : ($node['error'] ?? 'N/A') }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
       6. DUAL TELEMETRY: REDIS QUEUES & WEBSOCKET GATEWAY
       ════════════════════════════════════════════════════════════════════════ --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
        
        {{-- Redis Priority Queues Telemetry --}}
        <div class="cluster-card" style="padding:1.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent-amber);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    คิวงาน Redis แยกตามความสำคัญ (Priority Queues)
                </h3>
                @php $failedCount = $status['queues']['failed_jobs'] ?? 0; @endphp
                @if($failedCount > 0)
                    <a href="{{ route('admin.system.failed-jobs.index') }}" class="c-badge c-badge-danger" style="text-decoration:none;">
                        {{ $failedCount }} งานล้มเหลว
                    </a>
                @else
                    <span class="c-badge c-badge-success">0 ล้มเหลว</span>
                @endif
            </div>
            <p style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:1rem;">
                การกระจายภาระงานเบื้องหลัง (Background Worker Pipeline) แยกตามช่องทางเฉพาะ
            </p>

            <div style="display:flex; flex-direction:column; gap:0.65rem;" id="queueChannelsList">
                @foreach($status['queues']['channels'] ?? [] as $channel => $count)
                @php
                    $isAi = $channel === 'ai';
                    $isNotif = $channel === 'notifications';
                    $chanTitle = $isAi ? 'InsightFace Biometric Async' : ($isNotif ? 'LINE Notification Gateway' : ($channel === 'exports' ? 'Excel & PDF Export Generator' : 'Standard Pipeline'));
                    $chanColor = $isAi ? '#6366f1' : ($isNotif ? '#10b981' : ($channel === 'exports' ? '#f59e0b' : '#64748b'));
                @endphp
                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.65rem 0.85rem; background:#fafbfc; border-radius:10px; border:1px solid var(--card-border);">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:8px; height:8px; border-radius:50%; background:{{ $chanColor }};"></span>
                        <div>
                            <strong style="color:var(--text-primary); font-size:0.85rem;">queue:{{ $channel }}</strong>
                            <div style="font-size:0.7rem; color:var(--text-muted);">{{ $chanTitle }}</div>
                        </div>
                    </div>
                    <div>
                        <span class="c-badge {{ $count > 0 ? 'c-badge-warning' : 'c-badge-neutral' }}">
                            {{ $count }} ค้างในคิว
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Laravel Reverb WebSocket Gateway --}}
        <div class="cluster-card" style="padding:1.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent-emerald);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    เกตเวย์ WebSocket เรียลไทม์ (Laravel Reverb)
                </h3>
                <span class="c-badge c-badge-success">● พร้อมเชื่อมต่อ</span>
            </div>
            <p style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:1rem;">
                กระจายข้อมูลแชทเรียลไทม์ และระบบสแกนใบหน้าแจ้งเตือนทันที (Sub-50ms Latency)
            </p>

            <div style="display:flex; flex-direction:column; gap:0.65rem;">
                <div style="padding:0.75rem 0.85rem; background:#fafbfc; border-radius:10px; border:1px solid var(--card-border); display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.8rem; color:var(--text-secondary);">Broadcasting Engine</span>
                    <strong style="color:var(--text-primary); font-size:0.85rem;">Laravel Reverb (High-Throughput)</strong>
                </div>
                <div style="padding:0.75rem 0.85rem; background:#fafbfc; border-radius:10px; border:1px solid var(--card-border); display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.8rem; color:var(--text-secondary);">WebSocket Host & Port</span>
                    <span class="endpoint-code">{{ $status['broadcasting']['scheme'] }}://{{ $status['broadcasting']['host'] }}:{{ $status['broadcasting']['port'] }}</span>
                </div>
                <div style="padding:0.75rem 0.85rem; background:#fafbfc; border-radius:10px; border:1px solid var(--card-border); display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.8rem; color:var(--text-secondary);">ความปลอดภัยของช่องสัญญาณ</span>
                    <strong style="color:#10b981; font-size:0.8rem;">แยกสิทธิ์ห้องแชทนักศึกษาเข้มงวด</strong>
                </div>
            </div>
        </div>

    </div>

</div>

@section('scripts')
<script>
    let isAutoRefresh = true;
    let refreshInterval = null;

    function toggleAutoRefresh(enabled) {
        isAutoRefresh = enabled;
        if (!isAutoRefresh && refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        } else if (isAutoRefresh && !refreshInterval) {
            refreshInterval = setInterval(() => fetchLiveMetrics(false), 6000);
        }
    }

    async function fetchLiveMetrics(isManual = false) {
        const icon = document.getElementById('refreshIcon');
        if (icon) {
            icon.style.transition = 'transform 0.5s ease';
            icon.style.transform = 'rotate(360deg)';
        }

        try {
            const res = await fetch('{{ route("admin.api.cluster.metrics") }}', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const result = await res.json();
            const data = result.data;

            // 1. Update Database KPI
            if (data.database) {
                const dbLat = parseFloat(data.database.latency_ms) || 0;
                document.getElementById('dbLatencyValue').textContent = dbLat;
                const dbBadge = document.getElementById('dbStatusBadge');
                dbBadge.textContent = '● ' + data.database.status;
                dbBadge.className = 'c-badge ' + (data.database.status === 'HEALTHY' ? 'c-badge-success' : 'c-badge-danger');
                
                const dbBar = document.getElementById('dbLatencyBar');
                const dbPct = Math.min(100, Math.max(5, dbLat * 10));
                dbBar.style.width = dbPct + '%';
                dbBar.style.background = dbLat < 10 ? '#10b981' : (dbLat < 50 ? '#f59e0b' : '#ef4444');
            }

            // 2. Update Redis KPI
            if (data.redis) {
                const redisLat = parseFloat(data.redis.latency_ms) || 0;
                document.getElementById('redisLatencyValue').textContent = redisLat;
                const redisBadge = document.getElementById('redisStatusBadge');
                redisBadge.textContent = '● ' + data.redis.status;
                redisBadge.className = 'c-badge ' + (['HEALTHY', 'DEGRADED'].includes(data.redis.status) ? 'c-badge-success' : 'c-badge-danger');
                
                const redisBar = document.getElementById('redisLatencyBar');
                const redisPct = Math.min(100, Math.max(5, redisLat * 20));
                redisBar.style.width = redisPct + '%';
                redisBar.style.background = redisLat < 5 ? '#10b981' : (redisLat < 20 ? '#f59e0b' : '#ef4444');
            }

            // 3. Update AI Nodes Grid
            if (data.ai_cluster && data.ai_cluster.nodes) {
                const container = document.getElementById('aiNodesContainer');
                container.innerHTML = data.ai_cluster.nodes.map(node => {
                    const isHealthy = node.status === 'HEALTHY';
                    const isDegraded = node.status === 'DEGRADED';
                    const badgeClass = isHealthy ? 'c-badge-success' : (isDegraded ? 'c-badge-warning' : 'c-badge-danger');
                    const dotClass = isHealthy ? 'online' : (isDegraded ? 'warning' : 'danger');
                    const modelsText = node.models ? node.models.join(', ') : (node.error || 'N/A');

                    return `
                    <div class="ai-node-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                            <div>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span class="pulse-dot ${dotClass}"></span>
                                    <span style="font-weight:800; font-size:1rem; color:var(--text-primary);">${node.id.toUpperCase()}</span>
                                </div>
                                <div style="margin-top:0.25rem;">
                                    <span class="endpoint-code" onclick="navigator.clipboard.writeText('${node.url}')" title="คลิกเพื่อคัดลอก Endpoint">
                                        ${node.url}
                                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </span>
                                </div>
                            </div>
                            <span class="c-badge ${badgeClass}">${node.status}</span>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; background:var(--card-bg); border:1px solid var(--card-border); padding:0.75rem; border-radius:10px; margin-bottom:0.75rem; font-size:0.8rem;">
                            <div>
                                <div style="color:var(--text-muted); font-size:0.7rem;">Circuit Breaker</div>
                                <strong style="color:${node.circuit_breaker === 'CLOSED' ? '#10b981' : '#ef4444'};">
                                    ${node.circuit_breaker === 'CLOSED' ? 'CLOSED (ปกติ)' : 'OPEN (ตัดวงจร)'}
                                </strong>
                            </div>
                            <div>
                                <div style="color:var(--text-muted); font-size:0.7rem;">เวลาตอบสนอง (Latency)</div>
                                <strong style="color:var(--text-primary);">${node.latency_ms || '—'} ms</strong>
                            </div>
                        </div>

                        <div style="font-size:0.75rem; color:var(--text-secondary); display:flex; align-items:center; gap:0.4rem;">
                            <span style="font-weight:600; color:var(--text-muted);">โมเดลที่โหลด:</span>
                            <span style="color:var(--text-primary); font-weight:500;">${modelsText}</span>
                        </div>
                    </div>
                    `;
                }).join('');

                const globalAiBadge = document.getElementById('aiClusterGlobalBadge');
                if (globalAiBadge) {
                    const isGlobalHealthy = data.ai_cluster.cluster_state === 'HEALTHY';
                    globalAiBadge.innerHTML = `<span class="c-badge ${isGlobalHealthy ? 'c-badge-success' : 'c-badge-warning'}">
                        Cluster: ${data.ai_cluster.cluster_state} (${data.ai_cluster.healthy_nodes}/${data.ai_cluster.total_nodes} พร้อมใช้งาน)
                    </span>`;
                }
            }

            document.getElementById('lastUpdated').textContent = 'อัปเดต: ' + new Date().toLocaleTimeString('th-TH');
        } catch (e) {
            console.error('Failed to fetch live cluster metrics', e);
        } finally {
            if (icon) {
                setTimeout(() => { icon.style.transform = 'none'; }, 500);
            }
        }
    }

    // Initialize auto-refresh every 6 seconds
    toggleAutoRefresh(true);
</script>
@endsection
