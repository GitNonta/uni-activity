export function ServicesCard({ services, listeningPorts }) {
  const srv = services ?? {}
  const ports = Array.isArray(listeningPorts) ? listeningPorts : []

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
        
        {Object.entries(srv).map(([name, status]) => {
          const isRunning = typeof status === 'string' && status.startsWith('Running');
          return (
            <div key={name} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.5rem' }}>
              <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#374151' }}>{name}</span>
              <span style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.75rem', fontWeight: 600, color: isRunning ? '#059669' : '#dc2626' }}>
                <span style={{ width: 8, height: 8, borderRadius: '50%', background: isRunning ? '#10b981' : '#ef4444', boxShadow: `0 0 4px ${isRunning ? '#10b981' : '#ef4444'}` }}></span>
                {status}
              </span>
            </div>
          );
        })}
      </div>

      {ports.length > 0 && (
        <div style={{ marginTop: '1.25rem', paddingTop: '1rem', borderTop: '2px dashed #e5e7eb' }}>
          <h3 style={{ margin: '0 0 0.75rem 0', fontSize: '0.8rem', color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 600 }}>
            Active Ports ({ports.length})
          </h3>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.4rem' }}>
            {ports.map(port => {
              const portLabels = {
                8080: 'Nginx (Web)',
                8082: 'Laravel Reverb (WS)',
                9999: 'Monitor API/UI',
                8022: 'SSH/SFTP',
                5432: 'PostgreSQL',
                6379: 'Redis'
              };
              const label = portLabels[port] || `Port ${port}`;
              return (
                <span 
                  key={port} 
                  title={label}
                  style={{ 
                    fontSize: '0.72rem', 
                    background: '#eff6ff', 
                    color: '#2563eb', 
                    padding: '0.2rem 0.5rem', 
                    borderRadius: '0.375rem', 
                    fontWeight: 600,
                    border: '1px solid #bfdbfe'
                  }}
                >
                  {port} {portLabels[port] ? `(${portLabels[port].split(' ')[0]})` : ''}
                </span>
              );
            })}
          </div>
        </div>
      )}
    </div>
  )
}
