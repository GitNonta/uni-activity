export function Header({ connected }) {
  return (
    <header style={{
      background: '#ffffff',
      borderBottom: '1px solid #e5e7eb',
      padding: '0.875rem 1.5rem',
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      position: 'sticky',
      top: 0,
      zIndex: 50,
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem' }}>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="2" y="3" width="20" height="14" rx="2"/>
          <line x1="8" y1="21" x2="16" y2="21"/>
          <line x1="12" y1="17" x2="12" y2="21"/>
        </svg>
        <span style={{ fontWeight: 700, fontSize: '1rem', color: '#111827' }}>
          Server Administration Dashboard
        </span>
      </div>
      <div style={{
        display: 'flex',
        alignItems: 'center',
        gap: '0.5rem',
        fontSize: '0.8rem',
        fontWeight: 500,
        color: connected ? '#059669' : '#dc2626',
        background: connected ? '#d1fae5' : '#fee2e2',
        padding: '0.3rem 0.75rem',
        borderRadius: '9999px',
      }}>
        <span style={{
          width: 8,
          height: 8,
          borderRadius: '50%',
          background: connected ? '#059669' : '#dc2626',
          display: 'inline-block',
          animation: connected ? 'pulse-dot 2s infinite' : 'none',
        }} />
        {connected ? 'Live' : 'Reconnecting...'}
        <style>{`
          @keyframes pulse-dot {
            0%   { opacity:1; box-shadow: 0 0 0 0 rgba(5,150,105,0.4); }
            70%  { opacity:.6; box-shadow: 0 0 0 6px rgba(5,150,105,0); }
            100% { opacity:1; box-shadow: 0 0 0 0 rgba(5,150,105,0); }
          }
        `}</style>
      </div>
    </header>
  )
}
