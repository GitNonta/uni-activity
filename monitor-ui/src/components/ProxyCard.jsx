import React, { useState, useEffect } from 'react';

const STATUS_COLORS = {
  Running: { bg: '#dcfce7', color: '#15803d', border: '#bbf7d0', dot: '#10b981' },
  Stopped: { bg: '#fee2e2', color: '#b91c1c', border: '#fecaca', dot: '#ef4444' },
  Warning: { bg: '#fef3c7', color: '#b45309', border: '#fde68a', dot: '#f59e0b' },
};

function StatusBadge({ status }) {
  const s = STATUS_COLORS[status] || STATUS_COLORS.Stopped;
  return (
    <span style={{
      background: s.bg, color: s.color, border: `1px solid ${s.border}`,
      fontSize: '0.7rem', fontWeight: 700, padding: '0.15rem 0.5rem', borderRadius: 4,
      display: 'inline-flex', alignItems: 'center', gap: '0.3rem'
    }}>
      <span style={{ width: 6, height: 6, borderRadius: '50%', background: s.dot }}></span>
      {status}
    </span>
  );
}

function ServiceCard({ title, subtitle, status, port, connections, extra, icon }) {
  const s = STATUS_COLORS[status] || STATUS_COLORS.Stopped;
  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)',
      borderLeft: `4px solid ${s.dot}`
    }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <div style={{
            width: 36, height: 36, borderRadius: 10, background: s.bg, color: s.color,
            display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0
          }}>
            {icon}
          </div>
          <div>
            <div style={{ fontWeight: 700, fontSize: '0.95rem', color: '#0f172a' }}>{title}</div>
            <div style={{ fontSize: '0.75rem', color: '#94a3b8' }}>{subtitle}</div>
          </div>
        </div>
        <StatusBadge status={status} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', marginBottom: '0.75rem' }}>
        <div style={{ background: '#f8fafc', border: '1px solid #f1f5f9', padding: '0.5rem 0.75rem', borderRadius: 8, fontSize: '0.8rem' }}>
          <div style={{ color: '#94a3b8', fontSize: '0.7rem' }}>Port</div>
          <strong style={{ color: '#0f172a', fontFamily: 'monospace' }}>{port}</strong>
        </div>
        <div style={{ background: '#f8fafc', border: '1px solid #f1f5f9', padding: '0.5rem 0.75rem', borderRadius: 8, fontSize: '0.8rem' }}>
          <div style={{ color: '#94a3b8', fontSize: '0.7rem' }}>Active Connections</div>
          <strong style={{ color: connections > 0 ? '#ea580c' : '#0f172a' }}>{connections}</strong>
        </div>
      </div>

      {extra && <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '0.25rem' }}>{extra}</div>}
    </div>
  );
}

function TrafficDashboard({ traffic }) {
  if (!traffic) return null;
  const t = traffic;

  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#2563eb" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
        Traffic & Throughput
      </h3>

      {/* KPI Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
        <div style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#0369a1', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Requests/sec</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#0c4a6e', margin: '0.25rem 0' }}>{t.rps}</div>
          <div style={{ fontSize: '0.7rem', color: '#0369a1' }}>RPS (last 60s)</div>
        </div>

        <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#15803d', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Total Data</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#14532d', margin: '0.25rem 0' }}>{t.total_bytes_human}</div>
          <div style={{ fontSize: '0.7rem', color: '#15803d' }}>All interfaces</div>
        </div>

        <div style={{ background: '#fefce8', border: '1px solid #fde68a', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#a16207', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Cache Hit Ratio</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#713f12', margin: '0.25rem 0' }}>{t.cache_hit_ratio}%</div>
          <div style={{ fontSize: '0.7rem', color: '#a16207' }}>RAM cache efficiency</div>
        </div>

        <div style={{ background: t.recent_errors > 0 ? '#fef2f2' : '#f0fdf4', border: `1px solid ${t.recent_errors > 0 ? '#fecaca' : '#bbf7d0'}`, borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: t.recent_errors > 0 ? '#b91c1c' : '#15803d', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Errors (60s)</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: t.recent_errors > 0 ? '#991b1b' : '#14532d', margin: '0.25rem 0' }}>{t.recent_errors}</div>
          <div style={{ fontSize: '0.7rem', color: t.recent_errors > 0 ? '#b91c1c' : '#15803d' }}>{t.recent_errors > 0 ? 'DENIED/ERR' : 'All OK'}</div>
        </div>
      </div>

      {/* Top Domains */}
      {t.top_domains && t.top_domains.length > 0 && (
        <div>
          <h4 style={{ margin: '0 0 0.5rem 0', fontSize: '0.85rem', fontWeight: 700, color: '#374151' }}>Top Domains (last 60s)</h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
            {t.top_domains.map((d, i) => (
              <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.4rem 0.6rem', background: '#f8fafc', borderRadius: 6, border: '1px solid #f1f5f9' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <span style={{ fontSize: '0.65rem', fontWeight: 700, color: '#94a3b8', width: 20, textAlign: 'right' }}>#{i + 1}</span>
                  <span style={{ fontSize: '0.8rem', fontFamily: 'monospace', color: '#334155' }}>{d.domain}</span>
                </div>
                <span style={{ fontSize: '0.75rem', fontWeight: 700, color: '#2563eb', background: '#eff6ff', padding: '0.1rem 0.4rem', borderRadius: 4 }}>{d.count}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function WorkerGrid({ workers }) {
  if (!workers || workers.length === 0) return null;
  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#ea580c" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
        </svg>
        PHP Workers Load Balance (7 Workers)
      </h3>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))', gap: '0.5rem' }}>
        {workers.map((w, i) => {
          const isUp = w.status === '200' || w.status === '302';
          const isDown = w.status === '000' || w.status === '' || w.status === '502' || w.status === '503';
          return (
            <div key={i} style={{
              background: isUp ? '#f0fdf4' : (isDown ? '#fef2f2' : '#fffbeb'),
              border: `1px solid ${isUp ? '#bbf7d0' : (isDown ? '#fecaca' : '#fde68a')}`,
              borderRadius: 10, padding: '0.75rem', textAlign: 'center'
            }}>
              <div style={{
                width: 10, height: 10, borderRadius: '50%', margin: '0 auto 0.35rem',
                background: isUp ? '#10b981' : (isDown ? '#ef4444' : '#f59e0b'),
                boxShadow: `0 0 8px ${isUp ? '#10b981' : (isDown ? '#ef4444' : '#f59e0b')}`
              }}></div>
              <div style={{ fontSize: '0.7rem', fontWeight: 600, color: '#64748b' }}>{w.phone}</div>
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: '#0f172a', fontFamily: 'monospace' }}>:{w.port}</div>
              <div style={{
                fontSize: '0.65rem', fontWeight: 700, marginTop: '0.25rem',
                color: isUp ? '#15803d' : (isDown ? '#b91c1c' : '#b45309')
              }}>
                {w.status || 'N/A'}
              </div>
            </div>
          );
        })}
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '0.75rem', fontSize: '0.75rem', color: '#64748b' }}>
        <span>Phone 1: {workers.filter(w => w.phone === 'P1').length} workers</span>
        <span>Phone 2: {workers.filter(w => w.phone === 'P2').length} workers</span>
        <span>Healthy: {workers.filter(w => w.status === '200' || w.status === '302').length}/{workers.length}</span>
      </div>
    </div>
  );
}

function EgressSecurity({ blockedPorts }) {
  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 0.75rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#ef4444" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        Egress Security (Zero Trust)
      </h3>
      <p style={{ margin: '0 0 0.75rem 0', color: '#64748b', fontSize: '0.8rem' }}>
        Squid blocks outgoing connections to dangerous ports to prevent data exfiltration.
      </p>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.35rem' }}>
        {(blockedPorts || [7, 9, 19, 22, 23, 25, 110, 111, 135, 139, 445, 512, 513, 514, 515]).map(port => (
          <span key={port} style={{
            background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca',
            fontSize: '0.7rem', fontWeight: 700, padding: '0.15rem 0.45rem', borderRadius: 4,
            fontFamily: 'monospace'
          }}>
            :{port}
          </span>
        ))}
      </div>
      <div style={{ marginTop: '0.75rem', padding: '0.5rem 0.75rem', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 8, fontSize: '0.75rem', color: '#15803d' }}>
        <strong>✅ LAN clients</strong> can access all HTTP/HTTPS sites — only dangerous ports are blocked.
      </div>
    </div>
  );
}

function TopologyDiagram({ proxy }) {
  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        Proxy Network Topology
      </h3>

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.5rem', overflowX: 'auto', padding: '0.5rem 0' }}>
        {/* iPad / Devices */}
        <div style={{ flex: 1, minWidth: 100, background: '#faf5ff', border: '1px solid #e9d5ff', borderRadius: 12, padding: '0.75rem', textAlign: 'center' }}>
          <div style={{ fontSize: '1.5rem' }}>📱</div>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#7c3aed' }}>iPad / PC</div>
          <div style={{ fontSize: '0.65rem', color: '#94a3b8' }}>LAN Devices</div>
        </div>

        <svg width="20" height="20" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>

        {/* Phone 1 */}
        <div style={{ flex: 1.5, minWidth: 140, background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 12, padding: '0.75rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#15803d', textTransform: 'uppercase' }}>Phone 1 (192.168.1.222)</div>
          <div style={{ fontSize: '0.85rem', fontWeight: 800, color: '#064e3b', margin: '0.25rem 0' }}>Squid :3128</div>
          <div style={{ display: 'flex', justifyContent: 'center', gap: '0.35rem', flexWrap: 'wrap' }}>
            <span style={{ fontSize: '0.6rem', background: '#dcfce7', color: '#15803d', padding: '0.1rem 0.35rem', borderRadius: 4, fontWeight: 600 }}>HTTP</span>
            <span style={{ fontSize: '0.6rem', background: '#dbeafe', color: '#2563eb', padding: '0.1rem 0.35rem', borderRadius: 4, fontWeight: 600 }}>SOCKS5 :1080</span>
            <span style={{ fontSize: '0.6rem', background: '#fef3c7', color: '#b45309', padding: '0.1rem 0.35rem', borderRadius: 4, fontWeight: 600 }}>Nginx LB</span>
          </div>
        </div>

        <svg width="20" height="20" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>

        {/* Cloudflare */}
        <div style={{ flex: 1, minWidth: 100, background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 12, padding: '0.75rem', textAlign: 'center' }}>
          <div style={{ fontSize: '1.5rem' }}>☁️</div>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#c2410c' }}>Cloudflare</div>
          <div style={{ fontSize: '0.65rem', color: '#94a3b8' }}>HTTPS Tunnel</div>
        </div>

        <svg width="20" height="20" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>

        {/* Internet */}
        <div style={{ flex: 1, minWidth: 100, background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 12, padding: '0.75rem', textAlign: 'center' }}>
          <div style={{ fontSize: '1.5rem' }}>🌐</div>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#0369a1' }}>Internet</div>
          <div style={{ fontSize: '0.65rem', color: '#94a3b8' }}>{proxy?.public_ip || 'N/A'}</div>
        </div>
      </div>

      <div style={{ marginTop: '0.75rem', display: 'flex', justifyContent: 'center', gap: '1rem', fontSize: '0.7rem', color: '#64748b' }}>
        <span>🔒 Squid: Whitelist + Cache</span>
        <span>⚡ SOCKS5: Direct Tunnel</span>
        <span>⚖️ Nginx: Load Balance</span>
      </div>
    </div>
  );
}

export function ProxyCard({ proxy }) {
  if (!proxy) {
    return (
      <div style={{ padding: '2rem', textAlign: 'center', color: '#94a3b8' }}>
        <div style={{ fontSize: '1.5rem', marginBottom: '0.5rem' }}>⏳</div>
        Waiting for proxy data...
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem', width: '100%' }}>
      {/* Header */}
      <div>
        <h2 style={{ margin: 0, fontSize: '1.5rem', fontWeight: 800, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.6rem' }}>
          <span style={{
            display: 'inline-block', width: 10, height: 10, borderRadius: '50%',
            background: proxy.squid?.status === 'Running' ? '#10b981' : '#f59e0b',
            boxShadow: proxy.squid?.status === 'Running' ? '0 0 10px #10b981' : '0 0 10px #f59e0b'
          }}></span>
          ระบบ Proxy สำหรับ LAN
          <span style={{ fontSize: '1.05rem', fontWeight: 500, color: '#64748b' }}>Proxy Management</span>
        </h2>
        <p style={{ margin: '0.35rem 0 0 0', color: '#64748b', fontSize: '0.875rem' }}>
          Squid HTTP Proxy + Python SOCKS5 + Nginx Load Balancer — ให้บริการ iPad, Computer, และอุปกรณ์ LAN ทั้งหมด
        </p>
      </div>

      {/* Topology */}
      <TopologyDiagram proxy={proxy} />

      {/* Service Cards Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
        <ServiceCard
          title="Squid HTTP Proxy"
          subtitle="HTTP/HTTPS proxy with caching & egress security"
          status={proxy.squid?.status || 'Stopped'}
          port={proxy.squid?.port || 3128}
          connections={proxy.squid?.connections || 0}
          icon={<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>}
          extra={
            <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
              <span style={{ fontSize: '0.65rem', background: '#f1f5f9', padding: '0.1rem 0.35rem', borderRadius: 4, color: '#475569' }}>
                Cache: {proxy.squid?.cache_hits || 0} hits / {proxy.squid?.cache_misses || 0} misses
              </span>
              <span style={{ fontSize: '0.65rem', background: '#f1f5f9', padding: '0.1rem 0.35rem', borderRadius: 4, color: '#475569' }}>
                Allowed: {proxy.squid?.allowed_domains || 0} domains
              </span>
            </div>
          }
        />

        <ServiceCard
          title="Python SOCKS5 Proxy"
          subtitle="SOCKS5 tunnel for browser & app proxy"
          status={proxy.socks5?.status || 'Stopped'}
          port={proxy.socks5?.port || 1080}
          connections={proxy.socks5?.connections || 0}
          icon={<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>}
          extra={<span style={{ fontSize: '0.65rem', background: '#f1f5f9', padding: '0.1rem 0.35rem', borderRadius: 4, color: '#475569' }}>Threaded · Auto-restart via crontab</span>}
        />

        <ServiceCard
          title="Nginx Load Balancer"
          subtitle="Round-robin across 7 PHP workers"
          status={proxy.nginx_lb?.status || 'Stopped'}
          port={proxy.nginx_lb?.port || 8088}
          connections={proxy.nginx_lb?.connections || 0}
          icon={<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>}
          extra={
            <span style={{
              fontSize: '0.65rem', background: (proxy.nginx_lb?.down_markers || 0) > 0 ? '#fef3c7' : '#dcfce7',
              color: (proxy.nginx_lb?.down_markers || 0) > 0 ? '#b45309' : '#15803d',
              padding: '0.1rem 0.35rem', borderRadius: 4, fontWeight: 600
            }}>
              Down markers: {proxy.nginx_lb?.down_markers || 0}
            </span>
          }
        />

        <ServiceCard
          title="Cloudflare Tunnel"
          subtitle="HTTPS ingress from internet"
          status={proxy.cloudflared?.status || 'Stopped'}
          port={proxy.cloudflared?.port || 8080}
          connections={0}
          icon={<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" /></svg>}
          extra={<span style={{ fontSize: '0.65rem', background: '#f1f5f9', padding: '0.1rem 0.35rem', borderRadius: 4, color: '#475569' }}>Public IP: {proxy.public_ip || 'N/A'}</span>}
        />
      </div>

      {/* Traffic Dashboard */}
      <TrafficDashboard traffic={proxy.traffic} />

      {/* Worker Grid */}
      <WorkerGrid workers={proxy.workers} />

      {/* Egress Security */}
      <EgressSecurity blockedPorts={proxy.nginx_lb?.blocked_ports} />
    </div>
  );
}
