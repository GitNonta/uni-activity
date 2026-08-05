import React, { useEffect, useRef, useState } from 'react';

export function DeployCard({ deployLog, sshSessions = [], sftpSessions = 0, selectedEvent, onBack }) {
  const consoleRef = useRef(null);
  const [logSearchText, setLogSearchText] = useState('');
  const [logFilter, setLogFilter] = useState('All logs');
  const [logOrder, setLogOrder] = useState('Ascending');
  const [isAllLogsMenuOpen, setIsAllLogsMenuOpen] = useState(false);
  const [isMoreMenuOpen, setIsMoreMenuOpen] = useState(false);
  
  // Close menus when clicking outside
  useEffect(() => {
    const handleClickOutside = () => {
      setIsAllLogsMenuOpen(false);
      setIsMoreMenuOpen(false);
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);
  // Auto-scroll to bottom of logs on update
  useEffect(() => {
    if (consoleRef.current && logOrder === 'Ascending') {
      consoleRef.current.scrollTop = consoleRef.current.scrollHeight;
    }
  }, [deployLog, logOrder]);

  // Process logs
  const logLines = deployLog ? deployLog.split('\n').filter(line => line.trim()) : [];
  let processedLogs = logLines.map((line, idx) => ({
    id: idx,
    time: `06:57:${String(50 + (idx % 10)).padStart(2, '0')} PM`,
    text: line
  }));
  
  if (logSearchText) {
    processedLogs = processedLogs.filter(log => log.text.toLowerCase().includes(logSearchText.toLowerCase()));
  }
  
  if (logOrder === 'Descending') {
    processedLogs = [...processedLogs].reverse();
  }

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

      {/* Log Console Area */}
      {selectedEvent ? (
        <div style={{ display: 'flex', flexDirection: 'column', flex: 1, background: '#0a0a0a', border: '1px solid #262626', borderRadius: '6px', overflow: 'hidden' }}>
          {/* Terminal Log Console (Render Style) */}
          
          {/* Log Viewer Toolbar */}
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: '1px solid #262626', background: '#0a0a0a', padding: '0.5rem' }}>
            
            {/* Left: All logs & Search */}
            <div style={{ display: 'flex', alignItems: 'center', height: '32px' }}>
              <div style={{ position: 'relative', height: '100%' }}>
                <button 
                  onClick={(e) => { e.stopPropagation(); setIsAllLogsMenuOpen(!isAllLogsMenuOpen); setIsMoreMenuOpen(false); }}
                  style={{ 
                    display: 'flex', alignItems: 'center', gap: '0.5rem', 
                    background: 'transparent', border: 'none', borderRight: '1px solid #262626',
                    color: '#f5f5f5', fontSize: '0.85rem', padding: '0 1rem', height: '100%', cursor: 'pointer' 
                  }}>
                  {logFilter}
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                {isAllLogsMenuOpen && (
                  <div style={{ position: 'absolute', top: '100%', left: 0, marginTop: '4px', background: '#0f0f0f', border: '1px solid #262626', borderRadius: '4px', width: '200px', padding: '0.5rem', zIndex: 10, boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.5)' }}>
                    {['All logs', 'Application logs', 'Build logs'].map(opt => (
                      <div 
                        key={opt}
                        onClick={(e) => { e.stopPropagation(); setLogFilter(opt); setIsAllLogsMenuOpen(false); }}
                        style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', padding: '0.5rem', cursor: 'pointer', borderRadius: '4px', background: logFilter === opt ? '#1a1a1a' : 'transparent', borderLeft: logFilter === opt ? '2px solid #a855f7' : '2px solid transparent' }}
                      >
                        <div style={{ width: '12px', height: '12px', borderRadius: '50%', border: logFilter === opt ? '4px solid #a855f7' : '1px solid #525252' }}></div>
                        <span style={{ fontSize: '0.85rem', color: logFilter === opt ? '#e879f9' : '#d4d4d8' }}>{opt}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
              <div style={{ display: 'flex', alignItems: 'center', padding: '0 1rem', color: '#a3a3a3' }}>
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input 
                  type="text" 
                  placeholder="Search logs" 
                  value={logSearchText}
                  onChange={(e) => setLogSearchText(e.target.value)}
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
                <div style={{ position: 'relative', height: '100%' }}>
                  <button 
                    onClick={(e) => { e.stopPropagation(); setIsMoreMenuOpen(!isMoreMenuOpen); setIsAllLogsMenuOpen(false); }}
                    style={{ background: isMoreMenuOpen ? '#fff' : 'transparent', border: 'none', color: isMoreMenuOpen ? '#000' : '#a3a3a3', padding: '0 0.5rem', height: '100%', cursor: 'pointer', display: 'flex', alignItems: 'center', transition: 'all 0.2s' }}>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                  </button>
                  {isMoreMenuOpen && (
                    <div style={{ position: 'absolute', top: '100%', right: 0, marginTop: '4px', background: '#0f0f0f', border: '1px solid #262626', borderRadius: '4px', width: '220px', zIndex: 10, boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.5)' }}>
                      <div 
                        onClick={(e) => { 
                          e.stopPropagation(); 
                          navigator.clipboard.writeText(processedLogs.map(l => l.text).join('\n')); 
                          setIsMoreMenuOpen(false); 
                          alert('Logs copied to clipboard!'); 
                        }}
                        style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.75rem 1rem', cursor: 'pointer', borderBottom: '1px solid #262626', color: '#d4d4d8', fontSize: '0.85rem' }}
                      >
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                          Copy logs
                        </div>
                        <span style={{ fontSize: '0.7rem', color: '#737373', display: 'flex', gap: '2px' }}><span>^</span><span>C</span></span>
                      </div>
                      <div style={{ padding: '0.75rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#737373', letterSpacing: '0.05em', marginBottom: '0.5rem' }}>ORDER BY</div>
                        <div 
                          onClick={(e) => { e.stopPropagation(); setLogOrder('Ascending'); }}
                          style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.4rem 0', cursor: 'pointer', color: '#d4d4d8', fontSize: '0.85rem' }}
                        >
                          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7"/></svg>
                            Ascending
                          </div>
                          {logOrder === 'Ascending' && <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>}
                        </div>
                        <div 
                          onClick={(e) => { e.stopPropagation(); setLogOrder('Descending'); }}
                          style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.4rem 0', cursor: 'pointer', color: '#d4d4d8', fontSize: '0.85rem' }}
                        >
                          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"/></svg>
                            Descending
                          </div>
                          {logOrder === 'Descending' && <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>}
                        </div>
                      </div>
                      <div style={{ borderTop: '1px solid #262626', borderBottom: '1px solid #262626', padding: '0.75rem 1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer', color: '#d4d4d8', fontSize: '0.85rem' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                          <span>Display: <span style={{ fontWeight: 600 }}>Expand everything</span></span>
                        </div>
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/></svg>
                      </div>
                      <div style={{ padding: '0.75rem 1rem', display: 'flex', alignItems: 'center', gap: '0.75rem', cursor: 'pointer', color: '#d4d4d8', fontSize: '0.85rem' }}>
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Theme Settings
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Log Lines */}
          <div 
            ref={consoleRef}
            style={{ overflowY: 'auto', flex: 1, maxHeight: '600px', background: '#0a0a0a', padding: '1rem 0' }}
          >
            {processedLogs.length > 0 ? processedLogs.map((log) => (
              <div key={log.id} style={{ display: 'flex', fontFamily: 'monospace', fontSize: '0.8rem', lineHeight: '1.5' }}>
                <div style={{ width: '100px', flexShrink: 0, paddingLeft: '1rem', color: '#737373', userSelect: 'none' }}>
                  {log.time}
                </div>
                <div style={{ color: '#d4d4d8', paddingLeft: '1rem', flex: 1, wordBreak: 'break-all', whiteSpace: 'pre-wrap' }}>
                  {log.text}
                </div>
              </div>
            )) : (
              <div style={{ display: 'flex', fontFamily: 'monospace', fontSize: '0.8rem', lineHeight: '1.5' }}>
                <div style={{ width: '100px', flexShrink: 0, paddingLeft: '1rem', color: '#737373', userSelect: 'none' }}>
                  --:--:--
                </div>
                <div style={{ color: '#a3a3a3', paddingLeft: '1rem', flex: 1 }}>
                  {logSearchText ? 'No matching logs found.' : 'No deployment log found. Run a deployment script to generate logs.'}
                </div>
              </div>
            )}
          </div>
        </div>
      ) : (
        <div className="card" style={{ padding: '1.5rem', background: '#0f172a', color: '#f8fafc', fontFamily: 'monospace', borderRadius: '8px', flex: 1, display: 'flex', flexDirection: 'column', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)' }}>
          {/* Old Terminal Log Console for generic Deploy Logs view */}
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
      )}
    </div>
  );
}
