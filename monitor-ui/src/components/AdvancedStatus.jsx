import { useState, useEffect } from 'react'

export function AdvancedStatus({ data }) {
  const metrics = data?.advanced_metrics ?? {}
  const cpuFreqs = metrics.cpu_freqs ?? []
  const wifiRssi = metrics.wifi_rssi ?? null
  const netSpeeds = metrics.net_speeds ?? { rx_kbps: 0, tx_kbps: 0 }
  const topProcs = metrics.top_procs ?? []
  const postgres = metrics.postgres ?? { db_size: '—', connections: 0 }
  const redis = metrics.redis ?? { used_memory: '—', clients: 0 }
  const queue = metrics.queue ?? { pending: 0, failed: 0 }
  const cf = metrics.cloudflared ?? { latency_ms: 0 }
  const gpu = metrics.gpu ?? { freq_mhz: 0, load_percent: 0 }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      <div className="card">
        <h1 style={{ margin: '0 0 0.5rem 0', fontSize: '1.5rem', fontWeight: 700, color: '#111827' }}>Advanced Hardware & Data Operations</h1>
        <p style={{ margin: 0, color: '#6b7280', fontSize: '0.9rem' }}>Real-time statistics of Android core hardware, Ubuntu container tunnels, and data infrastructure.</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(350px, 1fr))', gap: '1.5rem' }}>
        
        {/* Column 1: Hardware & Wireless */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          
          {/* CPU Core Frequencies */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem', marginBottom: '1rem' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="4" y="4" width="16" height="16" rx="2" ry="2"/>
                <rect x="9" y="9" width="6" height="6"/>
                <line x1="9" y1="1" x2="9" y2="4"/>
                <line x1="15" y1="1" x2="15" y2="4"/>
                <line x1="9" y1="20" x2="9" y2="23"/>
                <line x1="15" y1="20" x2="15" y2="23"/>
                <line x1="20" y1="9" x2="23" y2="9"/>
                <line x1="20" y1="15" x2="23" y2="15"/>
                <line x1="1" y1="9" x2="4" y2="9"/>
                <line x1="1" y1="15" x2="4" y2="15"/>
              </svg>
              <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>CPU Core Frequencies</h2>
            </div>
            {cpuFreqs.length === 0 ? (
              <div style={{ color: '#9ca3af', fontSize: '0.85rem' }}>Core scaling stats not available.</div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '0.75rem' }}>
                  {cpuFreqs.map((freq, idx) => (
                    <div key={idx} style={{ background: '#f9fafb', border: '1px solid #e5e7eb', padding: '0.5rem 0.75rem', borderRadius: '0.5rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span style={{ fontSize: '0.8rem', fontWeight: 600, color: '#4b5563' }}>Core {idx}</span>
                      <span style={{ fontSize: '0.85rem', fontWeight: 700, color: '#2563eb' }}>
                        {freq >= 1000 ? `${(freq / 1000).toFixed(2)} GHz` : `${freq} MHz`}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* GPU Stats */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem', marginBottom: '1rem' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="2" width="20" height="20" rx="2" ry="2"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
                <line x1="12" y1="2" x2="12" y2="22"/>
              </svg>
              <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>GPU Co-Processor</h2>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '0.75rem' }}>
              <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', padding: '0.5rem 0.75rem', borderRadius: '0.5rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: '0.8rem', fontWeight: 600, color: '#4b5563' }}>Clock</span>
                <span style={{ fontSize: '0.82rem', fontWeight: 700, color: gpu.freq_mhz === 'Permission Denied' ? '#dc2626' : '#2563eb' }}>
                  {typeof gpu.freq_mhz === 'number' && gpu.freq_mhz > 0 ? `${gpu.freq_mhz} MHz` : gpu.freq_mhz}
                </span>
              </div>
              <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', padding: '0.5rem 0.75rem', borderRadius: '0.5rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: '0.8rem', fontWeight: 600, color: '#4b5563' }}>Load</span>
                <span style={{ fontSize: '0.82rem', fontWeight: 700, color: gpu.load_percent === 'Permission Denied' ? '#dc2626' : '#2563eb' }}>
                  {typeof gpu.load_percent === 'number' && gpu.load_percent > 0 ? `${gpu.load_percent}%` : gpu.load_percent}
                </span>
              </div>
            </div>
            {gpu.status === 'SELinux Protected' && (
              <div style={{ marginTop: '0.75rem', padding: '0.5rem', background: '#fffbeb', border: '1px solid #fef3c7', borderRadius: '0.375rem', fontSize: '0.72rem', color: '#b45309', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span>Android SELinux policy blocks non-root reading of GPU files directly.</span>
              </div>
            )}
          </div>

          {/* Network Speeds & WiFi RSSI */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem', marginBottom: '1rem' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
                <path d="M1.42 9a16 16 0 0 1 21.16 0"/>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                <line x1="12" y1="20" x2="12.01" y2="20"/>
              </svg>
              <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>Wireless & Bandwidth</h2>
            </div>
            
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {/* Traffic Speed indicators */}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '0.75rem' }}>
                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', padding: '0.75rem', borderRadius: '0.5rem', textAlign: 'center' }}>
                  <div style={{ fontSize: '0.75rem', color: '#166534', fontWeight: 600, marginBottom: '0.25rem' }}>📥 Download Speed</div>
                  <div style={{ fontSize: '1.2rem', fontWeight: 700, color: '#15803d' }}>
                    {netSpeeds.rx_kbps} <span style={{ fontSize: '0.8rem', fontWeight: 400 }}>KB/s</span>
                  </div>
                </div>
                <div style={{ background: '#fef2f2', border: '1px solid #fecaca', padding: '0.75rem', borderRadius: '0.5rem', textAlign: 'center' }}>
                  <div style={{ fontSize: '0.75rem', color: '#991b1b', fontWeight: 600, marginBottom: '0.25rem' }}>📤 Upload Speed</div>
                  <div style={{ fontSize: '1.2rem', fontWeight: 700, color: '#b91c1c' }}>
                    {netSpeeds.tx_kbps} <span style={{ fontSize: '0.8rem', fontWeight: 400 }}>KB/s</span>
                  </div>
                </div>
              </div>

              {/* WiFi RSSI if available */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f9fafb', border: '1px solid #e5e7eb', padding: '0.75rem', borderRadius: '0.5rem' }}>
                <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#374151' }}>Wi-Fi Signal Strength</span>
                <span style={{ 
                  fontSize: '0.85rem', 
                  fontWeight: 700, 
                  color: wifiRssi !== null ? (wifiRssi > -60 ? '#10b981' : wifiRssi > -80 ? '#f59e0b' : '#ef4444') : '#6b7280' 
                }}>
                  {wifiRssi !== null ? `${wifiRssi} dBm` : 'N/A (Cellular / Eth)'}
                </span>
              </div>
            </div>
          </div>

          {/* Cloudflared Latency */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem', marginBottom: '1rem' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
              <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>Cloudflared Tunnel Latency</h2>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span style={{ fontSize: '0.85rem', color: '#4b5563' }}>Ping to Cloudflare Edge</span>
              <span style={{ fontSize: '1.25rem', fontWeight: 700, color: cf.latency_ms > 0 ? '#2563eb' : '#9ca3af' }}>
                {cf.latency_ms > 0 ? `${cf.latency_ms} ms` : 'Offline / Checking'}
              </span>
            </div>
          </div>

        </div>

        {/* Column 2: Processes & Infrastructure */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          
          {/* Top Processes (Task Manager) */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem', marginBottom: '1rem' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="8" y1="6" x2="21" y2="6"/>
                <line x1="8" y1="12" x2="21" y2="12"/>
                <line x1="8" y1="18" x2="21" y2="18"/>
                <line x1="3" y1="6" x2="3.01" y2="6"/>
                <line x1="3" y1="12" x2="3.01" y2="12"/>
                <line x1="3" y1="18" x2="3.01" y2="18"/>
              </svg>
              <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>Top Resource Processes</h2>
            </div>
            {topProcs.length === 0 ? (
              <div style={{ color: '#9ca3af', fontSize: '0.85rem', textAlign: 'center', padding: '1rem' }}>Loading process list...</div>
            ) : (
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.8rem' }}>
                <thead>
                  <tr style={{ textAlign: 'left', color: '#6b7280', borderBottom: '2px solid #e5e7eb' }}>
                    <th style={{ padding: '0.5rem 0.25rem' }}>PID</th>
                    <th style={{ padding: '0.5rem 0.25rem' }}>Process</th>
                    <th style={{ padding: '0.5rem 0.25rem', textAlign: 'right' }}>CPU %</th>
                    <th style={{ padding: '0.5rem 0.25rem', textAlign: 'right' }}>RAM %</th>
                  </tr>
                </thead>
                <tbody>
                  {topProcs.map((p, idx) => (
                    <tr key={idx} style={{ borderBottom: '1px solid #f3f4f6' }}>
                      <td style={{ padding: '0.5rem 0.25rem', color: '#9ca3af', fontFamily: 'monospace' }}>{p.pid}</td>
                      <td style={{ padding: '0.5rem 0.25rem', fontWeight: 600, color: '#374151' }}>{p.name}</td>
                      <td style={{ padding: '0.5rem 0.25rem', textAlign: 'right', color: '#dc2626', fontWeight: 600 }}>{p.cpu}%</td>
                      <td style={{ padding: '0.5rem 0.25rem', textAlign: 'right', color: '#2563eb', fontWeight: 600 }}>{p.mem}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {/* Database & Infrastructure Analytics */}
          <div className="card" style={{ padding: '1.5rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem', marginBottom: '1rem' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                <path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>
              </svg>
              <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>Data Operations</h2>
            </div>
            
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {/* PostgreSQL */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.5rem 0', borderBottom: '1px solid #f3f4f6' }}>
                <div>
                  <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#374151', display: 'block' }}>PostgreSQL Database</span>
                  <span style={{ fontSize: '0.72rem', color: '#9ca3af' }}>Active connections</span>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontSize: '0.9rem', fontWeight: 700, color: '#111827' }}>{postgres.db_size ?? '—'}</div>
                  <div style={{ fontSize: '0.75rem', color: '#6b7280' }}>{postgres.connections} sessions</div>
                </div>
              </div>

              {/* Redis */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.5rem 0', borderBottom: '1px solid #f3f4f6' }}>
                <div>
                  <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#374151', display: 'block' }}>Redis Cache Store</span>
                  <span style={{ fontSize: '0.72rem', color: '#9ca3af' }}>Memory occupied</span>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontSize: '0.9rem', fontWeight: 700, color: '#111827' }}>{redis.used_memory ?? '—'}</div>
                  <div style={{ fontSize: '0.75rem', color: '#6b7280' }}>{redis.clients} clients</div>
                </div>
              </div>

              {/* Laravel Queue */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.5rem 0' }}>
                <div>
                  <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#374151', display: 'block' }}>Laravel Queue Workers</span>
                  <span style={{ fontSize: '0.72rem', color: '#9ca3af' }}>Redis queue lists</span>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontSize: '0.9rem', fontWeight: 700, color: queue.pending > 0 ? '#d97706' : '#10b981' }}>
                    {queue.pending} pending
                  </div>
                  <div style={{ fontSize: '0.75rem', color: queue.failed > 0 ? '#ef4444' : '#6b7280', fontWeight: queue.failed > 0 ? 600 : 400 }}>
                    {queue.failed} failed jobs
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
        
      </div>
    </div>
  )
}
