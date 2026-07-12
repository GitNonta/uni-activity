import React from 'react';

export function DeployCard({ deployLog }) {
  return (
    <div className="card" style={{ padding: '1.5rem', background: '#0f172a', color: '#f8fafc', fontFamily: 'monospace', borderRadius: '8px', minHeight: 'calc(100vh - 200px)', display: 'flex', flexDirection: 'column', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)' }}>
      <div style={{ borderBottom: '1px solid #334155', paddingBottom: '0.75rem', marginBottom: '1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="16 16 12 12 8 16" />
            <line x1="12" y1="12" x2="12" y2="21" />
            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
            <polyline points="16 16 12 12 8 16" />
          </svg>
          <span style={{ fontSize: '1rem', fontWeight: 600, color: '#f8fafc' }}>SFTP Deployment Console Log</span>
        </div>
        <span style={{ fontSize: '0.75rem', color: '#94a3b8', background: '#1e293b', padding: '0.25rem 0.5rem', borderRadius: '4px' }}>
          deploy.log
        </span>
      </div>
      <pre style={{ margin: 0, overflowY: 'auto', whiteSpace: 'pre-wrap', wordBreak: 'break-all', flex: 1, fontSize: '0.85rem', lineHeight: '1.5', color: '#cbd5e1' }}>
        {deployLog || 'No deployment log found. Run a deployment script to generate logs.'}
      </pre>
    </div>
  );
}
