import React, { useState, useEffect, useMemo } from 'react';

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

function LiveProxyTraffic({ recentTraffic = [], deviceBreakdown = [], traffic = {} }) {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedDevice, setSelectedDevice] = useState('ALL');
  const [statusFilter, setStatusFilter] = useState('ALL');
  const [isPaused, setIsPaused] = useState(false);
  const [displayedLogs, setDisplayedLogs] = useState([]);

  useEffect(() => {
    if (!isPaused && recentTraffic && recentTraffic.length > 0) {
      setDisplayedLogs(recentTraffic);
    }
  }, [recentTraffic, isPaused]);

  const filteredLogs = useMemo(() => {
    return displayedLogs.filter(item => {
      // Device filter
      if (selectedDevice !== 'ALL' && item.client_ip !== selectedDevice) {
        return false;
      }
      // Status filter
      if (statusFilter !== 'ALL') {
        if (statusFilter === 'blocked' && item.status_type !== 'blocked') return false;
        if (statusFilter === 'cached' && item.status_type !== 'cached') return false;
        if (statusFilter === 'slow' && item.status_type !== 'slow') return false;
        if (statusFilter === 'success' && !['tunnel', 'cached', 'success'].includes(item.status_type)) return false;
      }
      // Search term
      if (searchTerm.trim()) {
        const q = searchTerm.toLowerCase();
        const matchDomain = item.domain?.toLowerCase().includes(q);
        const matchDest = item.destination?.toLowerCase().includes(q);
        const matchIp = item.client_ip?.toLowerCase().includes(q);
        const matchDev = item.device?.toLowerCase().includes(q);
        const matchStatus = item.status?.toLowerCase().includes(q);
        if (!matchDomain && !matchDest && !matchIp && !matchDev && !matchStatus) return false;
      }
      return true;
    });
  }, [displayedLogs, selectedDevice, statusFilter, searchTerm]);

  const getStatusBadge = (status, statusType, durationMs) => {
    if (statusType === 'blocked') {
      return (
        <span style={{
          background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca',
          fontSize: '0.7rem', fontWeight: 700, padding: '0.15rem 0.45rem', borderRadius: 4,
          display: 'inline-flex', alignItems: 'center', gap: '0.3rem'
        }}>
          <span style={{ width: 5, height: 5, borderRadius: '50%', background: '#ef4444' }}></span>
          {status}
        </span>
      );
    }
    if (statusType === 'cached') {
      return (
        <span style={{
          background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0',
          fontSize: '0.7rem', fontWeight: 700, padding: '0.15rem 0.45rem', borderRadius: 4,
          display: 'inline-flex', alignItems: 'center', gap: '0.3rem'
        }}>
          <span style={{ width: 5, height: 5, borderRadius: '50%', background: '#10b981' }}></span>
          {status}
        </span>
      );
    }
    if (durationMs > 4000) {
      return (
        <span style={{
          background: '#fffbeb', color: '#b45309', border: '1px solid #fde68a',
          fontSize: '0.7rem', fontWeight: 700, padding: '0.15rem 0.45rem', borderRadius: 4,
          display: 'inline-flex', alignItems: 'center', gap: '0.3rem'
        }}>
          <span style={{ width: 5, height: 5, borderRadius: '50%', background: '#f59e0b' }}></span>
          {status}
        </span>
      );
    }
    return (
      <span style={{
        background: '#eff6ff', color: '#1d4ed8', border: '1px solid #bfdbfe',
        fontSize: '0.7rem', fontWeight: 700, padding: '0.15rem 0.45rem', borderRadius: 4,
        display: 'inline-flex', alignItems: 'center', gap: '0.3rem'
      }}>
        <span style={{ width: 5, height: 5, borderRadius: '50%', background: '#3b82f6' }}></span>
        {status}
      </span>
    );
  };

  const getDeviceIcon = (device) => {
    if (device.includes('Mobile') || device.includes('Phone')) {
      return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
          <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
          <line x1="12" y1="18" x2="12.01" y2="18" />
        </svg>
      );
    }
    if (device.includes('iPad') || device.includes('Tablet')) {
      return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
          <rect x="4" y="2" width="16" height="20" rx="2" ry="2" />
          <line x1="12" y1="18" x2="12.01" y2="18" />
        </svg>
      );
    }
    if (device.includes('PC') || device.includes('Computer')) {
      return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
          <line x1="8" y1="21" x2="16" y2="21" />
          <line x1="12" y1="17" x2="12" y2="21" />
        </svg>
      );
    }
    if (device.includes('Server')) {
      return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
          <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
          <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
          <line x1="6" y1="6" x2="6.01" y2="6" />
          <line x1="6" y1="18" x2="6.01" y2="18" />
        </svg>
      );
    }
    return (
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
        <rect x="4" y="4" width="16" height="16" rx="2" />
        <rect x="9" y="9" width="6" height="6" />
        <line x1="9" y1="1" x2="9" y2="4" />
        <line x1="15" y1="1" x2="15" y2="4" />
        <line x1="9" y1="20" x2="9" y2="23" />
        <line x1="15" y1="20" x2="15" y2="23" />
        <line x1="20" y1="9" x2="23" y2="9" />
        <line x1="20" y1="14" x2="23" y2="14" />
        <line x1="1" y1="9" x2="4" y2="9" />
        <line x1="1" y1="14" x2="4" y2="14" />
      </svg>
    );
  };

  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)',
      display: 'flex', flexDirection: 'column', gap: '1rem'
    }}>
      {/* Header & Controls */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.75rem' }}>
        <div>
          <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 800, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <span style={{ position: 'relative', display: 'flex', height: 10, width: 10 }}>
              {!isPaused && (
                <span style={{
                  position: 'absolute', display: 'inline-flex', height: '100%', width: '100%',
                  borderRadius: '50%', background: '#10b981', opacity: 0.75,
                  animation: 'ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite'
                }}></span>
              )}
              <span style={{ position: 'relative', display: 'inline-flex', borderRadius: '50%', height: 10, width: 10, background: isPaused ? '#f59e0b' : '#10b981' }}></span>
            </span>
            ทราฟฟิกการใช้พร็อกซีสด (Live Proxy Traffic Stream)
          </h3>
          <p style={{ margin: '0.25rem 0 0 0', fontSize: '0.8rem', color: '#64748b' }}>
            ติดตามการเชื่อมต่อและโดเมนที่อุปกรณ์ใน LAN เรียกใช้งานผ่าน Squid & SOCKS5 แบบ Real-time
          </p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <button
            onClick={() => setIsPaused(!isPaused)}
            style={{
              display: 'inline-flex', alignItems: 'center', gap: '0.4rem',
              padding: '0.45rem 0.85rem', borderRadius: 8, fontSize: '0.8rem', fontWeight: 600,
              cursor: 'pointer', border: '1px solid',
              background: isPaused ? '#ecfdf5' : '#fef3c7',
              borderColor: isPaused ? '#a7f3d0' : '#fde68a',
              color: isPaused ? '#065f46' : '#92400e',
              transition: 'all 0.15s'
            }}
          >
            {isPaused ? (
              <>
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
                เล่นต่อ (Resume Stream)
              </>
            ) : (
              <>
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" /></svg>
                หยุดชั่วคราว (Pause)
              </>
            )}
          </button>
          <span style={{
            fontSize: '0.75rem', fontWeight: 700, background: '#f1f5f9', color: '#475569',
            padding: '0.45rem 0.75rem', borderRadius: 8, border: '1px solid #e2e8f0'
          }}>
            {filteredLogs.length} รายการ
          </span>
        </div>
      </div>

      {/* Device Breakdown Mini Cards */}
      {deviceBreakdown && deviceBreakdown.length > 0 && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.65rem' }}>
          {deviceBreakdown.slice(0, 5).map((dev, i) => (
            <div
              key={i}
              onClick={() => setSelectedDevice(selectedDevice === dev.ip ? 'ALL' : dev.ip)}
              style={{
                background: selectedDevice === dev.ip ? '#eff6ff' : '#f8fafc',
                border: `1px solid ${selectedDevice === dev.ip ? '#60a5fa' : '#e2e8f0'}`,
                borderRadius: 10, padding: '0.75rem', cursor: 'pointer',
                transition: 'all 0.15s', position: 'relative'
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.35rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.45rem' }}>
                  <span style={{ display: 'inline-flex', alignItems: 'center', color: selectedDevice === dev.ip ? '#2563eb' : '#64748b' }}>
                    {getDeviceIcon(dev.device)}
                  </span>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: '0.82rem', color: '#0f172a' }}>{dev.device}</div>
                    <div style={{ fontSize: '0.68rem', fontFamily: 'monospace', color: '#64748b' }}>{dev.ip}</div>
                  </div>
                </div>
                <span style={{
                  width: 8, height: 8, borderRadius: '50%',
                  background: dev.active ? '#10b981' : '#cbd5e1',
                  boxShadow: dev.active ? '0 0 6px #10b981' : 'none'
                }}></span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.75rem', marginTop: '0.4rem', borderTop: '1px dashed #e2e8f0', paddingTop: '0.4rem' }}>
                <span style={{ color: '#64748b' }}>{dev.requests} คำขอ</span>
                <strong style={{ color: '#2563eb' }}>{dev.bytes_human}</strong>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Filters & Search Row */}
      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', alignItems: 'center', background: '#f8fafc', padding: '0.75rem', borderRadius: 10, border: '1px solid #e2e8f0' }}>
        {/* Search Input */}
        <div style={{ flex: '1 1 200px', minWidth: 180, position: 'relative' }}>
          <input
            type="text"
            placeholder="ค้นหาโดเมน, IP, หรือคำขอ (เช่น github, google)..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={{
              width: '100%', padding: '0.45rem 0.75rem', borderRadius: 7, border: '1px solid #cbd5e1',
              fontSize: '0.8rem', background: '#fff', outline: 'none'
            }}
          />
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              style={{
                position: 'absolute', right: 8, top: '50%', transform: 'translateY(-50%)',
                background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8',
                display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 2
              }}
              title="ล้างคำค้นหา"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          )}
        </div>

        {/* Device Filter */}
        <div style={{ display: 'flex', gap: '0.3rem', alignItems: 'center', flexWrap: 'wrap' }}>
          <span style={{ fontSize: '0.75rem', fontWeight: 600, color: '#64748b', marginRight: '0.2rem' }}>อุปกรณ์:</span>
          <button
            onClick={() => setSelectedDevice('ALL')}
            style={{
              padding: '0.3rem 0.6rem', borderRadius: 6, fontSize: '0.75rem', fontWeight: 600, cursor: 'pointer',
              border: '1px solid',
              background: selectedDevice === 'ALL' ? '#0f172a' : '#fff',
              borderColor: selectedDevice === 'ALL' ? '#0f172a' : '#cbd5e1',
              color: selectedDevice === 'ALL' ? '#fff' : '#475569'
            }}
          >
            ทั้งหมด
          </button>
          {deviceBreakdown.map((dev, i) => (
            <button
              key={i}
              onClick={() => setSelectedDevice(dev.ip)}
              style={{
                padding: '0.3rem 0.6rem', borderRadius: 6, fontSize: '0.75rem', fontWeight: 600, cursor: 'pointer',
                border: '1px solid',
                background: selectedDevice === dev.ip ? '#2563eb' : '#fff',
                borderColor: selectedDevice === dev.ip ? '#2563eb' : '#cbd5e1',
                color: selectedDevice === dev.ip ? '#fff' : '#475569'
              }}
            >
              {dev.device.split(' ')[0]} ({dev.ip.split('.').slice(-1)[0]})
            </button>
          ))}
        </div>

        {/* Status Filter */}
        <div style={{ display: 'flex', gap: '0.3rem', alignItems: 'center', marginLeft: 'auto', flexWrap: 'wrap' }}>
          <span style={{ fontSize: '0.75rem', fontWeight: 600, color: '#64748b', marginRight: '0.2rem' }}>สถานะ:</span>
          {[
            { id: 'ALL', label: 'ทั้งหมด' },
            { id: 'success', label: 'ผ่าน (OK)' },
            { id: 'cached', label: 'แคช (HIT)' },
            { id: 'slow', label: 'ช้า (>4s)' },
            { id: 'blocked', label: 'บล็อก (DENIED)' }
          ].map(st => (
            <button
              key={st.id}
              onClick={() => setStatusFilter(st.id)}
              style={{
                padding: '0.3rem 0.55rem', borderRadius: 6, fontSize: '0.72rem', fontWeight: 600, cursor: 'pointer',
                border: '1px solid',
                background: statusFilter === st.id ? '#0f172a' : '#fff',
                borderColor: statusFilter === st.id ? '#0f172a' : '#cbd5e1',
                color: statusFilter === st.id ? '#fff' : '#475569'
              }}
            >
              {st.label}
            </button>
          ))}
        </div>
      </div>

      {/* Traffic Table */}
      <div style={{ border: '1px solid #e2e8f0', borderRadius: 10, overflow: 'hidden' }}>
        <div style={{ overflowX: 'auto', maxHeight: '420px', overflowY: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.78rem' }}>
            <thead style={{ background: '#f8fafc', position: 'sticky', top: 0, zIndex: 2, borderBottom: '1px solid #e2e8f0' }}>
              <tr>
                <th style={{ padding: '0.65rem 0.8rem', color: '#475569', fontWeight: 700, width: 85 }}>เวลา</th>
                <th style={{ padding: '0.65rem 0.8rem', color: '#475569', fontWeight: 700, width: 140 }}>อุปกรณ์ (Client)</th>
                <th style={{ padding: '0.65rem 0.8rem', color: '#475569', fontWeight: 700, width: 90 }}>โปรโตคอล</th>
                <th style={{ padding: '0.65rem 0.8rem', color: '#475569', fontWeight: 700 }}>เป้าหมาย (Destination Domain)</th>
                <th style={{ padding: '0.65rem 0.8rem', color: '#475569', fontWeight: 700, width: 140 }}>สถานะ</th>
                <th style={{ padding: '0.65rem 0.8rem', color: '#475569', fontWeight: 700, textAlign: 'right', width: 90 }}>ขนาด</th>
                <th style={{ padding: '0.65rem 0.8rem', color: '#475569', fontWeight: 700, textAlign: 'right', width: 85 }}>ความเร็ว</th>
              </tr>
            </thead>
            <tbody>
              {filteredLogs.length > 0 ? (
                filteredLogs.map((item, idx) => (
                  <tr
                    key={item.id || idx}
                    style={{
                      borderBottom: '1px solid #f1f5f9',
                      background: idx % 2 === 0 ? '#fff' : '#fafafa',
                      transition: 'background 0.1s'
                    }}
                  >
                    <td style={{ padding: '0.55rem 0.8rem', fontFamily: 'monospace', color: '#64748b' }}>
                      {item.time}
                    </td>
                    <td style={{ padding: '0.55rem 0.8rem' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.45rem' }}>
                        <span style={{ display: 'inline-flex', alignItems: 'center', color: '#64748b' }}>
                          {getDeviceIcon(item.device)}
                        </span>
                        <div>
                          <div style={{ fontWeight: 600, color: '#0f172a' }}>{item.device}</div>
                          <div style={{ fontSize: '0.65rem', fontFamily: 'monospace', color: '#94a3b8' }}>{item.client_ip}</div>
                        </div>
                      </div>
                    </td>
                    <td style={{ padding: '0.55rem 0.8rem' }}>
                      <span style={{
                        fontSize: '0.68rem', fontWeight: 700, padding: '0.12rem 0.4rem', borderRadius: 4,
                        background: item.protocol === 'SOCKS5' ? '#faf5ff' : '#f1f5f9',
                        color: item.protocol === 'SOCKS5' ? '#7c3aed' : '#334155',
                        border: `1px solid ${item.protocol === 'SOCKS5' ? '#e9d5ff' : '#e2e8f0'}`
                      }}>
                        {item.protocol} {item.method}
                      </span>
                    </td>
                    <td style={{ padding: '0.55rem 0.8rem' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                        <span style={{
                          fontFamily: 'monospace', fontWeight: 600,
                          color: item.domain.includes('github') ? '#0969da' : (item.domain.includes('line') ? '#06c755' : '#0f172a'),
                          maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap'
                        }}>
                          {item.domain}
                        </span>
                        {item.port && item.port !== '443' && (
                          <span style={{ fontSize: '0.65rem', color: '#94a3b8', fontFamily: 'monospace' }}>:{item.port}</span>
                        )}
                      </div>
                      {item.remote_ip && item.remote_ip !== '-' && (
                        <div style={{ fontSize: '0.65rem', color: '#94a3b8', fontFamily: 'monospace' }}>IP: {item.remote_ip}</div>
                      )}
                    </td>
                    <td style={{ padding: '0.55rem 0.8rem' }}>
                      {getStatusBadge(item.status, item.status_type, item.duration_ms)}
                    </td>
                    <td style={{ padding: '0.55rem 0.8rem', textAlign: 'right', fontWeight: 600, color: '#334155', fontFamily: 'monospace' }}>
                      {item.bytes_human}
                    </td>
                    <td style={{
                      padding: '0.55rem 0.8rem', textAlign: 'right', fontWeight: 600,
                      fontFamily: 'monospace',
                      color: item.duration_ms > 4000 ? '#b45309' : (item.duration_ms > 1000 ? '#475569' : '#15803d')
                    }}>
                      {item.duration_ms > 0 ? (item.duration_ms >= 1000 ? `${(item.duration_ms / 1000).toFixed(1)}s` : `${item.duration_ms}ms`) : '—'}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={7} style={{ padding: '2.5rem', textAlign: 'center', color: '#94a3b8' }}>
                    <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '0.6rem' }}>
                      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                      </svg>
                    </div>
                    ไม่พบข้อมูลทราฟฟิกตามเงื่อนไขที่เลือก (หรือยังไม่มีคำขอใหม่วิ่งผ่าน Proxy)
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
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
        <strong>LAN clients</strong> can access all HTTP/HTTPS sites — only dangerous ports are blocked.
      </div>
    </div>
  );
}

function ConnectionDashboard({ connections }) {
  if (!connections) return null;
  const c = connections;

  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#7c3aed" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        Connection & Concurrency
      </h3>

      {/* Connection KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
        <div style={{ background: '#faf5ff', border: '1px solid #e9d5ff', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#7c3aed', textTransform: 'uppercase' }}>Squid HTTP</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#581c87', margin: '0.25rem 0' }}>{c.squid_active}</div>
          <div style={{ fontSize: '0.7rem', color: '#7c3aed' }}>Active connections (:3128)</div>
        </div>

        <div style={{ background: '#fdf2f8', border: '1px solid #fbcfe8', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#be185d', textTransform: 'uppercase' }}>SOCKS5</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#9d174d', margin: '0.25rem 0' }}>{c.socks5_active}</div>
          <div style={{ fontSize: '0.7rem', color: '#be185d' }}>Active connections (:1080)</div>
        </div>

        <div style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#0369a1', textTransform: 'uppercase' }}>Total HTTP</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#0c4a6e', margin: '0.25rem 0' }}>{c.total_squid_conns}</div>
          <div style={{ fontSize: '0.7rem', color: '#0369a1' }}>All states (incl. TIME_WAIT)</div>
        </div>

        <div style={{ background: '#fefce8', border: '1px solid #fde68a', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#a16207', textTransform: 'uppercase' }}>Total SOCKS5</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#713f12', margin: '0.25rem 0' }}>{c.total_socks5_conns}</div>
          <div style={{ fontSize: '0.7rem', color: '#a16207' }}>All states</div>
        </div>
      </div>

      {/* Top Client IPs */}
      {c.top_clients && c.top_clients.length > 0 && (
        <div>
          <h4 style={{ margin: '0 0 0.5rem 0', fontSize: '0.85rem', fontWeight: 700, color: '#374151' }}>Top Client IPs (by requests)</h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
            {c.top_clients.map((cl, i) => {
              const deviceNames = {
                '192.168.1.44': 'iPad',
                '192.168.1.57': 'Computer',
                '192.168.1.140': 'Phone 2',
                '127.0.0.1': 'Phone 1 (local)',
                '192.168.1.222': 'Phone 1',
              };
              const device = deviceNames[cl.ip] || 'Device';
              return (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.5rem 0.6rem', background: '#f8fafc', borderRadius: 6, border: '1px solid #f1f5f9' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <span style={{ fontSize: '0.65rem', fontWeight: 700, color: '#94a3b8', width: 20, textAlign: 'right' }}>#{i + 1}</span>
                    <span style={{ fontSize: '0.8rem', color: '#374151' }}>{device}</span>
                    <span style={{ fontSize: '0.7rem', fontFamily: 'monospace', color: '#6b7280' }}>{cl.ip}</span>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <span style={{ fontSize: '0.7rem', color: '#6b7280' }}>{cl.bytes_human}</span>
                    <span style={{ fontSize: '0.75rem', fontWeight: 700, color: '#2563eb', background: '#eff6ff', padding: '0.1rem 0.4rem', borderRadius: 4 }}>{cl.requests} reqs</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}

function SecurityDashboard({ security }) {
  if (!security) return null;
  const s = security;
  const totalRequests = s.blocked_requests + s.allowed_requests;
  const blockRate = totalRequests > 0 ? ((s.blocked_requests / totalRequests) * 100).toFixed(1) : 0;

  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#ef4444" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
        Security & Access Control (Zero Trust)
      </h3>

      {/* Security KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#b91c1c', textTransform: 'uppercase' }}>Blocked</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#991b1b', margin: '0.25rem 0' }}>{s.blocked_requests}</div>
          <div style={{ fontSize: '0.7rem', color: '#b91c1c' }}>HTTP 403 / DENIED</div>
        </div>

        <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#15803d', textTransform: 'uppercase' }}>Allowed</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#14532d', margin: '0.25rem 0' }}>{s.allowed_requests}</div>
          <div style={{ fontSize: '0.7rem', color: '#15803d' }}>TCP_TUNNEL / MISS / HIT</div>
        </div>

        <div style={{ background: '#fefce8', border: '1px solid #fde68a', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#a16207', textTransform: 'uppercase' }}>Block Rate</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#713f12', margin: '0.25rem 0' }}>{blockRate}%</div>
          <div style={{ fontSize: '0.7rem', color: '#a16207' }}>of total requests</div>
        </div>

        <div style={{ background: s.auth_failures > 0 ? '#fef2f2' : '#f0fdf4', border: `1px solid ${s.auth_failures > 0 ? '#fecaca' : '#bbf7d0'}`, borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: s.auth_failures > 0 ? '#b91c1c' : '#15803d', textTransform: 'uppercase' }}>Auth Failures</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: s.auth_failures > 0 ? '#991b1b' : '#14532d', margin: '0.25rem 0' }}>{s.auth_failures}</div>
          <div style={{ fontSize: '0.7rem', color: s.auth_failures > 0 ? '#b91c1c' : '#15803d' }}>{s.auth_failures > 0 ? 'Failed auth' : 'All OK'}</div>
        </div>
      </div>

      {/* Blocked & Allowed domains side by side */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
        {/* Top Blocked Domains */}
        <div>
          <h4 style={{ margin: '0 0 0.5rem 0', fontSize: '0.85rem', fontWeight: 700, color: '#b91c1c', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
            Top Blocked Domains
          </h4>
          {s.blocked_domains && s.blocked_domains.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.3rem' }}>
              {s.blocked_domains.map((d, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.35rem 0.5rem', background: '#fef2f2', borderRadius: 6, border: '1px solid #fecaca' }}>
                  <span style={{ fontSize: '0.75rem', fontFamily: 'monospace', color: '#991b1b' }}>{d.domain}</span>
                  <span style={{ fontSize: '0.7rem', fontWeight: 700, color: '#b91c1c', background: '#fee2e2', padding: '0.1rem 0.35rem', borderRadius: 4 }}>{d.count}</span>
                </div>
              ))}
            </div>
          ) : (
            <div style={{ fontSize: '0.8rem', color: '#15803d', padding: '0.5rem', background: '#f0fdf4', borderRadius: 6, textAlign: 'center' }}>No blocked requests</div>
          )}
        </div>

        {/* Top Allowed Domains */}
        <div>
          <h4 style={{ margin: '0 0 0.5rem 0', fontSize: '0.85rem', fontWeight: 700, color: '#15803d', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            Top Allowed Domains
          </h4>
          {s.allowed_domains && s.allowed_domains.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.3rem' }}>
              {s.allowed_domains.map((d, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.35rem 0.5rem', background: '#f0fdf4', borderRadius: 6, border: '1px solid #bbf7d0' }}>
                  <span style={{ fontSize: '0.75rem', fontFamily: 'monospace', color: '#14532d' }}>{d.domain}</span>
                  <span style={{ fontSize: '0.7rem', fontWeight: 700, color: '#15803d', background: '#dcfce7', padding: '0.1rem 0.35rem', borderRadius: 4 }}>{d.count}</span>
                </div>
              ))}
            </div>
          ) : (
            <div style={{ fontSize: '0.8rem', color: '#6b7280', padding: '0.5rem', background: '#f8fafc', borderRadius: 6, textAlign: 'center' }}>No data yet</div>
          )}
        </div>
      </div>

      {/* Blocked Ports */}
      {s.blocked_ports && s.blocked_ports.length > 0 && (
        <div style={{ marginTop: '1rem', padding: '0.75rem', background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 8 }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 700, color: '#c2410c', marginBottom: '0.4rem' }}>Blocked Ports (Egress Security)</div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.3rem' }}>
            {s.blocked_ports.map(port => (
              <span key={port} style={{ fontSize: '0.65rem', fontFamily: 'monospace', background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca', padding: '0.1rem 0.3rem', borderRadius: 3 }}>
                :{port}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function CachePerformance({ cache }) {
  if (!cache) return null;
  const c = cache;

  const hitRatioColor = c.hit_ratio >= 70 ? '#10b981' : (c.hit_ratio >= 40 ? '#f59e0b' : '#ef4444');
  const hitRatioBg = c.hit_ratio >= 70 ? '#f0fdf4' : (c.hit_ratio >= 40 ? '#fefce8' : '#fef2f2');
  const dnsColor = c.dns_resolution_ms < 50 ? '#10b981' : (c.dns_resolution_ms < 200 ? '#f59e0b' : '#ef4444');

  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#f59e0b" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
        Cache Performance (Squid RAM)
      </h3>

      {/* Cache KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
        {/* Cache Hit Ratio - prominent gauge */}
        <div style={{ background: hitRatioBg, border: `1px solid ${hitRatioColor}33`, borderRadius: 10, padding: '1rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: hitRatioColor, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Cache Hit Ratio</div>
          <div style={{ fontSize: '2rem', fontWeight: 800, color: hitRatioColor, margin: '0.25rem 0' }}>{c.hit_ratio}%</div>
          <div style={{ width: '100%', height: 8, background: '#e5e7eb', borderRadius: 999, overflow: 'hidden', marginTop: '0.35rem' }}>
            <div style={{ width: `${c.hit_ratio}%`, height: '100%', background: hitRatioColor, borderRadius: 999, transition: 'width 0.5s ease' }}></div>
          </div>
          <div style={{ fontSize: '0.65rem', color: '#6b7280', marginTop: '0.3rem' }}>{c.total_hits} hits / {c.total_misses} misses</div>
        </div>

        {/* DNS Resolution */}
        <div style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#0369a1', textTransform: 'uppercase' }}>DNS Resolution</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: dnsColor, margin: '0.25rem 0' }}>{c.dns_resolution_ms}ms</div>
          <div style={{ fontSize: '0.7rem', color: '#0369a1' }}>via {c.dns_server}</div>
        </div>

        {/* Bandwidth Saved */}
        <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#15803d', textTransform: 'uppercase' }}>Bandwidth Saved</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#14532d', margin: '0.25rem 0' }}>{c.bandwidth_saved_kb > 1024 ? (c.bandwidth_saved_kb / 1024).toFixed(1) + ' MB' : c.bandwidth_saved_kb + ' KB'}</div>
          <div style={{ fontSize: '0.7rem', color: '#15803d' }}>from RAM cache</div>
        </div>

        {/* Cache Objects */}
        <div style={{ background: '#faf5ff', border: '1px solid #e9d5ff', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#7c3aed', textTransform: 'uppercase' }}>Cache Objects</div>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: '#581c87', margin: '0.25rem 0' }}>{c.objects_in_cache || '—'}</div>
          <div style={{ fontSize: '0.7rem', color: '#7c3aed' }}>in 128 MB RAM</div>
        </div>
      </div>

      {/* Cache explanation */}
      <div style={{ padding: '0.6rem 0.8rem', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: '0.75rem', color: '#64748b' }}>
        <strong style={{ color: '#374151' }}>How it works:</strong> Squid stores frequently accessed files in RAM (128 MB). When a client requests the same file again, Squid serves it from memory instead of downloading from the internet. Higher hit ratio = less bandwidth used = faster responses.
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
          <div style={{ fontSize: '1.5rem' }}><svg width="24" height="24" fill="none" stroke="#7c3aed" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg></div>
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
          <div style={{ fontSize: '1.5rem' }}><svg width="24" height="24" fill="none" stroke="#f97316" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" /></svg></div>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#c2410c' }}>Cloudflare</div>
          <div style={{ fontSize: '0.65rem', color: '#94a3b8' }}>HTTPS Tunnel</div>
        </div>

        <svg width="20" height="20" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>

        {/* Internet */}
        <div style={{ flex: 1, minWidth: 100, background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 12, padding: '0.75rem', textAlign: 'center' }}>
          <div style={{ fontSize: '1.5rem' }}><svg width="24" height="24" fill="none" stroke="#2563eb" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg></div>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#0369a1' }}>Internet</div>
          <div style={{ fontSize: '0.65rem', color: '#94a3b8' }}>{proxy?.public_ip || 'N/A'}</div>
        </div>
      </div>

      <div style={{ marginTop: '0.75rem', display: 'flex', justifyContent: 'center', gap: '1rem', fontSize: '0.7rem', color: '#64748b' }}>
        <span>Squid: Whitelist + Cache</span>
        <span>SOCKS5: Direct Tunnel</span>
        <span>Nginx: Load Balance</span>
      </div>
    </div>
  );
}

function HardwareHealth({ hw }) {
  if (!hw) return null;
  const h = hw;

  const ramColor = h.system_ram_pct > 85 ? '#ef4444' : (h.system_ram_pct > 70 ? '#f59e0b' : '#10b981');
  const fdColor = h.open_files_pct > 80 ? '#ef4444' : (h.open_files_pct > 50 ? '#f59e0b' : '#10b981');
  const tempColor = h.system_temp > 70 ? '#ef4444' : (h.system_temp > 55 ? '#f59e0b' : '#10b981');

  return (
    <div style={{
      background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
      padding: '1.25rem', boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', fontSize: '1rem', fontWeight: 700, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <svg width="18" height="18" fill="none" stroke="#059669" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
        Hardware Health (Phone 1 Gateway)
      </h3>

      {/* System KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
        {/* RAM */}
        <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#15803d', textTransform: 'uppercase' }}>System RAM</div>
          <div style={{ fontSize: '1.3rem', fontWeight: 800, color: ramColor, margin: '0.25rem 0' }}>{h.system_ram_used_mb} / {h.system_ram_total_mb} MB</div>
          <div style={{ width: '100%', height: 6, background: '#e5e7eb', borderRadius: 999, overflow: 'hidden' }}>
            <div style={{ width: `${h.system_ram_pct}%`, height: '100%', background: ramColor, borderRadius: 999 }}></div>
          </div>
          <div style={{ fontSize: '0.65rem', color: '#6b7280', marginTop: '0.2rem' }}>{h.system_ram_pct}% used</div>
        </div>

        {/* CPU Load */}
        <div style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#0369a1', textTransform: 'uppercase' }}>CPU Load</div>
          <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#0c4a6e', margin: '0.25rem 0' }}>{h.system_load[0]} / {h.system_load[1]} / {h.system_load[2]}</div>
          <div style={{ fontSize: '0.65rem', color: '#6b7280' }}>1m / 5m / 15m avg</div>
        </div>

        {/* Temperature */}
        <div style={{ background: '#fefce8', border: '1px solid #fde68a', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#a16207', textTransform: 'uppercase' }}>Temperature</div>
          <div style={{ fontSize: '1.3rem', fontWeight: 800, color: tempColor, margin: '0.25rem 0' }}>{h.system_temp}°C</div>
          <div style={{ fontSize: '0.65rem', color: '#6b7280' }}>CPU thermal</div>
        </div>

        {/* Battery */}
        <div style={{ background: '#faf5ff', border: '1px solid #e9d5ff', borderRadius: 10, padding: '0.85rem', textAlign: 'center' }}>
          <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#7c3aed', textTransform: 'uppercase' }}>Battery</div>
          <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#581c87', margin: '0.25rem 0' }}>{h.battery_pct}%</div>
          <div style={{ fontSize: '0.65rem', color: '#6b7280' }}>Power level</div>
        </div>
      </div>

      {/* Process Stats */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '0.75rem', marginBottom: '1rem' }}>
        {/* Squid Process */}
        <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 10, padding: '0.85rem' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 700, color: '#374151', marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#10b981' }}></span>
            Squid Process
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.4rem', fontSize: '0.75rem' }}>
            <div><span style={{ color: '#6b7280' }}>CPU:</span> <strong>{h.squid_cpu}%</strong></div>
            <div><span style={{ color: '#6b7280' }}>RSS:</span> <strong>{h.squid_rss_human}</strong></div>
            <div><span style={{ color: '#6b7280' }}>VSZ:</span> <strong>{_human_bytes(h.squid_vsz_kb * 1024)}</strong></div>
            <div><span style={{ color: '#6b7280' }}>Uptime:</span> <strong>{h.squid_uptime}</strong></div>
          </div>
        </div>

        {/* SOCKS5 Process */}
        <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 10, padding: '0.85rem' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 700, color: '#374151', marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#8b5cf6' }}></span>
            SOCKS5 Process
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.4rem', fontSize: '0.75rem' }}>
            <div><span style={{ color: '#6b7280' }}>CPU:</span> <strong>{h.socks5_cpu}%</strong></div>
            <div><span style={{ color: '#6b7280' }}>RSS:</span> <strong>{h.socks5_rss_human}</strong></div>
          </div>
        </div>
      </div>

      {/* File Descriptors */}
      <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 10, padding: '0.85rem', marginBottom: '0.75rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.4rem' }}>
          <span style={{ fontSize: '0.8rem', fontWeight: 700, color: '#374151' }}>File Descriptors (ulimit)</span>
          <span style={{ fontSize: '0.75rem', fontWeight: 700, color: fdColor }}>{h.open_files_pct}%</span>
        </div>
        <div style={{ width: '100%', height: 8, background: '#e5e7eb', borderRadius: 999, overflow: 'hidden', marginBottom: '0.3rem' }}>
          <div style={{ width: `${Math.min(100, h.open_files_pct)}%`, height: '100%', background: fdColor, borderRadius: 999 }}></div>
        </div>
        <div style={{ fontSize: '0.7rem', color: '#6b7280' }}>
          {h.open_files_current} / {h.open_files_limit} open files — {h.open_files_pct > 80 ? 'Running low!' : 'Healthy'}
        </div>
      </div>

      {/* Squid Manager Info */}
      {h.squid_mgr_info && Object.keys(h.squid_mgr_info).length > 0 && (
        <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 10, padding: '0.85rem' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 700, color: '#c2410c', marginBottom: '0.4rem' }}>Squid Manager Info</div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.3rem', fontSize: '0.7rem' }}>
            {Object.entries(h.squid_mgr_info).slice(0, 8).map(([k, v]) => (
              <div key={k} style={{ display: 'flex', justifyContent: 'space-between', padding: '0.2rem 0.4rem', background: '#fff', borderRadius: 4 }}>
                <span style={{ color: '#6b7280' }}>{k.replace(/_/g, ' ')}</span>
                <span style={{ fontWeight: 600, color: '#374151' }}>{v}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function _human_bytes(size) {
  for (const unit of ['B', 'KB', 'MB', 'GB', 'TB']) {
    if (Math.abs(size) < 1024.0) return size.toFixed(1) + ' ' + unit;
    size /= 1024.0;
  }
  return size.toFixed(1) + ' PB';
}

export function ProxyCard({ proxy }) {
  if (!proxy) {
    return (
      <div style={{ padding: '2.5rem', textAlign: 'center', color: '#94a3b8' }}>
        <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '0.75rem' }}>
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ animation: 'spin 1s linear infinite' }}>
            <circle cx="12" cy="12" r="10" strokeOpacity="0.2" />
            <path d="M12 2a10 10 0 0 1 10 10" />
          </svg>
        </div>
        กำลังโหลดข้อมูล Proxy...
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

      {/* Live Proxy Traffic Stream */}
      <LiveProxyTraffic
        recentTraffic={proxy.recent_traffic}
        deviceBreakdown={proxy.device_breakdown}
        traffic={proxy.traffic}
      />

      {/* Connection Dashboard */}
      <ConnectionDashboard connections={proxy.connections} />

      {/* Security Dashboard */}
      <SecurityDashboard security={proxy.security} />

      {/* Cache Performance */}
      <CachePerformance cache={proxy.cache_perf} />

      {/* Hardware Health */}
      <HardwareHealth hw={proxy.hw_health} />

      {/* Worker Grid */}
      <WorkerGrid workers={proxy.workers} />

      {/* Egress Security */}
      <EgressSecurity blockedPorts={proxy.nginx_lb?.blocked_ports} />
    </div>
  );
}
