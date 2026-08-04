import { useState, useRef, useCallback } from 'react'

// ─── Constants ─────────────────────────────────────────────────────────────
const LAN_SERVER   = 'http://192.168.1.222:9999'
const LAN_TARGET   = '192.168.1.45'      // device the server pings via ICMP

// External CDN endpoints (CORS-enabled, no auth)
const EXT_DL_URLS = [
  'https://speed.cloudflare.com/__down?bytes=104857600',
  'https://cdn.speedof.me/sample4Mb.bin',
]
const EXT_UL_URL  = 'https://speed.cloudflare.com/__up'

const DL_DURATION_MS = 8000
const UL_DURATION_MS = 6000
const DL_CONNS_LAN   = 6
const DL_CONNS_EXT   = 4
const UL_CONNS       = 4
const UL_CHUNK       = 2 * 1024 * 1024  // 2 MB blob per upload

// ─── Formula helpers ────────────────────────────────────────────────────────
const toMbps = (bytes, ms) => ms > 0 ? +((bytes * 8) / (ms * 1000)).toFixed(2) : 0
const rfcJitter = (acc, cur, prev) => acc + (Math.abs(cur - prev) - acc) / 16

// ─── Quality badge ──────────────────────────────────────────────────────────
const qualityOf = (dl, ping) => {
  if (!dl)                               return { label: '—',           c:'#6b7280', bg:'#f3f4f6' }
  if (dl >= 900 && ping <= 2)            return { label: '🚀 Wire',     c:'#0284c7', bg:'#e0f2fe' }
  if (dl >= 100 && ping <= 5)            return { label: '⚡ Gig LAN',  c:'#0284c7', bg:'#e0f2fe' }
  if (dl >= 50  && ping <= 30)           return { label: '✅ Excellent', c:'#059669', bg:'#d1fae5' }
  if (dl >= 20  && ping <= 60)           return { label: '🔵 High',     c:'#2563eb', bg:'#dbeafe' }
  if (dl >= 5   && ping <= 120)          return { label: '🟢 Good',     c:'#65a30d', bg:'#f7fee7' }
  return                                        { label: '🟡 Fair',     c:'#d97706', bg:'#fef3c7' }
}

// ─── Sub-components ─────────────────────────────────────────────────────────
function Gauge({ value, max, label, color = '#2563eb' }) {
  const arc  = 172
  const fill = arc * Math.min(value / Math.max(max, 0.01), 1)
  return (
    <div style={{ display:'flex', flexDirection:'column', alignItems:'center' }}>
      <svg width="136" height="86" viewBox="0 0 140 90">
        <defs>
          <linearGradient id={`g-${label}`} x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%"   stopColor={color}/>
            <stop offset="100%" stopColor="#8b5cf6"/>
          </linearGradient>
        </defs>
        <path d="M 15 80 A 55 55 0 0 1 125 80" fill="none" stroke="#e2e8f0" strokeWidth="12" strokeLinecap="round"/>
        <path d="M 15 80 A 55 55 0 0 1 125 80" fill="none" stroke={`url(#g-${label})`} strokeWidth="12" strokeLinecap="round"
          strokeDasharray={arc} strokeDashoffset={arc - fill}
          style={{ transition:'stroke-dashoffset 0.3s ease-out' }}/>
      </svg>
      <div style={{ marginTop:'-58px', textAlign:'center' }}>
        <span style={{ fontSize:'1.65rem', fontWeight:900, color:'#0f172a' }}>
          {value >= 100 ? value.toFixed(0) : value.toFixed(1)}
        </span>
        <div style={{ fontSize:'0.65rem', fontWeight:700, color:'#64748b', textTransform:'uppercase', letterSpacing:'0.06em', marginTop:'2px' }}>{label}</div>
      </div>
    </div>
  )
}

function StatBox({ label, value, unit, icon, bg, border, color }) {
  return (
    <div style={{ background:bg, border:`1px solid ${border}`, borderRadius:'10px', padding:'0.7rem 0.85rem' }}>
      <div style={{ fontSize:'0.67rem', fontWeight:700, color, textTransform:'uppercase', letterSpacing:'0.05em', display:'flex', alignItems:'center', gap:'0.3rem' }}>
        {icon}{label}
      </div>
      <div style={{ fontSize:'1.25rem', fontWeight:800, color, lineHeight:1, marginTop:'0.25rem' }}>
        {value} <span style={{ fontSize:'0.7rem', fontWeight:600, opacity:0.7 }}>{unit}</span>
      </div>
    </div>
  )
}

// ─── Stage pill ─────────────────────────────────────────────────────────────
const STAGE_LABELS = {
  idle:'Ready', ping:'Ping/Jitter', upload:'Upload', download:'Download', done:'Complete'
}
function StagePill({ stage }) {
  if (stage === 'idle' || stage === 'done') return null
  return (
    <div style={{ display:'flex', alignItems:'center', gap:'0.4rem', fontSize:'0.78rem', fontWeight:700, color:'#2563eb' }}>
      <span style={{ width:'8px', height:'8px', background:'#2563eb', borderRadius:'50%',
        boxShadow:'0 0 0 0 rgba(37,99,235,0.6)', animation:'pulseRing 1s cubic-bezier(0.215,0.61,0.355,1) infinite' }}/>
      {STAGE_LABELS[stage] || stage}…
    </div>
  )
}

// ═══════════════════════════════════════════════════════════════════════════
export function SpeedTestCard({ speedtest: srv }) {
  const [mode,     setMode]     = useState('lan')      // 'lan' | 'external'
  const [stage,    setStage]    = useState('idle')
  const [liveMbps, setLiveMbps] = useState(0)
  const [liveSide, setLiveSide] = useState('dl')       // 'dl' | 'ul'
  const [result,   setResult]   = useState(null)
  const abortRef = useRef(null)

  const isTesting = stage !== 'idle' && stage !== 'done'
  const res = result || {
    ping:     srv?.ping_ms       || 0,
    jitter:   srv?.jitter_ms     || 0,
    download: srv?.download_mbps || 0,
    upload:   srv?.upload_mbps   || 0,
    pingMin:  0, pingMax: 0,
  }
  const q = qualityOf(res.download, res.ping)
  const gaugeVal   = liveSide === 'ul' ? (liveMbps || res.upload) : (liveMbps || res.download)
  const gaugeLabel = liveSide === 'ul' ? 'Mbps ↑' : 'Mbps ↓'
  const gaugeMax   = mode === 'lan' ? 1000 : Math.max(100, res.download * 1.5 || 100)
  const gaugeColor = mode === 'lan' ? '#0284c7' : '#2563eb'

  // ── LAN: server-side ICMP ping to 192.168.1.45 ─────────────────────────
  const lanPing = useCallback(async (signal) => {
    const url = `${LAN_SERVER}/api/st/lan-ping?target=${LAN_TARGET}&count=10`
    const r = await fetch(url, { signal, cache: 'no-store' })
    return await r.json()
  }, [])

  // ── External: browser-side ping to Cloudflare (fetch timing) ──────────
  const extPing = useCallback(async (signal) => {
    const rtts = []
    let jitter = 0, prev = null
    for (let i = 0; i < 10; i++) {
      if (signal.aborted) break
      const t0 = performance.now()
      try {
        await fetch(`https://speed.cloudflare.com/__down?bytes=0&nocache=${Date.now()}`, {
          signal, cache: 'no-store', method: 'HEAD'
        })
      } catch { /* HEAD might 404; timing still counts */ }
      const rtt = performance.now() - t0
      rtts.push(rtt)
      if (prev !== null) jitter = rfcJitter(jitter, rtt, prev)
      prev = rtt
      await new Promise(r => setTimeout(r, 30))
    }
    const avg = rtts.reduce((a, b) => a + b, 0) / (rtts.length || 1)
    return {
      ok: true, ping_ms: +avg.toFixed(1), jitter_ms: +jitter.toFixed(1),
      min_ms: +Math.min(...rtts).toFixed(1), max_ms: +Math.max(...rtts).toFixed(1),
      samples: rtts.length
    }
  }, [])

  // ── Download: multi-connection, time-based (used by BOTH modes) ─────────
  const runDownload = useCallback(async (signal, base, conns) => {
    const start = performance.now()
    let totalBytes = 0
    const ticker = setInterval(() => {
      setLiveMbps(toMbps(totalBytes, performance.now() - start))
    }, 200)

    const fetchStream = async (url) => {
      try {
        const res = await fetch(url, { signal, cache:'no-store', headers:{'Cache-Control':'no-store'} })
        const reader = res.body.getReader()
        while (true) {
          const { done, value } = await reader.read()
          if (done || signal.aborted) break
          totalBytes += value.byteLength
        }
      } catch {}
    }

    const timer = setTimeout(() => abortRef.current?.abort(), DL_DURATION_MS)
    const urls = Array.from({ length: conns }, (_, i) =>
      base === 'lan'
        ? `${LAN_SERVER}/api/st/download?size=134217728&nocache=${Date.now() + i}`
        : `${EXT_DL_URLS[i % EXT_DL_URLS.length]}&nc=${Date.now() + i}`
    )
    await Promise.allSettled(urls.map(fetchStream))
    clearTimeout(timer); clearInterval(ticker)
    const elapsed = performance.now() - start
    return toMbps(totalBytes, elapsed)
  }, [])

  // ── Upload: multi-connection, time-based ────────────────────────────────
  const runUpload = useCallback(async (signal, base) => {
    const BLOB  = new Uint8Array(UL_CHUNK)
    const ulUrl = base === 'lan'
      ? `${LAN_SERVER}/api/st/upload`
      : EXT_UL_URL
    const start = performance.now()
    let totalBytes = 0
    const ticker = setInterval(() => {
      setLiveMbps(toMbps(totalBytes, performance.now() - start))
    }, 200)

    const loop = async () => {
      while (!signal.aborted && (performance.now() - start) < UL_DURATION_MS) {
        try {
          const r = await fetch(`${ulUrl}?nocache=${Date.now()}`, {
            method:'POST', signal, body: BLOB,
            headers:{'Content-Type':'application/octet-stream', 'Cache-Control':'no-store'}
          })
          if (base === 'lan') {
            const j = await r.json()
            totalBytes += j.received_bytes ?? UL_CHUNK
          } else {
            totalBytes += UL_CHUNK  // Cloudflare doesn't echo back bytes
          }
        } catch { break }
      }
    }

    const timer = setTimeout(() => abortRef.current?.abort(), UL_DURATION_MS + 1000)
    await Promise.allSettled(Array.from({ length: UL_CONNS }, loop))
    clearTimeout(timer); clearInterval(ticker)
    const elapsed = performance.now() - start
    return toMbps(totalBytes, elapsed)
  }, [])

  // ── Main orchestrator ────────────────────────────────────────────────────
  const runTest = useCallback(async () => {
    if (isTesting) return
    const ctrl = new AbortController()
    abortRef.current = ctrl

    setStage('ping'); setLiveMbps(0); setLiveSide('dl'); setResult(null)

    try {
      // 1️⃣ Ping / Jitter (server-side ICMP for LAN, browser timing for External)
      const pingData = mode === 'lan'
        ? await lanPing(ctrl.signal)
        : await extPing(ctrl.signal)

      // 2️⃣ Upload
      abortRef.current = new AbortController()
      setStage('upload'); setLiveMbps(0); setLiveSide('ul')
      const upload = await runUpload(abortRef.current.signal, mode)

      // 3️⃣ Download
      abortRef.current = new AbortController()
      setStage('download'); setLiveMbps(0); setLiveSide('dl')
      const download = await runDownload(abortRef.current.signal, mode,
        mode === 'lan' ? DL_CONNS_LAN : DL_CONNS_EXT)

      setResult({
        ping:     pingData.ping_ms   || 0,
        jitter:   pingData.jitter_ms || 0,
        pingMin:  pingData.min_ms    || 0,
        pingMax:  pingData.max_ms    || 0,
        samples:  pingData.samples   || 0,
        upload,
        download,
        error:    pingData.error || null,
      })
      setLiveMbps(0); setStage('done')
    } catch {
      setStage('idle')
    }
  }, [isTesting, mode, lanPing, extPing, runUpload, runDownload])

  const isLan = mode === 'lan'
  const lastTest = srv?.last_test ? new Date(srv.last_test * 1000).toLocaleTimeString() : null

  return (
    <div className="card" style={{ background:'#fff', borderRadius:'16px', boxShadow:'0 4px 16px rgba(0,0,0,0.07)', border:'1px solid #e2e8f0', overflow:'hidden' }}>

      {/* ── Header ─────────────────────────────────────────────────── */}
      <div className="card-header" style={{ borderBottom:'1px solid #f1f5f9', paddingBottom:'0.85rem', alignItems:'flex-start' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'0.5rem' }}>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            <h2 style={{ fontSize:'1rem', fontWeight:700, color:'#0f172a', margin:0 }}>Speed Test</h2>
          </div>
          <div style={{ fontSize:'0.7rem', color:'#64748b', marginTop:'2px', marginLeft:'25px' }}>
            RFC 3550 Jitter · Time-based · Multi-connection · No cache
          </div>
        </div>
        <div style={{ marginLeft:'auto', display:'flex', flexDirection:'column', alignItems:'flex-end', gap:'0.35rem' }}>
          {lastTest && <span style={{ fontSize:'0.7rem', color:'#94a3b8' }}>Server: {lastTest}</span>}
          <button onClick={runTest} disabled={isTesting} style={{
            background: isTesting ? '#94a3b8' : isLan
              ? 'linear-gradient(135deg,#0284c7,#0ea5e9)'
              : 'linear-gradient(135deg,#4f46e5,#7c3aed)',
            border:'none', borderRadius:'8px', padding:'0.48rem 1rem',
            fontSize:'0.82rem', fontWeight:700, color:'#fff',
            cursor: isTesting ? 'not-allowed' : 'pointer',
            boxShadow: isTesting ? 'none' : '0 4px 10px rgba(37,99,235,0.25)',
            display:'flex', alignItems:'center', gap:'0.4rem', transition:'all 0.2s',
          }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"
              style={{ animation: isTesting ? 'spin 0.8s linear infinite' : 'none' }}>
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            {isTesting ? `${STAGE_LABELS[stage]}…` : 'Run Test'}
          </button>
        </div>
      </div>

      {/* ── Mode Switcher ───────────────────────────────────────────── */}
      <div style={{ display:'flex', gap:'0.5rem', margin:'0.9rem 0 0.6rem', background:'#f1f5f9', borderRadius:'12px', padding:'4px' }}>
        {[
          { id:'lan',      label:'🏠 LAN',      sub:`${LAN_SERVER.replace('http://','').replace(':9999','')} → ${LAN_TARGET}` },
          { id:'external', label:'🌐 Internet',  sub:'Cloudflare CDN endpoints' },
        ].map(m => (
          <button key={m.id} onClick={() => { if (!isTesting) { setMode(m.id); setResult(null); setStage('idle') } }}
            disabled={isTesting}
            style={{
              flex:1, border:'none', borderRadius:'9px', padding:'0.55rem 0.75rem',
              background: mode === m.id ? '#fff' : 'transparent',
              boxShadow: mode === m.id ? '0 1px 4px rgba(0,0,0,0.1)' : 'none',
              cursor: isTesting ? 'not-allowed' : 'pointer',
              transition:'all 0.18s',
            }}>
            <div style={{ fontSize:'0.83rem', fontWeight:700, color: mode === m.id ? (m.id==='lan'?'#0284c7':'#4f46e5') : '#64748b' }}>
              {m.label}
            </div>
            <div style={{ fontSize:'0.62rem', color:'#94a3b8', marginTop:'1px' }}>{m.sub}</div>
          </button>
        ))}
      </div>

      {/* ── Info Banner ─────────────────────────────────────────────── */}
      <div style={{
        background: isLan ? 'linear-gradient(135deg,#f0f9ff,#e0f2fe)' : 'linear-gradient(135deg,#f5f3ff,#ede9fe)',
        border:`1px solid ${isLan?'#bae6fd':'#ddd6fe'}`, borderRadius:'11px',
        padding:'0.7rem 1rem', marginBottom:'0.9rem',
        display:'flex', alignItems:'center', justifyContent:'space-between', flexWrap:'wrap', gap:'0.5rem'
      }}>
        <div style={{ display:'flex', alignItems:'center', gap:'0.55rem' }}>
          <span style={{ fontSize:'1.1rem' }}>{isLan ? '🔵' : '🌏'}</span>
          <div>
            <div style={{ fontSize:'0.65rem', fontWeight:700, color: isLan?'#0369a1':'#5b21b6', textTransform:'uppercase', letterSpacing:'0.06em' }}>
              {isLan ? 'LAN — ICMP ping from server to device' : 'Internet — Browser ↔ Cloudflare CDN'}
            </div>
            <div style={{ fontSize:'0.82rem', fontWeight:700, color: isLan?'#0c4a6e':'#3730a3' }}>
              {isLan
                ? `${LAN_SERVER} pings ${LAN_TARGET}  ·  ${DL_CONNS_LAN} DL conns · ${UL_CONNS} UL conns`
                : `speed.cloudflare.com  ·  ${DL_CONNS_EXT} DL conns · ${UL_CONNS} UL conns`}
            </div>
          </div>
        </div>
        <div style={{ display:'flex', alignItems:'center', gap:'0.6rem' }}>
          <StagePill stage={stage}/>
          <div style={{ background:q.bg, color:q.c, fontSize:'0.72rem', fontWeight:700, padding:'0.28rem 0.65rem', borderRadius:'999px' }}>
            {q.label}
          </div>
        </div>
      </div>

      {/* ── Gauge + 4-stat Grid ──────────────────────────────────────── */}
      <div style={{ display:'grid', gridTemplateColumns:'auto 1fr', gap:'1rem', alignItems:'center' }}>
        {/* Gauge */}
        <div style={{ background:'#f8fafc', border:'1px solid #e2e8f0', borderRadius:'14px', padding:'1rem 1.1rem', display:'flex', flexDirection:'column', alignItems:'center', minWidth:'155px' }}>
          <Gauge value={gaugeVal} max={gaugeMax} label={gaugeLabel} color={gaugeColor}/>
          <div style={{ marginTop:'0.55rem', fontSize:'0.65rem', color:'#94a3b8', textAlign:'center' }}>
            {isLan ? `${DL_CONNS_LAN} down · ${UL_CONNS} up` : `${DL_CONNS_EXT} down · ${UL_CONNS} up`}<br/>
            DL {DL_DURATION_MS/1000}s · UL {UL_DURATION_MS/1000}s
          </div>
        </div>

        {/* Stats 2×2 */}
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'0.55rem' }}>
          <StatBox label="Download" value={res.download} unit="Mbps" bg="#f0f9ff" border="#bae6fd" color="#0369a1"
            icon={<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>}/>
          <StatBox label="Upload" value={res.upload} unit="Mbps" bg="#f0fdf4" border="#bbf7d0" color="#15803d"
            icon={<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>}/>
          <StatBox label={isLan ? `Ping ICMP (${res.samples||10}×)` : `Ping (${res.samples||10}×)`} value={res.ping} unit="ms" bg="#faf5ff" border="#e9d5ff" color="#7e22ce"
            icon={<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>}/>
          <StatBox label="Jitter RFC 3550" value={res.jitter} unit="ms" bg="#fff7ed" border="#ffedd5" color="#c2410c"
            icon={<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>}/>
        </div>
      </div>

      {/* ── Ping detail row ─────────────────────────────────────────── */}
      {(res.pingMin > 0 || res.pingMax > 0) && (
        <div style={{ marginTop:'0.7rem', display:'flex', gap:'0.5rem', flexWrap:'wrap' }}>
          {[
            { l:'Min', v:res.pingMin }, { l:'Avg', v:res.ping }, { l:'Max', v:res.pingMax }
          ].map(x => (
            <div key={x.l} style={{ flex:'1 0 80px', background:'#f8fafc', border:'1px solid #e2e8f0', borderRadius:'8px', padding:'0.4rem 0.65rem', textAlign:'center' }}>
              <div style={{ fontSize:'0.62rem', fontWeight:700, color:'#94a3b8', textTransform:'uppercase' }}>{x.l}</div>
              <div style={{ fontSize:'0.95rem', fontWeight:800, color:'#475569' }}>{x.v} <span style={{ fontSize:'0.65rem', color:'#94a3b8' }}>ms</span></div>
            </div>
          ))}
          {res.error && (
            <div style={{ flex:'2 0 180px', background:'#fef2f2', border:'1px solid #fecaca', borderRadius:'8px', padding:'0.4rem 0.65rem', fontSize:'0.72rem', color:'#b91c1c' }}>
              ⚠️ {res.error}
            </div>
          )}
        </div>
      )}

      {/* ── Flow badge ──────────────────────────────────────────────── */}
      <div style={{ marginTop:'0.8rem', display:'flex', gap:'0.35rem', alignItems:'center', flexWrap:'wrap' }}>
        {['Ping / Jitter', '→', 'Upload', '→', 'Download'].map((s, i) => (
          s === '→'
            ? <span key={i} style={{ color:'#cbd5e1', fontWeight:700, fontSize:'0.85rem' }}>→</span>
            : <span key={i} style={{ background: isLan?'#e0f2fe':'#ede9fe', color: isLan?'#0369a1':'#5b21b6', fontSize:'0.7rem', fontWeight:700, padding:'0.2rem 0.55rem', borderRadius:'6px' }}>{s}</span>
        ))}
        <span style={{ marginLeft:'auto', fontSize:'0.62rem', color:'#9ca3af' }}>
          Speed = (bytes×8) / (ms×1000) Mbps · Jitter = acc+(|cur−prev|−acc)/16
        </span>
      </div>
    </div>
  )
}
