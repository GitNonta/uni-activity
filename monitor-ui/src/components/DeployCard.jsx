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
              {selectedEvent.detail || (selectedEvent.type === 'failed' ? 'Exited with an error while running your code.' : 'Deployment process executed.')}
            </div>
            <div style={{ fontSize: '0.85rem', color: '#a3a3a3' }}>
              {selectedEvent.type === 'failed' ? 'Read our docs for common ways to troubleshoot your deploy.' : 'View the logs below for more details.'}
            </div>
          </div>
        </div>
      )}

      {/* Terminal Log Console (Render Style) */}
      <div style={{ display: 'flex', flexDirection: 'column', flex: 1, background: '#0a0a0a', border: '1px solid #262626', borderRadius: '6px', overflow: 'hidden' }}>
        
        {/* Log Viewer Toolbar */}
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: '1px solid #262626', background: '#0a0a0a', padding: '0.5rem' }}>
          
          {/* Left: All logs & Search */}
          <div style={{ display: 'flex', alignItems: 'center', height: '32px' }}>
            <button style={{ 
              display: 'flex', alignItems: 'center', gap: '0.5rem', 
              background: 'transparent', border: 'none', borderRight: '1px solid #262626',
              color: '#f5f5f5', fontSize: '0.85rem', padding: '0 1rem', height: '100%', cursor: 'pointer' 
            }}>
              All logs
              <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div style={{ display: 'flex', alignItems: 'center', padding: '0 1rem', color: '#a3a3a3' }}>
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              <input 
                type="text" 
                placeholder="Search logs" 
                style={{ background: 'transparent', border: 'none', color: '#f5f5f5', outline: 'none', marginLeft: '0.5rem', fontSize: '0.85rem', width: '150px' }} 
              />
            </div>
          </div>

          {/* Right: Tools */}
          <div style={{ display: 'flex', alignItems: 'center', height: '32px' }}>
            <button style={{ 
              display: 'flex', alignItems: 'center', gap: '0.5rem', 
              background: 'transparent', border: '1px solid #262626', borderRadius: '4px',
              color: '#e5e5e5', fontSize: '0.8rem', padding: '0 0.75rem', height: '100%', cursor: 'pointer', marginRight: '0.5rem'
            }}>
              <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Aug 5, 6:56 PM - 6:59 PM
              <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" style={{ marginLeft: '0.25rem' }}><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div style={{ border: '1px solid #262626', borderRadius: '4px', display: 'flex', height: '100%', alignItems: 'center' }}>
              <div style={{ padding: '0 0.5rem', color: '#a3a3a3', fontSize: '0.75rem', borderRight: '1px solid #262626', display: 'flex', alignItems: 'center', height: '100%' }}>
                GMT+7
              </div>
              <button style={{ background: 'transparent', border: 'none', color: '#a3a3a3', padding: '0 0.5rem', height: '100%', cursor: 'pointer', borderRight: '1px solid #262626', display: 'flex', alignItems: 'center' }}>
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
              </button>
              <button style={{ background: 'transparent', border: 'none', color: '#a3a3a3', padding: '0 0.5rem', height: '100%', cursor: 'pointer', display: 'flex', alignItems: 'center' }}>
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
              </button>
            </div>
          </div>
        </div>

        {/* Log Lines */}
        <div 
          ref={consoleRef}
          style={{ overflowY: 'auto', flex: 1, maxHeight: '600px', background: '#0a0a0a', padding: '1rem 0' }}
        >
          {deployLog ? deployLog.split('\n').map((line, idx) => {
            if (!line.trim()) return null;
            return (
              <div key={idx} style={{ display: 'flex', fontFamily: 'monospace', fontSize: '0.8rem', lineHeight: '1.5' }}>
                <div style={{ width: '100px', flexShrink: 0, paddingLeft: '1rem', color: '#737373', userSelect: 'none' }}>
                  06:57:{String(50 + (idx % 10)).padStart(2, '0')} PM
                </div>
                <div style={{ color: '#d4d4d8', paddingLeft: '1rem', flex: 1, wordBreak: 'break-all', whiteSpace: 'pre-wrap' }}>
                  {line}
                </div>
              </div>
            );
          }) : (
            <div style={{ display: 'flex', fontFamily: 'monospace', fontSize: '0.8rem', lineHeight: '1.5' }}>
              <div style={{ width: '100px', flexShrink: 0, paddingLeft: '1rem', color: '#737373', userSelect: 'none' }}>
                --:--:--
              </div>
              <div style={{ color: '#a3a3a3', paddingLeft: '1rem', flex: 1 }}>
                No deployment log found. Run a deployment script to generate logs.
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
