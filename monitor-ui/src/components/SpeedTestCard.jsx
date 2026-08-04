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
  const server = speedtest?.server || { name: 'Auto-Selected Server', code: 'BKK', latency_ms: 0 }
  const lastTest = speedtest?.last_test ? new Date(speedtest.last_test * 1000).toLocaleTimeString() : null

  const handleStartSpeedTest = async () => {
    if (isTesting) return
    setIsTesting(true)
    setLocalStage('Finding Best Server')

    try {
      await fetch('/api/speedtest', { method: 'POST' })
    } catch (e) {
      console.error('Failed to trigger speedtest', e)
    }

    // Smooth UI Stage progression
    setTimeout(() => setLocalStage('Testing Latency'), 2000)
    setTimeout(() => setLocalStage('Testing Download'), 4500)
    setTimeout(() => setLocalStage('Testing Upload'), 8000)
    setTimeout(() => {
      setIsTesting(false)
      setLocalStage(null)
    }, 11000)
  }

  const getQuality = () => {
    if (download === 0 && upload === 0) return { label: 'Not Tested', color: '#9ca3af', bg: '#f3f4f6' }
    if (download >= 30 && ping <= 60) return { label: 'High Speed Connection', color: '#059669', bg: '#d1fae5' }
    if (download >= 10 && ping <= 120) return { label: 'Good Connection', color: '#2563eb', bg: '#dbeafe' }
    return { label: 'Fair Connection', color: '#d97706', bg: '#fef3c7' }
  }

  const quality = getQuality()
  const displayMbps = stage === 'Testing Upload' ? upload : download

  return (
    <div className="card" style={{ background: '#ffffff', borderRadius: '16px', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05)', border: '1px solid #e2e8f0' }}>
      
      {/* Header */}
      <div className="card-header" style={{ borderBottom: '1px solid #f1f5f9', paddingBottom: '0.85rem' }}>
        <svg className="card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
        </svg>
        <div>
          <h2 className="card-title" style={{ fontSize: '1.05rem', fontWeight: 700, color: '#0f172a' }}>Server Internet Speed Test</h2>
          <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '2px' }}>Automated Nearest Node Benchmark</div>
        </div>

        <div style={{ marginLeft: 'auto', display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
          {lastTest && (
            <span style={{ fontSize: '0.75rem', color: '#64748b' }}>
              Tested at {lastTest}
            </span>
          )}

          <button 
            onClick={handleStartSpeedTest}
            disabled={isTesting || status === 'running'}
            style={{ 
              background: (isTesting || status === 'running') ? '#94a3b8' : 'linear-gradient(135deg, #2563eb 0%, #4f46e5 100%)', 
              border: 'none', 
              borderRadius: '8px', 
              padding: '0.5rem 1rem', 
              fontSize: '0.825rem', 
              fontWeight: 600, 
              color: '#ffffff', 
              cursor: (isTesting || status === 'running') ? 'not-allowed' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem',
              boxShadow: (isTesting || status === 'running') ? 'none' : '0 4px 10px rgba(37, 99, 235, 0.3)',
              transition: 'all 0.2s ease-in-out'
            }}
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ animation: (isTesting || status === 'running') ? 'spin 1s linear infinite' : 'none' }}>
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            {(isTesting || status === 'running') ? 'Running Speed Test...' : 'Start Speed Test'}
          </button>
        </div>
      </div>

      {/* Selected Server Banner */}
      <div style={{ margin: '1.25rem 0 0.85rem', background: 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)', border: '1px solid #bae6fd', borderRadius: '12px', padding: '0.85rem 1.25rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.75rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.6rem' }}>
          <span style={{ fontSize: '1.2rem' }}>🌐</span>
          <div>
            <div style={{ fontSize: '0.725rem', fontWeight: 600, color: '#0369a1', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Optimal Server Selected</div>
            <div style={{ fontSize: '0.95rem', fontWeight: 700, color: '#0c4a6e' }}>
              {server.name} <span style={{ fontSize: '0.75rem', fontWeight: 600, background: '#0284c7', color: '#ffffff', padding: '0.15rem 0.45rem', borderRadius: '6px', marginLeft: '0.3rem' }}>{server.code}</span>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontSize: '0.725rem', color: '#0369a1', fontWeight: 500 }}>Server Latency</div>
            <div style={{ fontSize: '0.95rem', fontWeight: 700, color: '#0369a1' }}>{server.latency_ms || ping} ms</div>
          </div>
          <div style={{ background: quality.bg, color: quality.color, fontSize: '0.75rem', fontWeight: 700, padding: '0.35rem 0.75rem', borderRadius: '999px', border: '1px solid currentColor' }}>
            {quality.label}
          </div>
        </div>
      </div>

      {/* Active Stage Indicator */}
      {(isTesting || status === 'running') && (
        <div style={{ marginBottom: '1.25rem', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '0.5rem 0.85rem', display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.8rem', color: '#334155', fontWeight: 600 }}>
          <span style={{ width: '8px', height: '8px', background: '#2563eb', borderRadius: '50%', animation: 'pulse 1s infinite' }}></span>
          Current Stage: <span style={{ color: '#2563eb' }}>{stage}</span>
        </div>
      )}

      {/* Speedometer Gauge & Stats Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1.25rem' }}>
        
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
              <div style={{ fontSize: '1.65rem', fontWeight: 800, color: '#0f172a', lineHeight: 1 }}>
                {displayMbps.toFixed(1)}
              </div>
              <div style={{ fontSize: '0.68rem', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', marginTop: '3px' }}>
                Mbps ({stage === 'Testing Upload' ? 'Upload' : 'Download'})
              </div>
            </div>
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
            <div style={{ fontSize: '1.35rem', fontWeight: 800, color: '#0c4a6e', marginTop: '0.25rem' }}>
              {download} <span style={{ fontSize: '0.75rem', fontWeight: 600 }}>Mbps</span>
            </div>
          </div>

          {/* Upload Mbps */}
          <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '10px', padding: '0.85rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', color: '#15803d', fontSize: '0.75rem', fontWeight: 600 }}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
              </svg>
              Upload (3x Avg)
            </div>
            <div style={{ fontSize: '1.35rem', fontWeight: 800, color: '#14532d', marginTop: '0.25rem' }}>
              {upload} <span style={{ fontSize: '0.75rem', fontWeight: 600 }}>Mbps</span>
            </div>
          </div>

          {/* Ping Latency ms */}
          <div style={{ background: '#faf5ff', border: '1px solid #e9d5ff', borderRadius: '10px', padding: '0.85rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', color: '#7e22ce', fontSize: '0.75rem', fontWeight: 600 }}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
              Ping (8x Samples)
            </div>
            <div style={{ fontSize: '1.35rem', fontWeight: 800, color: '#581c87', marginTop: '0.25rem' }}>
              {ping} <span style={{ fontSize: '0.75rem', fontWeight: 600 }}>ms</span>
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
            <div style={{ fontSize: '1.35rem', fontWeight: 800, color: '#7c2d12', marginTop: '0.25rem' }}>
              {jitter} <span style={{ fontSize: '0.75rem', fontWeight: 600 }}>ms</span>
            </div>
          </div>

        </div>
      </div>
    </div>
  )
}
