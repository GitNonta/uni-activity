import React from 'react';

export function AlertsBanner({ alerts }) {
  if (!alerts || alerts.length === 0) return null;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '1.5rem' }}>
      {alerts.map((alert, index) => (
        <div key={index} style={{
          background: alert.type === 'critical' ? '#fef2f2' : '#fffbeb',
          borderLeft: `4px solid ${alert.type === 'critical' ? '#ef4444' : '#f59e0b'}`,
          padding: '1rem',
          borderRadius: '6px',
          display: 'flex',
          alignItems: 'center',
          gap: '0.75rem',
          boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)'
        }}>
          {alert.type === 'critical' ? (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          ) : (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          )}
          <div>
            <h4 style={{ margin: 0, color: alert.type === 'critical' ? '#991b1b' : '#92400e', fontSize: '0.95rem' }}>
              {alert.type === 'critical' ? 'Critical Alert' : 'Warning'}
            </h4>
            <p style={{ margin: '0.25rem 0 0 0', color: alert.type === 'critical' ? '#b91c1c' : '#b45309', fontSize: '0.85rem' }}>
              {alert.message}
            </p>
          </div>
        </div>
      ))}
    </div>
  );
}
