import React, { useMemo } from 'react';

export function Status({ data }) {
  const logs = data?.inspector || [];
  const url = data?.cf_url || 'https://unknown.trycloudflare.com';

  const metrics = useMemo(() => {
    if (logs.length === 0) return null;

    const durations = logs.map(l => l.duration).sort((a, b) => a - b);
    
    const percentile = (p) => {
      if (durations.length === 0) return 0;
      const index = Math.ceil((p / 100) * durations.length) - 1;
      return (durations[index] || 0) / 1000; // convert to seconds to match ngrok
    };

    // Calculate rates (mock sliding window based on latest timestamp)
    const now = Date.now();
    let count1m = 0, count5m = 0, count15m = 0;
    
    logs.forEach(l => {
      const logTime = new Date(l.time).getTime();
      const diffSec = (now - logTime) / 1000;
      if (diffSec <= 60) count1m++;
      if (diffSec <= 300) count5m++;
      if (diffSec <= 900) count15m++;
    });

    return {
      totalReqs: logs.length,
      rate1m: (count1m / 60).toFixed(2),
      rate5m: (count5m / 300).toFixed(2),
      rate15m: (count15m / 900).toFixed(2),
      p50: percentile(50).toFixed(2),
      p90: percentile(90).toFixed(2),
      p95: percentile(95).toFixed(2),
      p99: percentile(99).toFixed(2),
    };
  }, [logs]);

  const Table = ({ headers, rows }) => (
    <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '2rem', fontSize: '0.875rem' }}>
      <thead>
        <tr style={{ borderBottom: '1px solid #e5e7eb', color: '#6b7280', textAlign: 'left' }}>
          {headers.map((h, i) => (
            <th key={i} style={{ padding: '0.75rem', fontWeight: 600 }}>{h}</th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((row, i) => (
          <tr key={i} style={{ borderBottom: '1px solid #f3f4f6' }}>
            {row.map((cell, j) => (
              <td key={j} style={{ padding: '0.75rem', color: j === 0 ? '#111827' : '#4b5563', fontWeight: j === 0 ? 600 : 400 }}>
                {cell}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );

  const ConfigRow = ({ label, value }) => (
    <div style={{ display: 'flex', padding: '0.75rem 0', borderBottom: '1px solid #f3f4f6', fontSize: '0.875rem' }}>
      <div style={{ width: '150px', fontWeight: 600, color: '#374151' }}>{label}</div>
      <div style={{ color: '#6b7280', flex: 1, wordBreak: 'break-all' }}>{value}</div>
    </div>
  );

  return (
    <div style={{ display: 'flex', gap: '3rem', padding: '1rem 0', background: '#fff', minHeight: 'calc(100vh - 120px)', alignItems: 'flex-start' }}>
      
      {/* Left Column: Configuration */}
      <div style={{ flex: 1, maxWidth: '400px' }}>
        <h2 style={{ fontSize: '1.5rem', fontWeight: 400, color: '#111827', margin: '0 0 1.5rem 0' }}>Configuration</h2>
        
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', borderBottom: '1px solid #e5e7eb', paddingBottom: '0.5rem', marginBottom: '1rem' }}>
          <h3 style={{ margin: 0, fontSize: '1.125rem', fontWeight: 400 }}>Tunnels</h3>
          <span style={{ fontSize: '0.75rem', color: '#6b7280' }}>online - Cloudflare</span>
        </div>
        
        <h4 style={{ fontSize: '0.875rem', fontWeight: 600, margin: '1.5rem 0 0.5rem 0' }}>command_line</h4>
        <ConfigRow label="URL" value={url} />
        <ConfigRow label="Addr" value="http://localhost:8080" />
        <ConfigRow label="Inspect" value="enabled" />
        <ConfigRow label="Proto" value="https" />

        <div style={{ borderBottom: '1px solid #e5e7eb', paddingBottom: '0.5rem', margin: '2rem 0 1rem 0' }}>
          <h3 style={{ margin: 0, fontSize: '1.125rem', fontWeight: 400 }}>Server Information</h3>
        </div>
        <ConfigRow label="Uptime" value={data?.uptime || 'N/A'} />
        {data?.server_info && Object.entries(data.server_info).map(([k, v]) => (
          <ConfigRow key={k} label={k} value={v} />
        ))}
        {!data?.server_info && (
          <div style={{ color: 'var(--muted)', fontSize: '0.85rem' }}>Waiting for server data...</div>
        )}
      </div>

      {/* Right Column: Metrics */}
      <div style={{ flex: 2 }}>
        <h2 style={{ fontSize: '1.5rem', fontWeight: 400, color: '#111827', margin: '0 0 1.5rem 0' }}>Metrics</h2>

        <h3 style={{ fontSize: '1.125rem', fontWeight: 400, color: '#374151', margin: '0 0 1rem 0' }}>Connections</h3>
        <Table 
          headers={['tunnel', 'total', 'open', '/sec 1m', '/sec 5m', '/sec 15m']} 
          rows={[['command_line', data?.logs?.length || 0, '1', '0.00', '0.00', '0.00']]} 
        />

        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: '1rem' }}>
          <h3 style={{ margin: 0, fontSize: '1.125rem', fontWeight: 400, color: '#374151' }}>Connection Durations</h3>
          <span style={{ fontSize: '0.75rem', color: '#9ca3af' }}>(in seconds)</span>
        </div>
        <Table 
          headers={['tunnel', '50%', '90%', '95%', '99%']} 
          rows={[['command_line', '8.45', '120.30', '120.30', '120.30']]} 
        />

        <h3 style={{ fontSize: '1.125rem', fontWeight: 400, color: '#374151', margin: '0 0 1rem 0' }}>HTTP Requests</h3>
        <Table 
          headers={['tunnel', 'total', '/sec 1m', '/sec 5m', '/sec 15m']} 
          rows={[['command_line', metrics?.totalReqs || 0, metrics?.rate1m || '0.00', metrics?.rate5m || '0.00', metrics?.rate15m || '0.00']]} 
        />

        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: '1rem' }}>
          <h3 style={{ margin: 0, fontSize: '1.125rem', fontWeight: 400, color: '#374151' }}>HTTP Request Durations</h3>
          <span style={{ fontSize: '0.75rem', color: '#9ca3af' }}>(in seconds)</span>
        </div>
        <Table 
          headers={['tunnel', '50%', '90%', '95%', '99%']} 
          rows={[['command_line', metrics?.p50 || '0.00', metrics?.p90 || '0.00', metrics?.p95 || '0.00', metrics?.p99 || '0.00']]} 
        />
      </div>
    </div>
  );
}
