import { useRef, useEffect } from 'react'

function StatusBadge({ status }) {
  const code = parseInt(status)
  let cls = 'badge-gray'
  if (code >= 200 && code < 400) cls = 'badge-success'
  else if (code >= 400 && code < 500) cls = 'badge-warning'
  else if (code >= 500) cls = 'badge-danger'
  return <span className={`badge ${cls}`}>{status}</span>
}

export function TrafficTable({ logs }) {
  const tableRef = useRef(null)
  const prevCountRef = useRef(0)

  useEffect(() => {
    if (!logs) return
    if (logs.length !== prevCountRef.current && tableRef.current) {
      tableRef.current.scrollTop = 0
    }
    prevCountRef.current = logs?.length ?? 0
  }, [logs])

  return (
    <div className="card">
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <h2 className="card-title">Cloudflare Tunnel — Live HTTP Requests</h2>
        <span className="badge badge-gray" style={{ marginLeft: 'auto' }}>
          {logs?.length ?? 0} entries
        </span>
      </div>

      <div className="traffic-wrap" ref={tableRef} style={{ maxHeight: 340, overflowY: 'auto' }}>
        <table className="traffic-table">
          <thead>
            <tr>
              <th style={{ width: 155 }}>Timestamp</th>
              <th style={{ width: 120 }}>Remote IP</th>
              <th>Request Target</th>
              <th style={{ width: 75, textAlign: 'center' }}>Status</th>
              <th style={{ width: 80, textAlign: 'right' }}>Size</th>
            </tr>
          </thead>
          <tbody>
            {(!logs || logs.length === 0) ? (
              <tr>
                <td colSpan="5" style={{ textAlign: 'center', padding: '2rem', color: 'var(--muted)' }}>
                  Waiting for incoming traffic...
                </td>
              </tr>
            ) : (
              logs.map((log, i) => (
                <tr key={i}>
                  <td className="time-cell">{log.time}</td>
                  <td className="ip-cell">{log.ip}</td>
                  <td>
                    <div className="req-cell" title={log.req}>{log.req}</div>
                  </td>
                  <td style={{ textAlign: 'center' }}>
                    <StatusBadge status={log.status} />
                  </td>
                  <td style={{ textAlign: 'right', fontFamily: 'ui-monospace, monospace', color: 'var(--muted)', fontSize: '0.78rem' }}>
                    {log.size ? `${(parseInt(log.size) / 1024).toFixed(1)} KB` : '—'}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
