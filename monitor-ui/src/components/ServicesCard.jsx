export function ServicesCard({ services }) {
  const srv = services ?? {}

  return (
    <div className="card">
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
          <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
          <line x1="6" y1="6" x2="6.01" y2="6"/>
          <line x1="6" y1="18" x2="6.01" y2="18"/>
        </svg>
        <h2 className="card-title">Services Status</h2>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
        {Object.keys(srv).length === 0 && (
          <div style={{ color: 'var(--muted)', fontSize: '0.85rem' }}>Waiting for data...</div>
        )}
        
        {Object.entries(srv).map(([name, status]) => (
          <div key={name} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.5rem' }}>
            <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#374151' }}>{name}</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.75rem', fontWeight: 600, color: status === 'Running' ? '#059669' : '#dc2626' }}>
              <span style={{ width: 8, height: 8, borderRadius: '50%', background: status === 'Running' ? '#10b981' : '#ef4444', boxShadow: `0 0 4px ${status === 'Running' ? '#10b981' : '#ef4444'}` }}></span>
              {status}
            </span>
          </div>
        ))}
      </div>
    </div>
  )
}
