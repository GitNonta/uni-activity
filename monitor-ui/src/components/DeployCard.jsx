import React, { useEffect, useRef } from 'react';

export function DeployCard({ deployLog, sshSessions = [], sftpSessions = 0, selectedEvent, onBack }) {
  const consoleRef = useRef(null);

  // Auto-scroll to bottom of logs on update
  useEffect(() => {
    if (consoleRef.current) {
      consoleRef.current.scrollTop = consoleRef.current.scrollHeight;
    }
  }, [deployLog]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem', minHeight: 'calc(100vh - 180px)' }}>
      {/* Session Access Panel (Hidden when viewing specific deployment details) */}
      {!selectedEvent && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
        {/* SSH Card */}
        <div className="card" style={{ padding: '1.25rem', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.75rem' }}>
            <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '32px', height: '32px', background: '#eff6ff', borderRadius: '6px' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
                <line x1="6" y1="6" x2="6.01" y2="6" />
                <line x1="6" y1="18" x2="6.01" y2="18" />
              </svg>
            </span>
            <div>
              <h3 style={{ margin: 0, fontSize: '0.875rem', fontWeight: 600, color: '#334155' }}>SSH Connections</h3>
              <p style={{ margin: 0, fontSize: '0.75rem', color: '#64748b' }}>Active SSH daemon sessions</p>
            </div>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem', maxHeight: '100px', overflowY: 'auto' }}>
            {sshSessions.length === 0 ? (
              <span style={{ fontSize: '0.85rem', color: '#94a3b8', fontStyle: 'italic' }}>No active SSH sessions</span>
            ) : (
              sshSessions.map((sess, idx) => (
                <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', color: '#0f172a', fontWeight: 500, background: '#f8fafc', padding: '0.25rem 0.5rem', borderRadius: '4px', borderLeft: '3px solid #10b981' }}>
                  <span style={{ width: '6px', height: '6px', background: '#10b981', borderRadius: '50%' }}></span>
                  {sess}
                </div>
              ))
            )}
          </div>
        </div>

        {/* SFTP Card */}
        <div className="card" style={{ padding: '1.25rem', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.75rem' }}>
            <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '32px', height: '32px', background: '#fffbeb', borderRadius: '6px' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="16 16 12 12 8 16" />
                <line x1="12" y1="12" x2="12" y2="21" />
                <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
              </svg>
            </span>
            <div>
              <h3 style={{ margin: 0, fontSize: '0.875rem', fontWeight: 600, color: '#334155' }}>SFTP Subsystem</h3>
              <p style={{ margin: 0, fontSize: '0.75rem', color: '#64748b' }}>Active SFTP file transfer sessions</p>
            </div>
          </div>
          <div style={{ display: 'flex', alignItems: 'baseline', gap: '0.5rem' }}>
            <span style={{ fontSize: '1.875rem', fontWeight: 700, color: sftpSessions > 0 ? '#d97706' : '#64748b' }}>
              {sftpSessions}
            </span>
            <span style={{ fontSize: '0.85rem', color: '#94a3b8' }}>session(s) active</span>
          </div>
        </div>
      </div>
      )}

      {/* Top Back Navigation (Moved outside the box) */}
      {selectedEvent && (
        <div style={{ marginBottom: '-0.5rem' }}>
          <button 
            onClick={onBack}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', background: 'transparent', border: 'none', color: '#94a3b8', fontSize: '0.85rem', fontWeight: 600, cursor: 'pointer', padding: 0 }}
          >
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Events
          </button>
        </div>
      )}

      {/* Render-like Event Summary Header */}
      {selectedEvent && (
        <div style={{ background: '#0a0a0a', border: '1px solid #262626', borderRadius: '6px', padding: '1rem', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          
          {/* Header row: Time and Status and Rollback button */}
          <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                <span style={{ fontSize: '0.9rem', color: '#e5e5e5' }}>{selectedEvent.timestamp}</span>
                <span style={{ 
                  background: selectedEvent.type === 'failed' ? '#7f1d1d' : (selectedEvent.type === 'success' ? '#064e3b' : '#1e293b'), 
                  color: selectedEvent.type === 'failed' ? '#fca5a5' : (selectedEvent.type === 'success' ? '#6ee7b7' : '#cbd5e1'), 
                  padding: '0.15rem 0.4rem', 
                  borderRadius: '2px', 
                  fontSize: '0.75rem', 
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '4px'
                }}>
                  {selectedEvent.type === 'failed' ? (
                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M6 18L18 6M6 6l12 12"/></svg>
                  ) : selectedEvent.type === 'success' ? (
                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"/></svg>
                  ) : null}
                  {selectedEvent.type === 'failed' ? 'Failed' : (selectedEvent.type === 'success' ? 'Succeeded' : 'Started')}
                </span>
              </div>
              
              {/* Details row: Hash and Message */}
              <div style={{ fontSize: '0.85rem', display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                <span style={{ fontFamily: 'monospace', color: '#a3a3a3' }}>{selectedEvent.hash}</span>
                <span style={{ color: '#a3a3a3' }}>{selectedEvent.message}</span>
              </div>
            </div>

            {/* Rollback button on the right */}
            <button style={{ 
              background: 'transparent', 
              border: '1px solid #404040', 
              color: '#a3a3a3', 
              padding: '0.4rem 0.75rem', 
              borderRadius: '4px', 
              fontSize: '0.75rem', 
              display: 'flex', 
              alignItems: 'center', 
              gap: '0.4rem',
              cursor: 'pointer'
            }}>
              <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
              Rollback
            </button>
          </div>
          
          {/* Status Message Block (with left border) */}
          <div style={{ 
            marginTop: '0.5rem', 
            paddingLeft: '0.75rem', 
            borderLeft: `2px solid ${selectedEvent.type === 'failed' ? '#ef4444' : (selectedEvent.type === 'success' ? '#10b981' : '#3b82f6')}`,
            display: 'flex',
            flexDirection: 'column',
            gap: '0.25rem'
          }}>
            <div style={{ fontSize: '0.9rem', fontWeight: 600, color: '#f5f5f5' }}>
              {selectedEvent.type === 'failed' ? 'Exited with status 255 while running your code.' : selectedEvent.detail}
            </div>
            <div style={{ fontSize: '0.85rem', color: '#a3a3a3' }}>
              {selectedEvent.type === 'failed' ? 'Read our docs for common ways to troubleshoot your deploy.' : 'View the logs below for more details.'}
            </div>
          </div>
        </div>
      )}

      {/* Terminal Log Console */}
      <div className="card" style={{ padding: '1.5rem', background: '#0f172a', color: '#f8fafc', fontFamily: 'monospace', borderRadius: '8px', flex: 1, display: 'flex', flexDirection: 'column', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)' }}>
        <div style={{ borderBottom: '1px solid #334155', paddingBottom: '0.75rem', marginBottom: '1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <span style={{ display: 'flex', width: '10px', height: '10px', background: '#ef4444', borderRadius: '50%' }}></span>
            <span style={{ display: 'flex', width: '10px', height: '10px', background: '#f59e0b', borderRadius: '50%' }}></span>
            <span style={{ display: 'flex', width: '10px', height: '10px', background: '#10b981', borderRadius: '50%' }}></span>
            <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#94a3b8', marginLeft: '0.5rem' }}>SFTP Deployment Console (Real-Time)</span>
          </div>
          <span style={{ fontSize: '0.75rem', color: '#38bdf8', background: '#1e293b', padding: '0.25rem 0.5rem', borderRadius: '4px' }}>
            deploy.log
          </span>
        </div>
        <div 
          ref={consoleRef}
          style={{ overflowY: 'auto', flex: 1, maxHeight: '500px', padding: '0.5rem', background: '#020617', borderRadius: '6px' }}
        >
          <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-all', fontSize: '0.85rem', lineHeight: '1.6', color: '#cbd5e1' }}>
            {deployLog || 'No deployment log found. Run a deployment script to generate logs.'}
          </pre>
        </div>
      </div>
    </div>
  );
}
