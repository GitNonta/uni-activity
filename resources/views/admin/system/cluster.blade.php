@extends('layouts.admin')

@section('title', 'Distributed Cluster Control Center')

@section('content')
<div class="cluster-dashboard-container" style="max-width:1200px; margin:0 auto; padding-bottom:3rem;">
    
    <!-- Top Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <h1 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#10b981; box-shadow:0 0 12px #10b981;"></span>
                    Cluster Control Center
                </h1>
                <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700; font-size:0.75rem; padding:3px 8px; border-radius:6px;">
                    Node: {{ $status['app']['node_id'] ?? 'primary' }}
                </span>
            </div>
            <p style="color:#64748b; font-size:0.9rem; margin:0.25rem 0 0 0;">
                ศูนย์กลางควบคุมและตรวจวัดสถานะ Real-time Topology, AI Load Balancer, Priority Queues และความมั่นคงปลอดภัย
            </p>
        </div>
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <button id="refreshBtn" onclick="fetchLiveMetrics()" class="btn btn-outline btn-sm" style="display:flex; align-items:center; gap:0.4rem; background:#fff;">
                <svg id="refreshIcon" style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>รีเฟรชข้อมูล</span>
            </button>
            <span id="lastUpdated" style="font-size:0.8rem; color:#94a3b8;">อัปเดตล่าสุด: {{ now()->format('H:i:s') }}</span>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
        
        <!-- App Engine Card -->
        <div class="metric-card" style="background:#fff; border-radius:12px; padding:1.25rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <span style="font-size:0.8rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Application Engine</span>
                <span class="badge" style="background:#dcfce7; color:#15803d; font-size:0.7rem; font-weight:700; border-radius:4px; padding:2px 6px;">
                    PHP {{ $status['app']['php_version'] }}
                </span>
            </div>
            <div style="font-size:1.3rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:0.5rem;">
                Laravel Octane
                <span style="font-size:0.8rem; font-weight:600; color:#6366f1; background:#e0e7ff; padding:1px 6px; border-radius:4px;">
                    {{ strtoupper($status['app']['octane_server'] ?? 'SWOOLE') }}
                </span>
            </div>
            <div style="font-size:0.8rem; color:#64748b; margin-top:0.35rem;">
                Env: <strong>{{ strtoupper($status['app']['env']) }}</strong> | Debug: <strong style="color:{{ $status['app']['debug'] ? '#ef4444' : '#10b981' }}">{{ $status['app']['debug'] ? 'ON (Warning)' : 'OFF (Safe)' }}</strong>
            </div>
        </div>

        <!-- Database Health Card -->
        <div class="metric-card" style="background:#fff; border-radius:12px; padding:1.25rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <span style="font-size:0.8rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Core Database</span>
                <span class="badge" id="dbStatusBadge" style="background:{{ ($status['database']['status'] ?? '') === 'HEALTHY' ? '#dcfce7' : '#fee2e2' }}; color:{{ ($status['database']['status'] ?? '') === 'HEALTHY' ? '#15803d' : '#b91c1c' }}; font-size:0.7rem; font-weight:700; border-radius:4px; padding:2px 6px;">
                    {{ $status['database']['status'] ?? 'UNKNOWN' }}
                </span>
            </div>
            <div style="font-size:1.3rem; font-weight:800; color:#0f172a;" id="dbLatency">
                {{ $status['database']['latency_ms'] ?? 0 }} <span style="font-size:0.85rem; font-weight:500; color:#64748b;">ms latency</span>
            </div>
            <div style="font-size:0.8rem; color:#64748b; margin-top:0.35rem;">
                PostgreSQL 16 (<span id="dbName">{{ $status['database']['database'] ?? 'uni_activity' }}</span>)
            </div>
        </div>

        <!-- Redis / Dragonfly Card -->
        <div class="metric-card" style="background:#fff; border-radius:12px; padding:1.25rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <span style="font-size:0.8rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Memory Cache & Queue</span>
                <span class="badge" id="redisStatusBadge" style="background:{{ in_array($status['redis']['status'] ?? '', ['HEALTHY', 'DEGRADED']) ? '#dcfce7' : '#fee2e2' }}; color:{{ in_array($status['redis']['status'] ?? '', ['HEALTHY', 'DEGRADED']) ? '#15803d' : '#b91c1c' }}; font-size:0.7rem; font-weight:700; border-radius:4px; padding:2px 6px;">
                    {{ $status['redis']['status'] ?? 'UNKNOWN' }}
                </span>
            </div>
            <div style="font-size:1.3rem; font-weight:800; color:#0f172a;" id="redisLatency">
                {{ $status['redis']['latency_ms'] ?? 0 }} <span style="font-size:0.85rem; font-weight:500; color:#64748b;">ms ping</span>
            </div>
            <div style="font-size:0.8rem; color:#64748b; margin-top:0.35rem;">
                Auth: <strong style="color:{{ !empty($status['redis']['auth_enabled']) ? '#10b981' : '#f59e0b' }}">{{ !empty($status['redis']['auth_enabled']) ? 'Enabled (requirepass)' : 'None' }}</strong>
            </div>
        </div>

        <!-- Security Posture Card -->
        <div class="metric-card" style="background:#fff; border-radius:12px; padding:1.25rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <span style="font-size:0.8rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Security Posture</span>
                <span class="badge" style="background:#dcfce7; color:#15803d; font-size:0.7rem; font-weight:700; border-radius:4px; padding:2px 6px;">
                    Grade {{ $status['security']['grade'] ?? 'A+' }}
                </span>
            </div>
            <div style="font-size:1.3rem; font-weight:800; color:#0f172a;">
                {{ $status['security']['score'] ?? 100 }} <span style="font-size:0.85rem; font-weight:500; color:#64748b;">/ 100 points</span>
            </div>
            <div style="font-size:0.8rem; color:#64748b; margin-top:0.35rem;">
                PDPA Biometric Encryption: <strong>ACTIVE</strong>
            </div>
        </div>
    </div>

    <!-- Distributed AI Face Recognition Cluster -->
    <div style="background:#fff; border-radius:12px; padding:1.5rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h3 style="font-size:1.1rem; font-weight:700; color:#0f172a; margin:0; display:flex; align-items:center; gap:0.5rem;">
                    <svg style="width:20px; height:20px; color:#6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Distributed AI Face Recognition Cluster (Dual-Node InsightFace)
                </h3>
                <p style="color:#64748b; font-size:0.85rem; margin:0.2rem 0 0 0;">
                    ระบบกระจายโหลดสแกนใบหน้าแบบ Round-Robin พร้อม Circuit Breaker แยกตามโหนดและการสลับโหนดอัตโนมัติ (Failover)
                </p>
            </div>
            <div id="aiClusterBadge">
                <span class="badge" style="background:{{ ($status['ai_cluster']['cluster_state'] ?? '') === 'HEALTHY' ? '#dcfce7' : '#fee2e2' }}; color:{{ ($status['ai_cluster']['cluster_state'] ?? '') === 'HEALTHY' ? '#15803d' : '#b91c1c' }}; font-weight:700; padding:4px 10px; border-radius:6px;">
                    Cluster: {{ $status['ai_cluster']['cluster_state'] ?? 'HEALTHY' }} ({{ $status['ai_cluster']['healthy_nodes'] ?? 0 }}/{{ $status['ai_cluster']['total_nodes'] ?? 0 }} Online)
                </span>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="table" style="width:100%; border-collapse:collapse;" id="aiNodesTable">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left; font-size:0.8rem; color:#64748b;">
                        <th style="padding:0.75rem 1rem;">Node ID</th>
                        <th style="padding:0.75rem 1rem;">IP & Endpoint</th>
                        <th style="padding:0.75rem 1rem;">Status</th>
                        <th style="padding:0.75rem 1rem;">Circuit Breaker</th>
                        <th style="padding:0.75rem 1rem;">Latency</th>
                        <th style="padding:0.75rem 1rem;">Loaded Models</th>
                    </tr>
                </thead>
                <tbody id="aiNodesBody">
                    @foreach($status['ai_cluster']['nodes'] ?? [] as $node)
                    <tr style="border-bottom:1px solid #f1f5f9; font-size:0.875rem;">
                        <td style="padding:0.75rem 1rem; font-weight:700; color:#0f172a;">
                            {{ $node['id'] }}
                        </td>
                        <td style="padding:0.75rem 1rem; font-family:monospace; color:#475569;">
                            {{ $node['url'] }}
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            @if($node['status'] === 'HEALTHY')
                                <span style="background:#dcfce7; color:#15803d; font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:4px;">● ONLINE</span>
                            @elseif($node['status'] === 'DEGRADED')
                                <span style="background:#fef3c7; color:#b45309; font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:4px;">▲ DEGRADED</span>
                            @else
                                <span style="background:#fee2e2; color:#b91c1c; font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:4px;">✖ OFFLINE</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <span style="font-size:0.75rem; font-weight:700; color:{{ $node['circuit_breaker'] === 'CLOSED' ? '#10b981' : '#ef4444' }};">
                                {{ $node['circuit_breaker'] === 'CLOSED' ? 'CLOSED (Normal)' : 'OPEN (Bypassed)' }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem; font-weight:600; color:#334155;">
                            {{ $node['latency_ms'] ?? '—' }} ms
                        </td>
                        <td style="padding:0.75rem 1rem; color:#64748b; font-size:0.8rem;">
                            {{ isset($node['models']) ? implode(', ', $node['models']) : ($node['error'] ?? 'N/A') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Redis Priority Queue Workers & WebSocket Streaming Grid -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:1.5rem;">
        
        <!-- Priority Queues -->
        <div style="background:#fff; border-radius:12px; padding:1.5rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="font-size:1.1rem; font-weight:700; color:#0f172a; margin:0 0 0.5rem 0; display:flex; align-items:center; gap:0.5rem;">
                <svg style="width:20px; height:20px; color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Redis Priority Queues Telemetry
            </h3>
            <p style="color:#64748b; font-size:0.85rem; margin-bottom:1rem;">
                ช่องทางการประมวลผลงานเบื้องหลังแยกตามระดับความสำคัญ
            </p>

            <div style="display:flex; flex-direction:column; gap:0.75rem;" id="queueChannelsList">
                @foreach($status['queues']['channels'] ?? [] as $channel => $count)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0.8rem; background:#f8fafc; border-radius:8px; border:1px solid #f1f5f9;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:8px; height:8px; border-radius:50%; background:{{ $channel === 'ai' ? '#6366f1' : ($channel === 'notifications' ? '#10b981' : '#64748b') }};"></span>
                        <strong style="color:#0f172a; font-size:0.875rem;">queue:{{ $channel }}</strong>
                        <span style="font-size:0.75rem; color:#64748b;">
                            ({{ $channel === 'ai' ? 'InsightFace Async' : ($channel === 'notifications' ? 'LINE Reminders' : 'Standard') }})
                        </span>
                    </div>
                    <div>
                        <span class="badge" style="background:{{ $count > 0 ? '#fef3c7' : '#f1f5f9' }}; color:{{ $count > 0 ? '#b45309' : '#64748b' }}; font-weight:700; font-size:0.8rem; padding:2px 8px; border-radius:999px;">
                            {{ $count }} pending
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Real-time WebSocket Gateway (Reverb) -->
        <div style="background:#fff; border-radius:12px; padding:1.5rem; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <h3 style="font-size:1.1rem; font-weight:700; color:#0f172a; margin:0 0 0.5rem 0; display:flex; align-items:center; gap:0.5rem;">
                <svg style="width:20px; height:20px; color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Real-time WebSocket Streaming
            </h3>
            <p style="color:#64748b; font-size:0.85rem; margin-bottom:1rem;">
                การกระจายข้อความแชทและการแจ้งเตือน Real-time Check-In
            </p>

            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                <div style="padding:0.75rem; background:#f8fafc; border-radius:8px; border:1px solid #f1f5f9;">
                    <div style="font-size:0.8rem; color:#64748b;">Broadcasting Engine</div>
                    <div style="font-weight:700; color:#0f172a; font-size:0.95rem;">Laravel Reverb (High-Throughput)</div>
                </div>
                <div style="padding:0.75rem; background:#f8fafc; border-radius:8px; border:1px solid #f1f5f9;">
                    <div style="font-size:0.8rem; color:#64748b;">WebSocket Endpoint</div>
                    <div style="font-weight:700; color:#0f172a; font-family:monospace; font-size:0.9rem;">
                        {{ $status['broadcasting']['scheme'] }}://{{ $status['broadcasting']['host'] }}:{{ $status['broadcasting']['port'] }}
                    </div>
                </div>
                <div style="padding:0.75rem; background:#f8fafc; border-radius:8px; border:1px solid #f1f5f9;">
                    <div style="font-size:0.8rem; color:#64748b;">Channel Security & Privacy</div>
                    <div style="font-weight:700; color:#10b981; font-size:0.875rem;">Strict Student-to-Student Thread Isolation</div>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    async function fetchLiveMetrics() {
        const icon = document.getElementById('refreshIcon');
        icon.style.transition = 'transform 0.5s ease';
        icon.style.transform = 'rotate(360deg)';

        try {
            const res = await fetch('{{ route("admin.api.cluster.metrics") }}');
            if (!res.ok) throw new Error('Network error');
            const result = await res.json();
            const data = result.data;

            // Update Database Latency
            if (data.database) {
                document.getElementById('dbLatency').innerHTML = `${data.database.latency_ms} <span style="font-size:0.85rem; font-weight:500; color:#64748b;">ms latency</span>`;
                const badge = document.getElementById('dbStatusBadge');
                badge.textContent = data.database.status;
                badge.style.background = data.database.status === 'HEALTHY' ? '#dcfce7' : '#fee2e2';
                badge.style.color = data.database.status === 'HEALTHY' ? '#15803d' : '#b91c1c';
            }

            // Update Redis Latency
            if (data.redis) {
                document.getElementById('redisLatency').innerHTML = `${data.redis.latency_ms} <span style="font-size:0.85rem; font-weight:500; color:#64748b;">ms ping</span>`;
            }

            // Update AI Nodes Table
            if (data.ai_cluster && data.ai_cluster.nodes) {
                const tbody = document.getElementById('aiNodesBody');
                tbody.innerHTML = data.ai_cluster.nodes.map(node => `
                    <tr style="border-bottom:1px solid #f1f5f9; font-size:0.875rem;">
                        <td style="padding:0.75rem 1rem; font-weight:700; color:#0f172a;">${node.id}</td>
                        <td style="padding:0.75rem 1rem; font-family:monospace; color:#475569;">${node.url}</td>
                        <td style="padding:0.75rem 1rem;">
                            <span style="background:${node.status === 'HEALTHY' ? '#dcfce7' : '#fee2e2'}; color:${node.status === 'HEALTHY' ? '#15803d' : '#b91c1c'}; font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:4px;">
                                ● ${node.status}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <span style="font-size:0.75rem; font-weight:700; color:${node.circuit_breaker === 'CLOSED' ? '#10b981' : '#ef4444'};">
                                ${node.circuit_breaker === 'CLOSED' ? 'CLOSED (Normal)' : 'OPEN (Bypassed)'}
                            </span>
                        </td>
                        <td style="padding:0.75rem 1rem; font-weight:600; color:#334155;">${node.latency_ms || '—'} ms</td>
                        <td style="padding:0.75rem 1rem; color:#64748b; font-size:0.8rem;">
                            ${node.models ? node.models.join(', ') : (node.error || 'N/A')}
                        </td>
                    </tr>
                `).join('');
            }

            document.getElementById('lastUpdated').textContent = 'อัปเดตล่าสุด: ' + new Date().toLocaleTimeString();
        } catch (e) {
            console.error('Failed to fetch live cluster metrics', e);
        } finally {
            setTimeout(() => {
                icon.style.transform = 'none';
            }, 500);
        }
    }

    // Auto refresh every 8 seconds
    setInterval(fetchLiveMetrics, 8000);
</script>
@endsection
