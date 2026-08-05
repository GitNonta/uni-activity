import { useState, useRef, useCallback, useEffect } from 'react'

// ═══════════════════════════════════════════════════════
// CONSTANTS
// ═══════════════════════════════════════════════════════
// LAN tests must bypass the page origin (which may be a Cloudflare Tunnel).
// It can be overridden for another local monitor host at build time.
const LAN_SERVER  = import.meta.env.VITE_LAN_SERVER || 'http://192.168.1.222:9999'
const LAN_INFO    = { name: 'Termux Monitor Server', location: 'Home Network (TH)', ip: '192.168.1.222', port: 9999, target: 'Browser ↔ Monitor Server' }

const EXT_DL_URLS = [
  'https://speed.cloudflare.com/__down?bytes=104857600',
  'https://speed.cloudflare.com/__down?bytes=104857600',
  'https://speed.cloudflare.com/__down?bytes=104857600',
  'https://speed.cloudflare.com/__down?bytes=104857600',
]
const EXT_UL_URL  = 'https://speed.cloudflare.com/__up'
const EXT_INFO    = { name: 'Cloudflare Speed', location: 'Anycast CDN (Global)', ip: 'speed.cloudflare.com', port: 443 }

const DL_DURATION_MS = 8000
const UL_DURATION_MS = 6000
const PING_COUNT     = 10
const WARMUP_MS      = 1500   // ตัดช่วงแรกออก (TCP slow-start)
const DL_CONNS_LAN   = 6
const DL_CONNS_EXT   = 4
const UL_CONNS       = 4
const UL_CHUNK       = 2 * 1024 * 1024  // 2 MB

// ═══════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════
const toMbps  = (bytes, ms) => ms > 0 ? +((bytes * 8) / (ms * 1000)).toFixed(2) : 0
const median  = (arr) => {
  if (!arr.length) return 0
  const s = [...arr].sort((a, b) => a - b)
  const m = Math.floor(s.length / 2)
  return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2
}
const rfcJitter = (acc, cur, prev) => acc + (Math.abs(cur - prev) - acc) / 16

const qualityOf = (dl, ping) => {
  if (!dl)               return { label: 'Not Tested',  c: '#6b7280', bg: '#f3f4f6', border: '#e5e7eb' }
  if (dl >= 900 && ping <= 2)  return { label: 'Wire Speed',   c: '#0284c7', bg: '#e0f2fe', border: '#7dd3fc' }
  if (dl >= 100 && ping <= 5)  return { label: 'Gigabit LAN',  c: '#0369a1', bg: '#e0f2fe', border: '#7dd3fc' }
  if (dl >= 50  && ping <= 30) return { label: 'Excellent',    c: '#059669', bg: '#d1fae5', border: '#6ee7b7' }
  if (dl >= 20  && ping <= 60) return { label: 'High Speed',   c: '#2563eb', bg: '#dbeafe', border: '#93c5fd' }
  if (dl >= 5   && ping <= 120)return { label: 'Good',         c: '#65a30d', bg: '#f7fee7', border: '#bef264' }
  return                        { label: 'Fair',         c: '#d97706', bg: '#fef3c7', border: '#fcd34d' }
}

// ═══════════════════════════════════════════════════════
// SVG ICONS
// ═══════════════════════════════════════════════════════
const Icon = {
  Bolt: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>
  ),
  Down: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
    </svg>
  ),
  Up: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
    </svg>
  ),
  Clock: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
    </svg>
  ),
  Wave: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
    </svg>
  ),
  Server: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
      <line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
    </svg>
  ),
  Globe: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
      <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
    </svg>
  ),
  Wifi: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/>
      <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>
    </svg>
  ),
  Router: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="2" y="9" width="20" height="12" rx="2"/><path d="M8 9V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4"/>
      <circle cx="8" cy="15" r="1" fill="currentColor"/><circle cx="12" cy="15" r="1" fill="currentColor"/>
    </svg>
  ),
  CheckCircle: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
  ),
  RefreshCw: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
      <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
    </svg>
  ),
  MapPin: (p) => (
    <svg {...p} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
    </svg>
  ),
}

// ═══════════════════════════════════════════════════════
// SEMI-CIRCULAR GAUGE
// ═══════════════════════════════════════════════════════
function Gauge({ value, max = 1000, label, sublabel, color, size = 180 }) {
  const r     = 70
  const cx    = 95
  const cy    = 95
  const start = { x: cx - r, y: cy }
  const end   = { x: cx + r, y: cy }
  const pct   = Math.min(Math.max(value / Math.max(max, 0.001), 0), 1)
  const angle = -180 + pct * 180
  const rad   = (angle * Math.PI) / 180
  const nx    = cx + r * Math.cos(rad)
  const ny    = cy + r * Math.sin(rad)
  const large = pct > 0.5 ? 1 : 0

  const trackPath = `M ${start.x} ${start.y} A ${r} ${r} 0 0 1 ${end.x} ${end.y}`
  const fillPath  = pct > 0
    ? `M ${start.x} ${start.y} A ${r} ${r} 0 ${large} 1 ${nx} ${ny}`
    : null

  const id = `sg-${label.replace(/\s/g,'')}`

  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
      <svg width={size} height={size * 0.6} viewBox="0 0 190 100" style={{ overflow: 'visible' }}>
        <defs>
          <linearGradient id={id} x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%"   stopColor={color || '#3b82f6'} />
            <stop offset="100%" stopColor="#8b5cf6" />
          </linearGradient>
        </defs>
        {/* Track */}
        <path d={trackPath} fill="none" stroke="#e2e8f0" strokeWidth="14" strokeLinecap="round" />
        {/* Fill */}
        {fillPath && (
          <path d={fillPath} fill="none" stroke={`url(#${id})`} strokeWidth="14" strokeLinecap="round"
            style={{ transition: 'all 0.3s ease-out' }}
          />
        )}
        {/* Needle dot */}
        {pct > 0 && (
          <circle cx={nx} cy={ny} r="7" fill={color || '#3b82f6'}
            style={{ transition: 'all 0.3s ease-out' }}
          />
        )}
        {/* Scale ticks */}
        {[0, 0.25, 0.5, 0.75, 1].map((p, i) => {
          const a   = (-180 + p * 180) * Math.PI / 180
          const x1  = cx + (r - 10) * Math.cos(a)
          const y1  = cy + (r - 10) * Math.sin(a)
          const x2  = cx + (r + 2) * Math.cos(a)
          const y2  = cy + (r + 2) * Math.sin(a)
          return <line key={i} x1={x1} y1={y1} x2={x2} y2={y2} stroke="#cbd5e1" strokeWidth="2" strokeLinecap="round" />
        })}
        {/* Value text */}
        <text x={cx} y={cy - 8} textAnchor="middle" fontSize="22" fontWeight="800" fill="#0f172a">
          {value >= 100 ? value.toFixed(0) : value.toFixed(1)}
        </text>
        <text x={cx} y={cy + 8} textAnchor="middle" fontSize="9" fontWeight="700" fill="#64748b" letterSpacing="0.8">
          {label.toUpperCase()}
        </text>
      </svg>
      {sublabel && (
        <div style={{ fontSize: '0.68rem', color: '#94a3b8', marginTop: '-4px' }}>{sublabel}</div>
      )}
    </div>
  )
}

// ═══════════════════════════════════════════════════════
// STAT CARD
// ═══════════════════════════════════════════════════════
function StatCard({ icon: Ic, label, value, unit, color, bg, border, sub }) {
  return (
    <div style={{ background: bg, border: `1px solid ${border}`, borderRadius: '12px', padding: '0.9rem 1rem' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', color, fontSize: '0.7rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em' }}>
        <Ic width="13" height="13" color={color} />{label}
      </div>
      <div style={{ fontSize: '1.6rem', fontWeight: 900, color, lineHeight: 1, marginTop: '0.3rem' }}>
        {value}
        <span style={{ fontSize: '0.75rem', fontWeight: 600, opacity: 0.7, marginLeft: '0.25rem' }}>{unit}</span>
      </div>
      {sub && <div style={{ fontSize: '0.65rem', color, opacity: 0.65, marginTop: '2px' }}>{sub}</div>}
    </div>
  )
}

// ═══════════════════════════════════════════════════════
// PROGRESS STEP
// ═══════════════════════════════════════════════════════
function StepPip({ label, active, done }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
      <div style={{
        width: '20px', height: '20px', borderRadius: '50%', border: `2px solid ${done ? '#059669' : active ? '#2563eb' : '#cbd5e1'}`,
        background: done ? '#059669' : active ? '#2563eb' : 'transparent',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        transition: 'all 0.3s',
      }}>
        {done
          ? <Icon.CheckCircle width="12" height="12" color="#fff" />
          : active
            ? <div style={{ width: '7px', height: '7px', borderRadius: '50%', background: '#fff' }} />
            : null
        }
      </div>
      <span style={{ fontSize: '0.72rem', fontWeight: active || done ? 700 : 400, color: done ? '#059669' : active ? '#2563eb' : '#94a3b8' }}>{label}</span>
    </div>
  )
}

// ═══════════════════════════════════════════════════════
// LIVE PROGRESS BAR
// ═══════════════════════════════════════════════════════
function LiveBar({ value, max, color }) {
  const pct = Math.min((value / Math.max(max, 0.01)) * 100, 100)
  return (
    <div style={{ height: '4px', background: '#e2e8f0', borderRadius: '2px', overflow: 'hidden', marginTop: '0.4rem' }}>
      <div style={{ height: '100%', width: `${pct}%`, background: color, borderRadius: '2px', transition: 'width 0.25s ease-out' }} />
    </div>
  )
}

// ═══════════════════════════════════════════════════════
// SERVER INFO BANNER
// ═══════════════════════════════════════════════════════
function ServerBanner({ mode, info, quality }) {
  const isLan = mode === 'lan'
  return (
    <div style={{
      background: isLan ? 'linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%)' : 'linear-gradient(135deg,#f5f3ff 0%,#ede9fe 100%)',
      border: `1px solid ${isLan ? '#bae6fd' : '#ddd6fe'}`,
      borderRadius: '12px', padding: '0.9rem 1.1rem',
      display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.75rem', flexWrap: 'wrap',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
        <div style={{
          width: '40px', height: '40px', borderRadius: '10px', flexShrink: 0,
          background: isLan ? '#0284c7' : '#6d28d9',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>
          {isLan
            ? <Icon.Router width="20" height="20" color="#fff" />
            : <Icon.Globe  width="20" height="20" color="#fff" />
          }
        </div>
        <div>
          <div style={{ fontSize: '0.72rem', fontWeight: 700, color: isLan ? '#0369a1' : '#5b21b6', textTransform: 'uppercase', letterSpacing: '0.06em' }}>
            {isLan ? 'LAN Test Server' : 'Internet Test Server'}
          </div>
          <div style={{ fontSize: '0.95rem', fontWeight: 800, color: isLan ? '#0c4a6e' : '#3730a3', marginTop: '1px' }}>{info.name}</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.85rem', marginTop: '3px', flexWrap: 'wrap' }}>
            <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', fontSize: '0.7rem', color: isLan ? '#0369a1' : '#5b21b6' }}>
              <Icon.Server width="11" height="11" />
              {info.ip}{info.port ? `:${info.port}` : ''}
            </span>
            <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', fontSize: '0.7rem', color: isLan ? '#0369a1' : '#5b21b6' }}>
              <Icon.MapPin width="11" height="11" />
              {info.location}
            </span>
            {isLan && info.target && (
              <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', fontSize: '0.7rem', color: '#0369a1' }}>
                <Icon.Wifi width="11" height="11" />
                Ping target: {info.target}
              </span>
            )}
          </div>
        </div>
      </div>
      <div style={{
        background: quality.bg, border: `1px solid ${quality.border}`,
        color: quality.c, fontSize: '0.72rem', fontWeight: 700,
        padding: '0.3rem 0.75rem', borderRadius: '999px',
      }}>
        {quality.label}
      </div>
    </div>
  )
}

// ═══════════════════════════════════════════════════════
// LIVE CHART
// ═══════════════════════════════════════════════════════
function LiveChart({ data }) {
  if (!data || data.length === 0) return null
  const W = 800
  const H = 70
  
  const maxVal = Math.max(10, ...data.map(d => Math.max(d.dl, d.ul))) * 1.1
  const maxTs  = data[data.length - 1].ts || 1
  
  const pointsDl = data.map(d => `${(d.ts / maxTs) * W},${H - (d.dl / maxVal) * H}`).join(' ')
  const pointsUl = data.map(d => `${(d.ts / maxTs) * W},${H - (d.ul / maxVal) * H}`).join(' ')

  return (
    <div style={{ marginTop: '1.5rem', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '1rem 1.25rem' }}>
      <div style={{ fontSize: '0.7rem', fontWeight: 700, color: '#64748b', marginBottom: '0.75rem', display: 'flex', gap: '1rem' }}>
        <span style={{ color: '#2563eb' }}>■ Download</span>
        <span style={{ color: '#059669' }}>■ Upload</span>
      </div>
      <svg width="100%" height={H} viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" style={{ overflow: 'visible' }}>
        <line x1="0" y1={H} x2={W} y2={H} stroke="#cbd5e1" strokeWidth="1" />
        {data.length > 1 && <polyline points={pointsDl} fill="none" stroke="#2563eb" strokeWidth="2.5" strokeLinejoin="round" />}
        {data.length > 1 && <polyline points={pointsUl} fill="none" stroke="#059669" strokeWidth="2.5" strokeLinejoin="round" />}
      </svg>
    </div>
  )
}

// ═══════════════════════════════════════════════════════
// MAIN COMPONENT
// ═══════════════════════════════════════════════════════
export function SpeedTestPage({ serverSpeedtest }) {
  const [mode,     setMode]     = useState('lan')
  const [stage,    setStage]    = useState('idle')   // idle | ping | upload | download | done
  const [liveVal,  setLiveVal]  = useState(0)
  const [liveSide, setLiveSide] = useState('dl')
  const [result,   setResult]   = useState(null)
  const [history,  setHistory]  = useState([])
  const [chartData,setChartData] = useState([])
  const [errorMsg, setErrorMsg]  = useState(null)

  const abortRef     = useRef(null)
  const bytesRef     = useRef(0)
  const startRef     = useRef(0)
  const testStartRef = useRef(0)

  const isTesting  = !['idle', 'done'].includes(stage)
  const isLan      = mode === 'lan'
  const serverInfo = isLan ? LAN_INFO : EXT_INFO

  const res = result ?? {
    ping: serverSpeedtest?.ping_ms || 0,
    jitter: serverSpeedtest?.jitter_ms || 0,
    download: serverSpeedtest?.download_mbps || 0,
    upload: serverSpeedtest?.upload_mbps || 0,
    pingMin: 0, pingMax: 0, samples: 0,
  }
  const q = qualityOf(res.download, res.ping)

  const gaugeVal   = liveSide === 'ul' ? (liveVal || res.upload)   : (liveVal || res.download)
  const gaugeMax   = isLan ? 1000 : 200
  const gaugeColor = stage === 'upload' ? '#059669' : '#2563eb'
  const gaugeLabel = stage === 'upload' ? 'Mbps Upload' : 'Mbps Download'

  // ── Ticker: live Mbps every 200ms ────────────────────────────────────
  const startTicker = useCallback((type) => {
    const id = setInterval(() => {
      const ms = performance.now() - startRef.current
      if (ms > 0 && bytesRef.current > 0) {
        const speed = toMbps(bytesRef.current, ms)
        setLiveVal(speed)
        if (type) {
          setChartData(prev => [...prev, {
            ts: performance.now() - testStartRef.current,
            dl: type === 'dl' ? speed : 0,
            ul: type === 'ul' ? speed : 0
          }])
        }
      }
    }, 200)
    return id
  }, [])

  // ── LAN Ping (browser → monitor server, same path as upload/download) ──
  const doLanPing = useCallback(async (signal) => {
    const rtts = []
    let jitter = 0, prev = null
    for (let i = 0; i < PING_COUNT; i++) {
      if (signal.aborted) break
      const t0 = performance.now()
      try {
        const response = await fetch(`${LAN_SERVER}/api/stats?nc=${Date.now()}`, { signal, cache: 'no-store' })
        if (!response.ok) continue

        const rtt = performance.now() - t0
        rtts.push(rtt)
        if (prev !== null) jitter = rfcJitter(jitter, rtt, prev)
        prev = rtt
      } catch (error) {
        if (error.name === 'AbortError') throw error
      }
      await new Promise(r => setTimeout(r, 30))
    }

    if (!rtts.length) throw new Error('Monitor Server is unreachable')

    const avg = rtts.reduce((a, b) => a + b, 0) / (rtts.length || 1)
    return {
      ok: true, method: 'HTTP Browser → Monitor',
      ping_ms:   +avg.toFixed(1),
      jitter_ms: +jitter.toFixed(1),
      min_ms:    +Math.min(...rtts).toFixed(1),
      max_ms:    +Math.max(...rtts).toFixed(1),
      samples:   rtts.length,
    }
  }, [])

  // ── External Ping (browser fetch timing, 10 samples) ─────────────────
  const doExtPing = useCallback(async (signal) => {
    const rtts = []
    let jitter = 0, prev = null
    for (let i = 0; i < PING_COUNT; i++) {
      if (signal.aborted) break
      const t0 = performance.now()
      try { await fetch(`https://speed.cloudflare.com/__down?bytes=1&nc=${Date.now()}`, { signal }) } catch { /**/ }
      const rtt = performance.now() - t0
      rtts.push(rtt)
      if (prev !== null) jitter = rfcJitter(jitter, rtt, prev)
      prev = rtt
      await new Promise(r => setTimeout(r, 30))
    }
    const avg = rtts.reduce((a, b) => a + b, 0) / (rtts.length || 1)
    return { ok: true, ping_ms: +avg.toFixed(1), jitter_ms: +jitter.toFixed(1), min_ms: +Math.min(...rtts).toFixed(1), max_ms: +Math.max(...rtts).toFixed(1), samples: rtts.length }
  }, [])

  // ── Download (multi-conn, time-based, warm-up cutoff, median) ─────────
  const doDownload = useCallback(async (signal, conns) => {
    const chunks = []          // [{ bytes, ts }] for moving average
    bytesRef.current  = 0
    startRef.current  = performance.now()

    const ticker  = startTicker('dl')

    const fetchOne = async (url) => {
      try {
        // LAN: add Cache-Control header (our server allows it)
        // External CDN (Cloudflare): NO custom headers — CORS preflight blocks Cache-Control
        const fetchOpts = isLan
          ? { signal, cache: 'no-store', headers: { 'Cache-Control': 'no-store' } }
          : { signal }
        const r = await fetch(url, fetchOpts)
        if (!r.ok || !r.body) return
        const reader = r.body.getReader()
        while (true) {
          const { done, value } = await reader.read()
          if (done || signal.aborted) break
          bytesRef.current += value.byteLength
          chunks.push({ bytes: value.byteLength, ts: performance.now() })
        }
      } catch { /* abort is expected */ }
    }

    // Hard time limit
    const timer = setTimeout(() => abortRef.current?.abort(), DL_DURATION_MS + 1500)

    const urls = Array.from({ length: conns }, (_, i) =>
      isLan
        ? `${LAN_SERVER}/api/st/download?size=134217728&nc=${Date.now() + i}`
        : `${EXT_DL_URLS[i % EXT_DL_URLS.length]}&nc=${Date.now() + i}`
    )
    await Promise.allSettled(urls.map(fetchOne))
    clearTimeout(timer)
    clearInterval(ticker)

    // Discard warm-up (first WARMUP_MS)
    const warmupCutoff = startRef.current + WARMUP_MS
    const effective    = chunks.filter(c => c.ts >= warmupCutoff)
    const effBytes     = effective.reduce((s, c) => s + c.bytes, 0)
    const effMs        = effective.length
      ? (effective[effective.length - 1].ts - effective[0].ts) || 1
      : (performance.now() - startRef.current - WARMUP_MS)

    // Collect per-second snapshots for median
    const snapshots = []
    let bucket = { bytes: 0, t0: effective.length ? effective[0].ts : startRef.current }
    for (const c of effective) {
      bucket.bytes += c.bytes
      if (c.ts - bucket.t0 >= 1000) {
        snapshots.push(toMbps(bucket.bytes, c.ts - bucket.t0))
        bucket = { bytes: 0, t0: c.ts }
      }
    }
    const speedMbps = snapshots.length >= 2
      ? +(median(snapshots)).toFixed(2)
      : toMbps(effBytes, effMs)

    setLiveVal(0)
    return speedMbps
  }, [isLan, startTicker])

  // ── Upload (multi-conn, time-based) ───────────────────────────────────
  const doUpload = useCallback(async (signal, conns) => {
    const BLOB = new Uint8Array(UL_CHUNK)
    bytesRef.current = 0
    startRef.current = performance.now()

    const ticker = startTicker('ul')
    const ulUrl  = isLan ? `${LAN_SERVER}/api/st/upload` : EXT_UL_URL

    const loop = async () => {
      while (!signal.aborted && (performance.now() - startRef.current) < UL_DURATION_MS) {
        try {
          // LAN: can send Cache-Control; external Cloudflare: omit it (CORS preflight blocks it)
          const hdrs = isLan
            ? { 'Content-Type': 'application/octet-stream', 'Cache-Control': 'no-store' }
            : { 'Content-Type': 'application/octet-stream' }
          const r = await fetch(`${ulUrl}?nc=${Date.now()}`, {
            method: 'POST', signal, body: BLOB, headers: hdrs,
          })
          if (!r.ok) continue
          if (isLan) {
            const j = await r.json().catch(() => ({}))
            bytesRef.current += j.received_bytes ?? UL_CHUNK
          } else {
            bytesRef.current += UL_CHUNK
          }
        } catch { break }
      }
    }

    const timer = setTimeout(() => abortRef.current?.abort(), UL_DURATION_MS + 1500)
    await Promise.allSettled(Array.from({ length: conns }, loop))
    clearTimeout(timer)
    clearInterval(ticker)

    const elapsed = performance.now() - startRef.current
    setLiveVal(0)
    return toMbps(bytesRef.current, elapsed)
  }, [isLan, startTicker])

  // ── Main orchestrator ─────────────────────────────────────────────────
  const runTest = useCallback(async () => {
    if (isTesting) return

    // Fresh controller for each full test run
    const ctrl = new AbortController()
    abortRef.current = ctrl

    testStartRef.current = performance.now()
    setResult(null)
    setLiveVal(0)
    setErrorMsg(null)
    setChartData([])

    try {
      if (isLan) {
        // --- LAN MODE (Browser drives the test against local server) ---
        // Step 1: Ping / Jitter
        setStage('ping')
        setLiveSide('dl')
        const pingData = await doLanPing(ctrl.signal)

        if (ctrl.signal.aborted) { setStage('idle'); return }

        // Step 2: Upload (fresh abort controller)
        const ulCtrl = new AbortController()
        abortRef.current = ulCtrl
        setStage('upload')
        setLiveSide('ul')
        const upload = await doUpload(ulCtrl.signal, UL_CONNS)

        if (ulCtrl.signal.aborted && upload === 0) { setStage('idle'); return }

        // Step 3: Download (fresh abort controller)
        const dlCtrl = new AbortController()
        abortRef.current = dlCtrl
        setStage('download')
        setLiveSide('dl')
        const download = await doDownload(dlCtrl.signal, DL_CONNS_LAN)

        const newResult = {
          ping:       pingData.ping_ms   || 0,
          jitter:     pingData.jitter_ms || 0,
          pingMin:    pingData.min_ms    || 0,
          pingMax:    pingData.max_ms    || 0,
          samples:    pingData.samples   || 0,
          pingMethod: pingData.method    || '—',
          upload,
          download,
          mode,
          ts: Date.now(),
          error: pingData.error || null,
        }
        setResult(newResult)
        setHistory(h => [newResult, ...h].slice(0, 5))
        setStage('done')
      } else {
        // --- EXTERNAL MODE (Server drives the test against Cloudflare, browser polls) ---
        // Start the job
        await fetch(`${LAN_SERVER}/api/st/ext-start`, { method: 'POST', signal: ctrl.signal, cache: 'no-store' })
        
        let lastStatus = null
        // Poll every 200ms
        while (!ctrl.signal.aborted) {
          await new Promise(r => setTimeout(r, 200))
          try {
            const r = await fetch(`${LAN_SERVER}/api/st/ext-status?nc=${Date.now()}`, { signal: ctrl.signal, cache: 'no-store' })
            const st = await r.json()
            lastStatus = st
            
            // Map stage
            if (st.stage !== 'idle' && st.stage !== 'done') {
              setStage(st.stage)
              setLiveSide(st.stage === 'upload' ? 'ul' : 'dl')
            }
            
            // Update live gauge depending on stage
            if (st.stage === 'ping') setLiveVal(0)
            if (st.stage === 'upload') setLiveVal(st.upload)
            if (st.stage === 'download') setLiveVal(st.download)
            
            // Add chart data
            if (st.stage === 'upload' || st.stage === 'download') {
                setChartData(prev => [...prev, {
                  ts: performance.now() - testStartRef.current,
                  dl: st.stage === 'download' ? st.download : 0,
                  ul: st.stage === 'upload' ? st.upload : 0
                }])
            }
            
            if (st.status === 'done' || st.status === 'error') {
              const newResult = {
                ping:       st.ping       || 0,
                jitter:     st.jitter     || 0,
                pingMin:    st.ping_min   || 0,
                pingMax:    st.ping_max   || 0,
                samples:    10,
                pingMethod: st.method     || '—',
                upload:     st.upload     || 0,
                download:   st.download   || 0,
                mode,
                ts: Date.now(),
                error: st.error || null,
              }
              setResult(newResult)
              setLiveVal(0)
              setHistory(h => [newResult, ...h].slice(0, 5))
              setStage('done')
              break
            }
          } catch {
            // Ignore fetch errors during polling
          }
        }
      }
    } catch (e) {
      if (e.name !== 'AbortError') setErrorMsg(e.message || String(e))
      setStage('idle')
    }
  }, [isTesting, isLan, mode, doLanPing, doUpload, doDownload])

  const STEPS = ['ping', 'upload', 'download']
  const stageIdx = STEPS.indexOf(stage)

  return (
    <div style={{ width: '100%', margin: '0 auto' }}>
      {/* ── Page Header ─────────────────────────────────── */}
      <div style={{ marginBottom: '1.25rem', display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.75rem' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.6rem' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '10px', background: 'linear-gradient(135deg,#2563eb,#4f46e5)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Icon.Bolt width="18" height="18" color="#fff" />
            </div>
            <div>
              <h1 style={{ fontSize: '1.25rem', fontWeight: 800, color: '#0f172a', margin: 0 }}>Internet Speed Test</h1>
              <p style={{ fontSize: '0.72rem', color: '#64748b', margin: 0, marginTop: '1px' }}>
                RFC 3550 Jitter · Warm-up cutoff · Median calc · Multi-connection · No cache
              </p>
            </div>
          </div>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          {isTesting && (
            <button
              onClick={() => { abortRef.current?.abort(); setStage('idle') }}
              style={{
                background: '#ef4444',
                border: 'none', borderRadius: '10px', padding: '0.6rem 1rem',
                fontSize: '0.85rem', fontWeight: 700, color: '#fff',
                cursor: 'pointer', display: 'flex', alignItems: 'center',
                boxShadow: '0 4px 14px rgba(239,68,68,0.3)', transition: 'all 0.2s',
              }}
            >
              Cancel
            </button>
          )}
          <button
            onClick={runTest}
            disabled={isTesting}
          style={{
            background: isTesting ? '#94a3b8' : 'linear-gradient(135deg,#2563eb,#4f46e5)',
            border: 'none', borderRadius: '10px', padding: '0.6rem 1.35rem',
            fontSize: '0.875rem', fontWeight: 700, color: '#fff',
            cursor: isTesting ? 'not-allowed' : 'pointer',
            display: 'flex', alignItems: 'center', gap: '0.5rem',
            boxShadow: isTesting ? 'none' : '0 4px 14px rgba(37,99,235,0.3)',
            transition: 'all 0.2s',
          }}
        >
          <Icon.RefreshCw width="15" height="15" style={{ animation: isTesting ? 'spin 0.8s linear infinite' : 'none' }} />
          {isTesting ? 'Testing…' : 'Start Test'}
        </button>
        </div>
      </div>

      {/* ── Mode Switcher ────────────────────────────────── */}
      <div style={{ display: 'flex', gap: '0.5rem', background: '#f1f5f9', borderRadius: '12px', padding: '5px', marginBottom: '1rem' }}>
        {[
          { id: 'lan',      Icon: Icon.Router, label: 'LAN',      sub: `Browser ↔ ${LAN_INFO.ip}` },
          { id: 'external', Icon: Icon.Globe,  label: 'Internet', sub: 'Cloudflare CDN' },
        ].map(m => (
          <button key={m.id}
            onClick={() => { if (!isTesting) { setMode(m.id); setResult(null); setStage('idle'); setLiveVal(0) } }}
            disabled={isTesting}
            style={{
              flex: 1, border: 'none', borderRadius: '9px', padding: '0.55rem 0.75rem',
              background: mode === m.id ? '#fff' : 'transparent',
              boxShadow: mode === m.id ? '0 1px 5px rgba(0,0,0,0.1)' : 'none',
              cursor: isTesting ? 'not-allowed' : 'pointer',
              transition: 'all 0.18s', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '2px',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
              <m.Icon width="14" height="14" color={mode === m.id ? (m.id === 'lan' ? '#0284c7' : '#6d28d9') : '#94a3b8'} />
              <span style={{ fontSize: '0.85rem', fontWeight: 700, color: mode === m.id ? (m.id === 'lan' ? '#0284c7' : '#6d28d9') : '#64748b' }}>{m.label}</span>
            </div>
            <span style={{ fontSize: '0.62rem', color: '#94a3b8' }}>{m.sub}</span>
          </button>
        ))}
      </div>

      {/* ── Server Banner ────────────────────────────────── */}
      <div style={{ marginBottom: '1rem' }}>
        <ServerBanner mode={mode} info={serverInfo} quality={q} />
      </div>

      {/* ── Progress Steps ───────────────────────────────── */}
      {isTesting && (
        <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '10px', padding: '0.7rem 1.1rem', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.6rem', flexWrap: 'wrap' }}>
          {STEPS.map((s, i) => (
            <div key={s} style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
              <StepPip
                label={{ ping: 'Ping / Jitter', upload: 'Upload', download: 'Download' }[s]}
                active={stage === s}
                done={stageIdx > i}
              />
              {i < STEPS.length - 1 && <div style={{ width: '20px', height: '1px', background: '#cbd5e1' }} />}
            </div>
          ))}
          <div style={{ marginLeft: 'auto', fontSize: '0.78rem', fontWeight: 700, color: '#2563eb', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <div style={{ width: '7px', height: '7px', borderRadius: '50%', background: '#2563eb', animation: 'pulseRing 1s cubic-bezier(0.215,0.61,0.355,1) infinite' }} />
            {liveVal > 0 ? `${liveVal.toFixed(1)} Mbps` : '…'}
          </div>
        </div>
      )}

      {/* ── Live Chart ──────────────────────────────────── */}
      {(isTesting || chartData.length > 0) && (
        <LiveChart data={chartData} />
      )}

      {/* ── Error Message ───────────────────────────────── */}
      {errorMsg && (
        <div style={{ marginBottom: '1rem', padding: '0.75rem 1rem', background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: '10px', color: '#991b1b', fontSize: '0.85rem', fontWeight: 600 }}>
          Test Failed: {errorMsg}
        </div>
      )}

      {/* ── Main Gauges + Stats ──────────────────────────── */}
      <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '16px', boxShadow: '0 4px 16px rgba(0,0,0,0.06)', padding: '1.5rem', marginBottom: '1rem' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.25rem', justifyItems: 'center', marginBottom: '1.25rem' }}>
          <div style={{ width: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
            <Gauge
              value={liveVal > 0 && liveSide === 'dl' ? liveVal : res.download}
              max={gaugeMax}
              label="Mbps"
              sublabel="Download"
              color="#2563eb"
            />
            {stage === 'download' && <LiveBar value={liveVal} max={gaugeMax} color="#2563eb" />}
          </div>
          <div style={{ width: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
            <Gauge
              value={liveVal > 0 && liveSide === 'ul' ? liveVal : res.upload}
              max={isLan ? 500 : 100}
              label="Mbps"
              sublabel="Upload"
              color="#059669"
            />
            {stage === 'upload' && <LiveBar value={liveVal} max={isLan ? 500 : 100} color="#059669" />}
          </div>
        </div>

        {/* 4-stat grid */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '0.6rem' }}>
          <StatCard icon={Icon.Down}  label="Download" value={res.download}  unit="Mbps" color="#1d4ed8" bg="#eff6ff"   border="#bfdbfe" />
          <StatCard icon={Icon.Up}    label="Upload"   value={res.upload}    unit="Mbps" color="#15803d" bg="#f0fdf4"   border="#bbf7d0" />
          <StatCard
            icon={Icon.Clock}
            label={`Ping (${res.samples || PING_COUNT}×)`}
            value={res.ping} unit="ms" color="#7e22ce" bg="#faf5ff" border="#e9d5ff"
            sub={
              res.pingMin > 0
                ? `↓${res.pingMin}  ↑${res.pingMax} ms  [${res.pingMethod || '—'}]`
                : res.pingMethod ? `[${res.pingMethod}]` : undefined
            }
          />
          <StatCard icon={Icon.Wave}  label="Jitter"   value={res.jitter}    unit="ms"   color="#c2410c" bg="#fff7ed"   border="#ffedd5"
            sub="RFC 3550"
          />
        </div>


        {/* Error notice */}
        {res.error && (
          <div style={{ marginTop: '0.75rem', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', padding: '0.5rem 0.8rem', fontSize: '0.75rem', color: '#b91c1c' }}>
            ⚠ {res.error}
          </div>
        )}
      </div>

      {/* ── History ──────────────────────────────────────── */}
      {history.length > 0 && (
        <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '12px', padding: '1rem', boxShadow: '0 2px 8px rgba(0,0,0,0.04)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
            <div style={{ fontSize: '0.8rem', fontWeight: 700, color: '#374151' }}>Recent Tests</div>
            <button
              onClick={() => {
                const blob = new Blob([JSON.stringify(history, null, 2)], { type: 'application/json' })
                const url = URL.createObjectURL(blob)
                const a = document.createElement('a')
                a.href = url
                a.download = `speedtest-export-${Date.now()}.json`
                a.click()
              }}
              style={{ background: '#f1f5f9', border: '1px solid #e2e8f0', padding: '5px 10px', borderRadius: '6px', fontSize: '0.7rem', fontWeight: 600, cursor: 'pointer', color: '#475569' }}
            >
              Export JSON
            </button>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
            {history.map((h, i) => (
              <div key={i} style={{
                display: 'grid', gridTemplateColumns: '70px 1fr 1fr 1fr 1fr 80px',
                gap: '0.5rem', alignItems: 'center',
                padding: '0.4rem 0.6rem', background: i === 0 ? '#f8fafc' : 'transparent',
                borderRadius: '8px', fontSize: '0.78rem',
              }}>
                <span style={{ color: '#94a3b8', fontSize: '0.68rem' }}>{new Date(h.ts).toLocaleTimeString()}</span>
                <span style={{ color: '#64748b', fontSize: '0.68rem', fontWeight: 600 }}>{h.mode === 'lan' ? 'LAN' : 'Internet'}</span>
                <span style={{ color: '#1d4ed8', fontWeight: 700 }}><Icon.Down width="10" height="10" /> {h.download} Mbps</span>
                <span style={{ color: '#15803d', fontWeight: 700 }}><Icon.Up   width="10" height="10" /> {h.upload}   Mbps</span>
                <span style={{ color: '#7e22ce', fontWeight: 700 }}><Icon.Clock width="10" height="10" /> {h.ping} ms</span>
                <span style={{ ...qualityOf(h.download, h.ping), padding: '0.15rem 0.45rem', borderRadius: '999px', fontSize: '0.65rem', fontWeight: 700, background: qualityOf(h.download, h.ping).bg, color: qualityOf(h.download, h.ping).c }}>
                  {qualityOf(h.download, h.ping).label}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ── Formula footnote ─────────────────────────────── */}
      <div style={{ marginTop: '0.75rem', display: 'flex', gap: '1rem', flexWrap: 'wrap', fontSize: '0.65rem', color: '#9ca3af' }}>
        <span><strong>Speed</strong> = (bytes × 8) / (ms × 1000) Mbps</span>
        <span><strong>Jitter</strong> = acc + (|cur−prev| − acc) / 16  (RFC 3550)</span>
        <span><strong>Result</strong> = median of 1-s snapshots, warm-up {WARMUP_MS}ms discarded</span>
        <span><strong>LAN Ping</strong> = Browser → Monitor Server ({LAN_INFO.ip})</span>
      </div>
    </div>
  )
}
