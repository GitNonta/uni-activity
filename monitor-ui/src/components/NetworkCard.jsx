import { useState, useEffect, useRef } from 'react'
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts'

export function NetworkCard({ network, networkInfo }) {
  const net = network ?? {}
  const info = networkInfo ?? {}
  const [history, setHistory] = useState([])
  const prevRef = useRef(null)

  useEffect(() => {
    if (net.rx_rate === undefined && net.tx_rate === undefined) return

    const now = new Date()
    const timeLabel = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`

    const rxVal = net.rx_rate ?? 0
    const txVal = net.tx_rate ?? 0
    setHistory(h => [...h.slice(-29), { time: timeLabel, rx: rxVal, tx: txVal }])
  }, [net.rx_rate, net.tx_rate])

  return (
    <div className="card">
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        </svg>
        <h2 className="card-title">Network Traffic (wlan0)</h2>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', marginBottom: '1rem', fontSize: '0.8rem', color: '#4b5563', background: '#f9fafb', padding: '0.75rem', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
        <div>
          <span style={{ fontWeight: 600, color: '#374151' }}>Local IP:</span> {info.local_ip || 'N/A'}
        </div>
        <div>
          <span style={{ fontWeight: 600, color: '#374151' }}>Gateway IP:</span> {info.gateway || 'N/A'}
        </div>
        <div>
          <span style={{ fontWeight: 600, color: '#374151' }}>MAC:</span> {info.mac || 'N/A'}
        </div>
        <div>
          <span style={{ fontWeight: 600, color: '#374151' }}>DNS:</span> {info.dns || 'N/A'}
        </div>
      </div>

      <div className="stat-grid" style={{ marginBottom: '1rem' }}>
        <div className="stat-box">
          <div className="stat-label">From Gateway (RX)</div>
          <div className="stat-value" style={{ color: '#059669' }}>
            {net.rx_rate ?? 0.00} <span className="stat-unit">KB/s</span>
          </div>
          <div style={{ fontSize: '0.75rem', color: '#6b7280', marginTop: '0.25rem' }}>
            Total: {net.total_rx ?? 0} MB
          </div>
        </div>
        <div className="stat-box">
          <div className="stat-label">To Gateway (TX)</div>
          <div className="stat-value" style={{ color: '#2563eb' }}>
            {net.tx_rate ?? 0.00} <span className="stat-unit">KB/s</span>
          </div>
          <div style={{ fontSize: '0.75rem', color: '#6b7280', marginTop: '0.25rem' }}>
            Total: {net.total_tx ?? 0} MB
          </div>
        </div>
      </div>

      <p className="section-label">Live Throughput (KB/s)</p>
      {history.length > 1 ? (
        <ResponsiveContainer width="100%" height={140}>
          <AreaChart data={history} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
            <defs>
              <linearGradient id="rxGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#2563eb" stopOpacity={0.15}/>
                <stop offset="95%" stopColor="#2563eb" stopOpacity={0}/>
              </linearGradient>
              <linearGradient id="txGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#059669" stopOpacity={0.15}/>
                <stop offset="95%" stopColor="#059669" stopOpacity={0}/>
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="3 3" stroke="#f3f4f6" />
            <XAxis dataKey="time" tick={{ fontSize: 9, fill: '#9ca3af' }} tickLine={false} interval="preserveStartEnd" />
            <YAxis tick={{ fontSize: 9, fill: '#9ca3af' }} tickLine={false} axisLine={false} />
            <Tooltip
              contentStyle={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 6, fontSize: 12 }}
              formatter={(val, name) => [`${val} KB/s`, name === 'rx' ? 'Download' : 'Upload']}
            />
            <Legend iconType="plainline" iconSize={12} wrapperStyle={{ fontSize: 11 }}
              formatter={v => v === 'rx' ? 'Download (KB/s)' : 'Upload (KB/s)'} />
            <Area type="monotone" dataKey="rx" stroke="#2563eb" strokeWidth={2} fill="url(#rxGrad)" dot={false} />
            <Area type="monotone" dataKey="tx" stroke="#059669" strokeWidth={2} fill="url(#txGrad)" dot={false} />
          </AreaChart>
        </ResponsiveContainer>
      ) : (
        <div style={{ height: 140, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--muted)', fontSize: '0.85rem' }}>
          Collecting traffic data...
        </div>
      )}
    </div>
  )
}
