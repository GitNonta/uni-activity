import React from 'react';

export function AlertsHistory({ history }) {
  if (!history) return null;

  return (
    <div className="card" style={{ marginTop: '2rem' }}>
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <h2 className="card-title">Alerts History</h2>
        <span className="badge badge-gray" style={{ marginLeft: 'auto' }}>
          {history.length} / 100
        </span>
      </div>

      {history.length === 0 ? (
        <div style={{ textAlign: 'center', padding: '3rem', color: '#9ca3af' }}>
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" style={{ margin: '0 auto 1rem auto', opacity: 0.5 }}>
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
          </svg>
          <p>No alerts recorded yet.</p>
        </div>
      ) : (
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.875rem' }}>
            <thead>
              <tr style={{ borderBottom: '1px solid #e5e7eb', color: '#6b7280', textAlign: 'left' }}>
                <th style={{ padding: '0.75rem', fontWeight: 600 }}>Time</th>
                <th style={{ padding: '0.75rem', fontWeight: 600 }}>Severity</th>
                <th style={{ padding: '0.75rem', fontWeight: 600 }}>Message</th>
              </tr>
            </thead>
            <tbody>
              {history.map((alert, i) => (
                <tr key={i} style={{ borderBottom: '1px solid #f3f4f6' }}>
                  <td style={{ padding: '0.75rem', color: '#6b7280', whiteSpace: 'nowrap' }}>
                    {alert.time}
                  </td>
                  <td style={{ padding: '0.75rem' }}>
                    <span className={`badge ${alert.type === 'critical' ? 'badge-error' : 'badge-warning'}`}>
                      {alert.type === 'critical' ? 'Critical' : 'Warning'}
                    </span>
                  </td>
                  <td style={{ padding: '0.75rem', color: '#111827', width: '100%' }}>
                    {alert.message}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
