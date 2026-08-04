import { useState } from 'react'

export function SpeedTestCard({ speedtest }) {
  const [isTesting, setIsTesting] = useState(false)
  const [localStage, setLocalStage] = useState(null)

  const status = speedtest?.status || 'idle'
  const stage = localStage || speedtest?.stage || 'idle'
  const ping = speedtest?.ping_ms || 0
  const jitter = speedtest?.jitter_ms || 0
  const download = speedtest?.download_mbps || 0
  const upload = speedtest?.upload_mbps || 0
  const lastTest = speedtest?.last_test ? new Date(speedtest.last_test * 1000).toLocaleTimeString() : null

  const handleStartSpeedTest = async () => {
    if (isTesting) return
    setIsTesting(true)
    setLocalStage('ping')
    
    try {
      await fetch('/api/speedtest', { method: 'POST' })
    } catch (e) {
      console.error('Failed to trigger speedtest', e)
    }

    // Progress stage simulation UI if backend pushes via websocket
    setTimeout(() => setLocalStage('download'), 2500)
    setTimeout(() => setLocalStage('upload'), 6000)
    setTimeout(() => {
      setIsTesting(false)
      setLocalStage(null)
    }, 9500)
  }

  // Network quality evaluation
  const getQuality = () => {
    if (download === 0 && upload === 0) return { label: 'Not Tested', color: '#9ca3af', bg: '#f3f4f6' }
    if (download >= 30 && ping <= 60) return { label: 'High Speed Connection', color: '#059669', bg: '#d1fae5' }
    if (download >= 10 && ping <= 120) return { label: 'Good Connection', color: '#2563eb', bg: '#dbeafe' }
    return { label: 'Fair Connection', color: '#d97706', bg: '#fef3c7' }
  }

  const quality = getQuality()
  const displayMbps = stage === 'upload' ? upload : download

  return (
    <div className="card">
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
        </svg>
        <h2 className="card-title">Server Internet Speed Test</h2>
        
        <div style={{ marginLeft: 'auto', display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
          {lastTest && (
            <span style={{ fontSize: '0.75rem', color: '#6b7280' }}>
              Last test: {lastTest}
            </span>
          )}

          <button 
            onClick={handleStartSpeedTest}
            disabled={isTesting || status === 'running'}
            style={{ 
              background: isTesting ? '#e5e7eb' : '#2563eb', 
              border: 'none', 
              borderRadius: '6px', 
              padding: '0.4rem 0.8rem', 
              fontSize: '0.8rem', 
              fontWeight: 600, 
              color: isTesting ? '#6b7280' : '#ffffff', 
              cursor: isTesting ? 'not-allowed' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '0.4rem',
              boxShadow: isTesting ? 'none' : '0 2px 4px rgba(37, 99, 235, 0.2)',
              transition: 'all 0.2s'
            }}
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ animation: (isTesting || status === 'running') ? 'spin 1s linear infinite' : 'none' }}>
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            {(isTesting || status === 'running') ? `Testing (${stage})...` : 'Start Speed Test'}
          </button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1.25rem', marginTop: '0.5rem' }}>
        
        {/* Speedometer Visual Gauge Card */}
        <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '1.25rem', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', textAlign: 'center' }}>
          <div style={{ position: 'relative', width: '130px', height: '90px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <svg width="130" height="90" viewBox="0 0 140 90">
              <path d="M 15 80 A 55 55 0 0 1 125 80" fill="none" stroke="#e2e8f0" strokeWidth="12" strokeLinecap="round" />
              <path 
                d="M 15 80 A 55 55 0 0 1 125 80" 
                fill="none" 
                stroke="url(#speedGradient)" 
                strokeWidth="12" 
                strokeLinecap="round" 
                strokeDasharray="172"
                strokeDashoffset={172 - (Math.min(displayMbps, 100) / 100) * 172}
                style={{ transition: 'stroke-dashoffset 0.5s ease-out' }}
              />
              <defs>
                <linearGradient id="speedGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stopColor="#3b82f6" />
                  <stop offset="50%" stopColor="#10b981" />
                  <stop offset="100%" stopColor="#8b5cf6" />
                </linearGradient>
              </defs>
            </svg>
            <div style={{ position: 'absolute', bottom: '5px', textAlign: 'center' }}>
              <div style={{ fontSize: '1.6rem', fontWeight: 800, color: '#1e293b', lineHeight: 1 }}>
                {displayMbps.toFixed(1)}
              </div>
              <div style={{ fontSize: '0.7rem', fontWeight: 600, color: '#64748b', textTransform: 'uppercase', marginTop: '2px' }}>
                Mbps ({stage === 'upload' ? 'Up' : 'Down'})
              </div>
            </div>
          </div>

          <div style={{ marginTop: '0.75rem', background: quality.bg, color: quality.color, fontSize: '0.75rem', fontWeight: 600, padding: '0.25rem 0.65rem', borderRadius: '999px' }}>
            {quality.label}
          </div>
        </div>

        {/* 4 Stats Grid */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
          
          {/* Download Mbps */}
          <div style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: '10px', padding: '0.85rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', color: '#0369a1', fontSize: '0.75rem', fontWeight: 600 }}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
              </svg>
              Download
            </div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: '#0c4a6e', marginTop: '0.25rem' }}>
              {download} <span style={{ fontSize: '0.75rem', fontWeight: 500 }}>Mbps</span>
            </div>
          </div>

          {/* Upload Mbps */}
          <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '10px', padding: '0.85rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', color: '#15803d', fontSize: '0.75rem', fontWeight: 600 }}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
              </svg>
              Upload
            </div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: '#14532d', marginTop: '0.25rem' }}>
              {upload} <span style={{ fontSize: '0.75rem', fontWeight: 500 }}>Mbps</span>
            </div>
          </div>

          {/* Ping Latency ms */}
          <div style={{ background: '#faf5ff', border: '1px solid #e9d5ff', borderRadius: '10px', padding: '0.85rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', color: '#7e22ce', fontSize: '0.75rem', fontWeight: 600 }}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
              Ping (Latency)
            </div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: '#581c87', marginTop: '0.25rem' }}>
              {ping} <span style={{ fontSize: '0.75rem', fontWeight: 500 }}>ms</span>
            </div>
          </div>

          {/* Jitter ms */}
          <div style={{ background: '#fff7ed', border: '1px solid #ffedd5', borderRadius: '10px', padding: '0.85rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', color: '#c2410c', fontSize: '0.75rem', fontWeight: 600 }}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
              Jitter
            </div>
            <div style={{ fontSize: '1.35rem', fontWeight: 700, color: '#7c2d12', marginTop: '0.25rem' }}>
              {jitter} <span style={{ fontSize: '0.75rem', fontWeight: 500 }}>ms</span>
            </div>
          </div>

        </div>
      </div>
    </div>
  )
}
