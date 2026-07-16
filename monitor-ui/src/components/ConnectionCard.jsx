import { useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'

export function ConnectionCard({ url, status, lineStatus }) {
  const isValid = url && url !== 'Not Found' && url !== 'Loading...'
  const isOnline = status?.online ?? false
  const pingMs = status?.ping_ms ?? 0
  const [isRestarting, setIsRestarting] = useState(false)

  const handleRestart = async () => {
    setIsRestarting(true)
    try {
      await fetch('/api/restart-tunnel', { method: 'POST' })
    } catch (e) {
      console.error('Failed to restart tunnel', e)
    }
    // Button stays restarting for a few seconds to let the server restart it
    setTimeout(() => setIsRestarting(false), 5000)
  }

  return (
    <div className="card">
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
        </svg>
        <h2 className="card-title">Cloudflare Tunnel Connection</h2>
        
        <div style={{ marginLeft: 'auto', display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <button 
            onClick={handleRestart}
            disabled={isRestarting}
            style={{ 
              background: '#f3f4f6', 
              border: '1px solid #d1d5db', 
              borderRadius: '6px', 
              padding: '0.3rem 0.6rem', 
              fontSize: '0.75rem', 
              fontWeight: 600, 
              color: '#374151', 
              cursor: isRestarting ? 'not-allowed' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '0.25rem'
            }}
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ animation: isRestarting ? 'spin 1s linear infinite' : 'none' }}>
              <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
            {isRestarting ? 'Restarting...' : 'Restart Tunnel'}
          </button>
          
          {isValid && (
            <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
              <span className={`badge ${isOnline ? 'badge-success' : 'badge-gray'}`}>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                {isOnline ? 'Online' : 'Checking...'}
              </span>
              {isOnline && (
                <span style={{ fontSize: '0.75rem', color: '#6b7280', background: '#f3f4f6', padding: '0.2rem 0.5rem', borderRadius: '4px', fontWeight: 600 }}>
                  {pingMs} ms
                </span>
              )}
            </div>
          )}
        </div>
      </div>

      <div style={{ display: 'flex', gap: '1.5rem', alignItems: 'flex-start', flexWrap: 'wrap' }}>
        <div style={{ flex: 1, minWidth: 220 }}>
          <p className="section-label">Public URL</p>
          <div className="url-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
              <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            {isValid
              ? <a href={url} target="_blank" rel="noopener noreferrer">{url}</a>
              : <span style={{ color: '#9ca3af' }}>Waiting for connection...</span>
            }
          </div>

          <p className="section-label" style={{ marginTop: '1rem' }}>Admin Panel</p>
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
            {isValid && (
              <>
                <a href={`${url}/admin`} target="_blank" rel="noopener noreferrer" className="badge badge-primary">
                  Admin Dashboard
                </a>
                <a href={`${url}/admin/activities`} target="_blank" rel="noopener noreferrer" className="badge badge-primary">
                  Activities
                </a>
                <a href={`${url}/admin/users`} target="_blank" rel="noopener noreferrer" className="badge badge-gray">
                  Users
                </a>
              </>
            )}
          </div>
        </div>

        <div style={{ flex: 1, minWidth: 220 }}>
          <p className="section-label">LINE Official Account</p>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', background: '#f9fafb', border: '1px solid #e5e7eb', padding: '0.75rem', borderRadius: '8px' }}>
            <div style={{ 
              width: 36, 
              height: 36, 
              borderRadius: '50%', 
              background: '#06C755', 
              display: 'flex', 
              alignItems: 'center', 
              justifyContent: 'center',
              color: '#fff',
              fontWeight: 'bold',
              fontSize: '1rem',
              flexShrink: 0
            }}>
              LN
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
                <span style={{ fontWeight: 600, fontSize: '0.875rem', color: '#111827', textOverflow: 'ellipsis', overflow: 'hidden', whiteSpace: 'nowrap' }}>
                  {lineStatus?.bot_name || 'LINE OA'}
                </span>
                <span className={`badge ${lineStatus?.status === 'Online' ? 'badge-success' : (lineStatus?.status === 'Offline' ? 'badge-error' : 'badge-gray')}`} style={{ fontSize: '0.7rem', padding: '0.1rem 0.4rem' }}>
                  {lineStatus?.status || 'Checking...'}
                </span>
              </div>
              <p style={{ fontSize: '0.75rem', color: '#6b7280', margin: 0, textOverflow: 'ellipsis', overflow: 'hidden', whiteSpace: 'nowrap' }} title={lineStatus?.error}>
                {lineStatus?.status === 'Online' ? (lineStatus?.basic_id || 'ID Active') : (lineStatus?.error || 'Checking configuration...')}
              </p>
            </div>
          </div>
        </div>

        <div>
          <p className="section-label" style={{ textAlign: 'center' }}>Scan QR Code</p>
          <div className="qr-wrap">
            {isValid
              ? <QRCodeSVG value={url} size={160} level="H" includeMargin={false} />
              : <div style={{ width: 160, height: 160, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f9fafb', border: '1px dashed #e5e7eb', borderRadius: 6, color: '#9ca3af', fontSize: '0.8rem' }}>No URL</div>
            }
          </div>
        </div>
      </div>
    </div>
  )
}
