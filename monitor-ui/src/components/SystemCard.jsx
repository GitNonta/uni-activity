function ProgressBar({ percent }) {
  const cls = percent > 85 ? 'progress-red' : percent > 65 ? 'progress-yellow' : 'progress-green'
  return (
    <div className="progress-bar-wrap">
      <div className="progress-bar" style={{ width: `${percent}%` }} />
      <style>{`
        .progress-bar { background: ${percent > 85 ? '#dc2626' : percent > 65 ? '#d97706' : '#059669'}; }
      `}</style>
    </div>
  )
}

export function SystemCard({ memory, load, temp, disk, battery }) {
  const mem = memory ?? {}
  const loadArr = load ?? [0, 0, 0]
  const dsk = disk ?? {}
  const bat = battery ?? null

  let current_mA = 0, power_W = 0, cap_mAh = 0, cap_Wh = 0;
  if (bat && bat.voltage_mv) {
    const v = bat.voltage_mv / 1000;
    const c_a = Math.abs(bat.current_ua) / 1000000;
    current_mA = Math.abs(bat.current_ua) / 1000;
    power_W = c_a * v;
    cap_mAh = bat.charge_counter_uah / 1000;
    cap_Wh = (cap_mAh * v) / 1000;
  }

  return (
    <div className="card">
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
        <h2 className="card-title">System Resources</h2>
      </div>

      <p className="section-label">Memory (RAM)</p>
      <div className="stat-grid">
        <div className="stat-box">
          <div className="stat-label">Used</div>
          <div className="stat-value">{mem.used_mb ?? '—'} <span className="stat-unit">MB</span></div>
        </div>
        <div className="stat-box">
          <div className="stat-label">Total</div>
          <div className="stat-value">{mem.total_mb ?? '—'} <span className="stat-unit">MB</span></div>
        </div>
      </div>
      <div style={{ marginTop: '0.5rem', display: 'flex', justifyContent: 'space-between', fontSize: '0.78rem', color: 'var(--muted)' }}>
        <span>Usage: <strong style={{ color: 'var(--text)' }}>{mem.percent ?? 0}%</strong></span>
        <span>Free: {mem.available_mb ?? '—'} MB</span>
      </div>
      <ProgressBar percent={mem.percent ?? 0} />

      <p className="section-label" style={{ marginTop: '1.25rem' }}>CPU Load Average</p>
      <div className="load-pills">
        {['1 min', '5 min', '15 min'].map((label, i) => (
          <div className="load-pill" key={label}>
            {loadArr[i]?.toFixed(2) ?? '—'}
            <span>{label}</span>
          </div>
        ))}
      </div>

      <p className="section-label" style={{ marginTop: '1.25rem' }}>Storage</p>
      <div className="stat-grid">
        <div className="stat-box">
          <div className="stat-label">Used</div>
          <div className="stat-value">{dsk.used_gb ?? '—'} <span className="stat-unit">GB</span></div>
        </div>
        <div className="stat-box">
          <div className="stat-label">Total</div>
          <div className="stat-value">{dsk.total_gb ?? '—'} <span className="stat-unit">GB</span></div>
        </div>
      </div>
      <div style={{ marginTop: '0.5rem', display: 'flex', justifyContent: 'space-between', fontSize: '0.78rem', color: 'var(--muted)' }}>
        <span>Usage: <strong style={{ color: 'var(--text)' }}>{dsk.percent ?? 0}%</strong></span>
      </div>
      <ProgressBar percent={dsk.percent ?? 0} />

      <p className="section-label" style={{ marginTop: '1.25rem' }}>Sensors</p>
      <div className="stat-grid">
        <div className="stat-box" style={{ background: '#fef3c7', borderColor: '#fde68a' }}>
          <div className="stat-label" style={{ color: '#92400e' }}>Temperature</div>
          <div className="stat-value" style={{ color: '#b45309' }}>{temp ?? '—'} <span className="stat-unit">°C</span></div>
        </div>
        <div className="stat-box">
          <div className="stat-label">Battery (Power)</div>
          <div className="stat-value" style={{ color: bat ? (bat.status === 'CHARGING' ? '#059669' : '#111827') : '#6b7280' }}>
            {bat ? bat.percent : 'N/A'} <span className="stat-unit">{bat ? '%' : '(API Req.)'}</span>
          </div>
          {bat && (
            <div style={{ fontSize: '0.75rem', color: '#6b7280', marginTop: '0.25rem', lineHeight: '1.4' }}>
              <span style={{ fontWeight: 600, color: bat.status === 'CHARGING' ? '#059669' : '#b45309' }}>{bat.status}</span><br />
              {current_mA.toFixed(0)} mA ({power_W.toFixed(2)} W)<br />
              {cap_mAh.toFixed(0)} mAh ({cap_Wh.toFixed(2)} Wh)
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
