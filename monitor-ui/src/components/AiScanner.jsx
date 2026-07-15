import { useEffect, useRef } from 'react'

export function AiScanner({ aiLog, serviceStatus }) {
  const terminalEndRef = useRef(null)

  // Auto-scroll to bottom of logs on new updates
  useEffect(() => {
    if (terminalEndRef.current) {
      terminalEndRef.current.scrollIntoView({ behavior: 'smooth' })
    }
  }, [aiLog])

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      {/* Header & Status Card */}
      <div className="card" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ margin: '0 0 0.25rem 0', fontSize: '1.5rem', fontWeight: 700, color: '#111827' }}>AI Scan Service Monitor</h1>
          <p style={{ margin: 0, color: '#6b7280', fontSize: '0.9rem' }}>Real-time facial recognition verification logs and status of InsightFace engine.</p>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <span style={{ fontSize: '0.85rem', color: '#4b5563', fontWeight: 500 }}>Status:</span>
          <span style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '0.375rem',
            padding: '0.375rem 0.75rem',
            borderRadius: '9999px',
            fontSize: '0.82rem',
            fontWeight: 600,
            background: serviceStatus?.startsWith('Running') ? '#d1fae5' : '#fee2e2',
            color: serviceStatus?.startsWith('Running') ? '#065f46' : '#991b1b',
            border: `1px solid ${serviceStatus?.startsWith('Running') ? '#10b981' : '#ef4444'}`
          }}>
            <span style={{
              width: '8px',
              height: '8px',
              borderRadius: '50%',
              background: serviceStatus?.startsWith('Running') ? '#10b981' : '#ef4444',
              display: 'inline-block'
            }} />
            {serviceStatus ?? 'Stopped'}
          </span>
        </div>
      </div>

      {/* Terminal Log Card */}
      <div className="card" style={{ padding: '1.5rem', flex: 1, display: 'flex', flexDirection: 'column', minHeight: '450px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="4 17 10 11 4 5"/>
              <line x1="12" y1="19" x2="20" y2="19"/>
            </svg>
            <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>InsightFace Engine Logs</h2>
          </div>
          <button
            onClick={() => {
              navigator.clipboard.writeText(aiLog ?? '')
              alert('AI Logs copied to clipboard!')
            }}
            style={{
              padding: '0.375rem 0.75rem',
              background: '#f3f4f6',
              border: '1px solid #e5e7eb',
              borderRadius: '0.375rem',
              fontSize: '0.78rem',
              color: '#374151',
              fontWeight: 500,
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '0.25rem'
            }}
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
            Copy Logs
          </button>
        </div>

        <div style={{
          background: '#1e1e1e',
          color: '#adbac7',
          padding: '1rem',
          borderRadius: '0.5rem',
          fontFamily: 'monospace',
          fontSize: '0.82rem',
          lineHeight: '1.5',
          overflowY: 'auto',
          flex: 1,
          maxHeight: '480px',
          whiteSpace: 'pre-wrap',
          boxShadow: 'inset 0 2px 4px rgba(0,0,0,0.3)',
          border: '1px solid #2d3139'
        }}>
          {aiLog && aiLog.trim() !== '' ? aiLog : 'No logs generated yet. Launching the AI Scan Service to capture output.'}
          <div ref={terminalEndRef} />
        </div>
      </div>
    </div>
  )
}
