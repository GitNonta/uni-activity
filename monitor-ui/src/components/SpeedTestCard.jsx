import { useState, useRef, useCallback, useEffect } from 'react'

// ─── Constants ─────────────────────────────────────────────────────────────
const DL_DURATION_MS   = 8000   // 8 s download window
const UL_DURATION_MS   = 6000   // 6 s upload window
const PING_SAMPLES     = 10     // ping iterations (RFC 3550)
const DL_CONNECTIONS   = 6      // concurrent download connections
const UL_CONNECTIONS   = 4      // concurrent upload connections
const UL_CHUNK_BYTES   = 4 * 1024 * 1024  // 4 MB per upload blob

// Mbps = (bytes × 8) / (seconds × 1,000,000)
const toMbps = (bytes, seconds) => seconds > 0
  ? +((bytes * 8) / (seconds * 1_000_000)).toFixed(2)
  : 0

// ─── RFC 3550 Jitter accumulator ──────────────────────────────────────────
const updateJitter = (prev, curr, prevPing) =>
  prev + (Math.abs(curr - prevPing) - prev) / 16

// ─── UI helpers ────────────────────────────────────────────────────────────
const STAGES = {
  idle:     'Idle',
  ping:     'Testing Latency',
  download: 'Testing Download',
  upload:   'Testing Upload',
  done:     'Complete',
}

const getQuality = (dl, ping) => {
  if (dl === 0)      return { label: 'Not Tested',            color: '#9ca3af', bg: '#f3f4f6' }
  if (dl >= 50 && ping <= 30)  return { label: 'Excellent',  color: '#059669', bg: '#d1fae5' }
  if (dl >= 20 && ping <= 60)  return { label: 'High Speed', color: '#0ea5e9', bg: '#e0f2fe' }
  if (dl >= 8  && ping <= 120) return { label: 'Good',       color: '#2563eb', bg: '#dbeafe' }
  return { label: 'Fair',                                      color: '#d97706', bg: '#fef3c7' }
}

// ─── Meter Gauge ────────────────────────────────────────────────────────────
function Gauge({ value, max = 100, label }) {
  const pct = Math.min(value / max, 1)
  const arc  = 172
  const fill = arc * pct
  return (
    <div style={{ display:'flex', flexDirection:'column', alignItems:'center', gap:'0.25rem' }}>
      <svg width="130" height="82" viewBox="0 0 140 90">
        <defs>
          <linearGradient id="gGrad" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%"   stopColor="#3b82f6"/>
            <stop offset="50%"  stopColor="#10b981"/>
            <stop offset="100%" stopColor="#8b5cf6"/>
          </linearGradient>
        </defs>
        <path d="M 15 80 A 55 55 0 0 1 125 80" fill="none" stroke="#e2e8f0" strokeWidth="11" strokeLinecap="round"/>
        <path d="M 15 80 A 55 55 0 0 1 125 80" fill="none" stroke="url(#gGrad)"  strokeWidth="11" strokeLinecap="round"
          strokeDasharray={arc} strokeDashoffset={arc - fill}
          style={{ transition: 'stroke-dashoffset 0.35s ease-out' }}/>
      </svg>
      <div style={{ marginTop:'-62px', textAlign:'center' }}>
        <div style={{ fontSize:'1.75rem', fontWeight:800, color:'#0f172a', lineHeight:1 }}>
          {value.toFixed(value >= 10 ? 1 : 2)}
        </div>
        <div style={{ fontSize:'0.68rem', fontWeight:700, color:'#64748b', textTransform:'uppercase', letterSpacing:'0.05em', marginTop:'4px' }}>
          {label}
        </div>
      </div>
    </div>
  )
}

// ─── Stat Tile ───────────────────────────────────────────────────────────
function Tile({ label, value, unit, icon, bg, border, textDark }) {
  return (
    <div style={{ background: bg, border: `1px solid ${border}`, borderRadius:'12px', padding:'0.85rem' }}>
      <div style={{ display:'flex', alignItems:'center', gap:'0.4rem', color: textDark, fontSize:'0.72rem', fontWeight:700, textTransform:'uppercase', letterSpacing:'0.04em' }}>
        {icon} {label}
      </div>
      <div style={{ fontSize:'1.4rem', fontWeight:800, color: textDark, marginTop:'0.3rem', lineHeight:1 }}>
        {value} <span style={{ fontSize:'0.75rem', fontWeight:600, opacity:0.75 }}>{unit}</span>
      </div>
    </div>
  )
}

// ═══════════════════════════════════════════════════════════════════════════
// Main Component
// ═══════════════════════════════════════════════════════════════════════════
export function SpeedTestCard({ speedtest: serverSpeedtest }) {
  const [stage,    setStage]    = useState('idle')
  const [liveMbps, setLiveMbps] = useState(0)       // live gauge value
  const [result,   setResult]   = useState(null)
  const abortRef = useRef(null)

  // Determine which results to show: client-side OR server-side fallback
  const res = result || {
    ping:     serverSpeedtest?.ping_ms      || 0,
    jitter:   serverSpeedtest?.jitter_ms   || 0,
    download: serverSpeedtest?.download_mbps || 0,
    upload:   serverSpeedtest?.upload_mbps  || 0,
    server:   serverSpeedtest?.server?.name || 'Server',
    serverCode: serverSpeedtest?.server?.code || '—',
    serverLat: serverSpeedtest?.server?.latency_ms || 0,
    ts: serverSpeedtest?.last_test,
  }

  const isTesting = stage !== 'idle' && stage !== 'done'
  const quality   = getQuality(res.download, res.ping)
  const displayVal = stage === 'upload' ? (liveMbps || res.upload) : (liveMbps || res.download)
  const displayLabel = stage === 'upload' ? 'Mbps Upload' : 'Mbps Download'

  // ── Helper: get the monitor server's base URL ──────────────────────────
  const getBase = () => `${window.location.protocol}//${window.location.host}`

  // ── Ping Test (10 samples, RFC 3550 jitter) ────────────────────────────
  const runPing = useCallback(async (signal) => {
    let pingSum = 0, jitter = 0, prevPing = null, count = 0
    for (let i = 0; i < PING_SAMPLES; i++) {
      if (signal.aborted) break
      const t0 = performance.now()
      try {
        await fetch(`${getBase()}/api/stats?nocache=${Date.now()}`, { signal, cache: 'no-store' })
        const rtt = performance.now() - t0
        pingSum += rtt
        count++
        if (prevPing !== null) jitter = updateJitter(jitter, rtt, prevPing)
        prevPing = rtt
      } catch { break }
      await new Promise(r => setTimeout(r, 30))
    }
    return {
      ping:   +(pingSum / Math.max(count, 1)).toFixed(1),
      jitter: +jitter.toFixed(1),
    }
  }, [])

  // ── Download Test (multi-connection, time-based, streaming) ────────────
  const runDownload = useCallback(async (signal) => {
    const BASE_URL = getBase()
    const SIZE     = 128 * 1024 * 1024   // 128 MB per connection (time-capped)
    const start    = performance.now()
    let totalBytes = 0

    // Live update ticker
    const ticker = setInterval(() => {
      const elapsed = (performance.now() - start) / 1000
      setLiveMbps(toMbps(totalBytes, elapsed))
    }, 250)

    const fetchStream = async () => {
      const url = `${BASE_URL}/api/st/download?size=${SIZE}&nocache=${Date.now()}`
      const res = await fetch(url, {
        signal,
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-store' },
      })
      const reader = res.body.getReader()
      while (true) {
        const { done, value } = await reader.read()
        if (done || signal.aborted) break
        totalBytes += value.byteLength
      }
    }

    // Abort after DL_DURATION_MS
    const timer = setTimeout(() => abortRef.current?.abort(), DL_DURATION_MS)
    try {
      await Promise.allSettled(Array.from({ length: DL_CONNECTIONS }, fetchStream))
    } finally {
      clearTimeout(timer)
      clearInterval(ticker)
    }

    const elapsed = (performance.now() - start) / 1000
    return toMbps(totalBytes, elapsed)
  }, [])

  // ── Upload Test (multi-connection, time-based) ─────────────────────────
  const runUpload = useCallback(async (signal) => {
    const BASE_URL  = getBase()
    const BLOB      = new Uint8Array(UL_CHUNK_BYTES)   // zeros — no crypto needed
    const start     = performance.now()
    let totalBytes  = 0

    const ticker = setInterval(() => {
      const elapsed = (performance.now() - start) / 1000
      setLiveMbps(toMbps(totalBytes, elapsed))
    }, 250)

    const uploadLoop = async () => {
      while (!signal.aborted && (performance.now() - start) < UL_DURATION_MS) {
        try {
          const url = `${BASE_URL}/api/st/upload?nocache=${Date.now()}`
          const res = await fetch(url, {
            method:  'POST',
            signal,
            body:    BLOB,
            headers: { 'Content-Type': 'application/octet-stream', 'Cache-Control': 'no-store' },
          })
          const j = await res.json()
          totalBytes += j.received_bytes ?? UL_CHUNK_BYTES
        } catch { break }
      }
    }

    const timer = setTimeout(() => abortRef.current?.abort(), UL_DURATION_MS + 500)
    try {
      await Promise.allSettled(Array.from({ length: UL_CONNECTIONS }, uploadLoop))
    } finally {
      clearTimeout(timer)
      clearInterval(ticker)
    }

    const elapsed = (performance.now() - start) / 1000
    return toMbps(totalBytes, elapsed)
  }, [])

  // ── Full speedtest orchestration ───────────────────────────────────────
  const runTest = useCallback(async () => {
    if (isTesting) return
    const controller = new AbortController()
    abortRef.current = controller
    const { signal } = controller

    setStage('ping')
    setLiveMbps(0)
    setResult(null)

    try {
      // 1. Ping
      const { ping, jitter } = await runPing(signal)

      // 2. Download
      setStage('download')
      setLiveMbps(0)
      const download = await runDownload(signal)

      // Reset for upload (need fresh AbortController since previous may be aborted)
      abortRef.current = new AbortController()
      const ulSignal = abortRef.current.signal

      // 3. Upload
      setStage('upload')
      setLiveMbps(0)
      const upload = await runUpload(ulSignal)

      setResult({ ping, jitter, download, upload, server: 'Local Monitor Server', serverCode: 'LAN', serverLat: ping, ts: Date.now() / 1000 })
      setLiveMbps(0)
      setStage('done')
    } catch (e) {
      setStage('idle')
    }
  }, [isTesting, runPing, runDownload, runUpload])

  const stageLabel = STAGES[stage] || stage
  const lastTest = res.ts ? new Date(res.ts * 1000).toLocaleTimeString() : null

  return (
    <div className="card" style={{ background:'#fff', borderRadius:'16px', boxShadow:'0 4px 12px rgba(0,0,0,0.06)', border:'1px solid #e2e8f0' }}>

      {/* ── Header ─────────────────────────────────────────────────────── */}
      <div className="card-header" style={{ borderBottom:'1px solid #f1f5f9', paddingBottom:'0.9rem', alignItems:'flex-start' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'0.5rem' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            <h2 style={{ fontSize:'1rem', fontWeight:700, color:'#0f172a', margin:0 }}>Internet Speed Test</h2>
          </div>
          <div style={{ fontSize:'0.73rem', color:'#64748b', marginTop:'3px', marginLeft:'26px' }}>
            Multi-connection • Time-based • RFC 3550 Jitter • No cache
          </div>
        </div>

        <div style={{ marginLeft:'auto', display:'flex', flexDirection:'column', alignItems:'flex-end', gap:'0.35rem' }}>
          {lastTest && <span style={{ fontSize:'0.72rem', color:'#94a3b8' }}>Last: {lastTest}</span>}
          <button
            onClick={runTest}
            disabled={isTesting}
            style={{
              background: isTesting ? '#94a3b8' : 'linear-gradient(135deg,#2563eb,#4f46e5)',
              border:'none', borderRadius:'8px', padding:'0.5rem 1rem',
              fontSize:'0.82rem', fontWeight:700, color:'#fff',
              cursor: isTesting ? 'not-allowed' : 'pointer',
              display:'flex', alignItems:'center', gap:'0.45rem',
              boxShadow: isTesting ? 'none' : '0 4px 10px rgba(37,99,235,0.28)',
              transition:'all 0.2s',
            }}
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"
              style={{ animation: isTesting ? 'spin 0.8s linear infinite' : 'none' }}>
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            {isTesting ? `${stageLabel}…` : 'Start Speed Test'}
          </button>
        </div>
      </div>

      {/* ── Server Banner ──────────────────────────────────────────────── */}
      <div style={{ margin:'1.1rem 0 0.8rem', background:'linear-gradient(135deg,#f0f9ff,#e0f2fe)', border:'1px solid #bae6fd', borderRadius:'12px', padding:'0.8rem 1.2rem', display:'flex', alignItems:'center', justifyContent:'space-between', flexWrap:'wrap', gap:'0.5rem' }}>
        <div style={{ display:'flex', alignItems:'center', gap:'0.6rem' }}>
          <span style={{ fontSize:'1.15rem' }}>🌐</span>
          <div>
            <div style={{ fontSize:'0.68rem', fontWeight:700, color:'#0369a1', textTransform:'uppercase', letterSpacing:'0.06em' }}>Test Server</div>
            <div style={{ fontSize:'0.9rem', fontWeight:700, color:'#0c4a6e' }}>
              {res.server}
              <span style={{ marginLeft:'0.4rem', background:'#0284c7', color:'#fff', fontSize:'0.7rem', fontWeight:700, padding:'0.1rem 0.4rem', borderRadius:'5px' }}>
                {res.serverCode}
              </span>
            </div>
          </div>
        </div>
        <div style={{ display:'flex', alignItems:'center', gap:'0.75rem' }}>
          {isTesting && (
            <div style={{ fontSize:'0.78rem', fontWeight:600, color:'#0369a1', display:'flex', alignItems:'center', gap:'0.35rem' }}>
              <span style={{ width:'7px', height:'7px', background:'#2563eb', borderRadius:'50%', display:'inline-block',
                boxShadow:'0 0 0 0 rgba(37,99,235,0.7)', animation:'pulseRing 1s cubic-bezier(0.215,0.61,0.355,1) infinite' }}/>
              {stageLabel}
            </div>
          )}
          <div style={{ background: quality.bg, color: quality.color, fontSize:'0.73rem', fontWeight:700, padding:'0.3rem 0.7rem', borderRadius:'999px' }}>
            {quality.label}
          </div>
        </div>
      </div>

      {/* ── Gauge + Stats Grid ─────────────────────────────────────────── */}
      <div style={{ display:'grid', gridTemplateColumns:'auto 1fr', gap:'1.25rem', alignItems:'center' }}>

        {/* Gauge */}
        <div style={{ background:'#f8fafc', border:'1px solid #e2e8f0', borderRadius:'14px', padding:'1.1rem 1.25rem', display:'flex', flexDirection:'column', alignItems:'center', minWidth:'160px' }}>
          <Gauge value={displayVal} max={Math.max(100, res.download * 1.5 || 100)} label={displayLabel}/>
          <div style={{ marginTop:'0.6rem', fontSize:'0.7rem', color:'#64748b', textAlign:'center' }}>
            {DL_CONNECTIONS} concurrent connections
          </div>
        </div>

        {/* Stats 2×2 grid */}
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'0.6rem' }}>
          <Tile label="Download" value={res.download} unit="Mbps" bg="#f0f9ff" border="#bae6fd" textDark="#0369a1"
            icon={<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>}/>
          <Tile label={`Upload (${UL_CONNECTIONS}×)`} value={res.upload} unit="Mbps" bg="#f0fdf4" border="#bbf7d0" textDark="#15803d"
            icon={<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>}/>
          <Tile label={`Ping (${PING_SAMPLES}× avg)`} value={res.ping} unit="ms" bg="#faf5ff" border="#e9d5ff" textDark="#7e22ce"
            icon={<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>}/>
          <Tile label="Jitter (RFC 3550)" value={res.jitter} unit="ms" bg="#fff7ed" border="#ffedd5" textDark="#c2410c"
            icon={<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>}/>
        </div>
      </div>

      {/* ── Formula footnote ───────────────────────────────────────────── */}
      <div style={{ marginTop:'0.9rem', padding:'0.55rem 0.85rem', background:'#f8fafc', border:'1px solid #e2e8f0', borderRadius:'8px', fontSize:'0.68rem', color:'#64748b', display:'flex', gap:'1.25rem', flexWrap:'wrap' }}>
        <span>⚡ <b>Speed</b> = (bytes × 8) / (s × 1,000,000) Mbps</span>
        <span>〰️ <b>Jitter</b> = prev + (|curr−prev| − prev) / 16  <span style={{color:'#9ca3af'}}>(RFC 3550)</span></span>
        <span>⏱ <b>Window</b>: DL {DL_DURATION_MS/1000}s · UL {UL_DURATION_MS/1000}s · Ping {PING_SAMPLES}×</span>
      </div>
    </div>
  )
}
