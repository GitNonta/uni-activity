import { useState, Component } from 'react'

// Error Boundary — ป้องกันจอขาวทั้งหน้าเมื่อ Inspector crash
class InspectorErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false, error: null }
  }
  static getDerivedStateFromError(error) {
    return { hasError: true, error }
  }
  render() {
    if (this.state.hasError) {
      return (
        <div style={{ padding: '2rem', textAlign: 'center', color: '#ef4444' }}>
          <strong>Inspector Error:</strong> {this.state.error?.message}
          <br />
          <button
            onClick={() => this.setState({ hasError: false, error: null })}
            style={{ marginTop: '1rem', padding: '0.5rem 1rem', cursor: 'pointer', borderRadius: '0.375rem', border: '1px solid #ef4444', color: '#ef4444', background: 'transparent' }}
          >Retry</button>
        </div>
      )
    }
    return this.props.children
  }
}

export function InspectorInner({ logs }) {
  const [selectedLogId, setSelectedLogId] = useState(null)
  const [activeTab, setActiveTab] = useState('summary')
  const [filterType, setFilterType] = useState('all') // 'all', 'http', 'artisan', 'shell'
  const [copied, setCopied] = useState(false)

  const safeLogs = Array.isArray(logs) ? logs : []

  const getLogId = (log, index) => {
    if (!log) return `log-${index}`;
    return log.id || `act-${log.time || ''}-${log.path || ''}-${index}`;
  }

  const getLogType = (log) => {
    if (!log) return 'http';
    if (log.method === 'SHELL') return 'shell';
    if (log.method === 'ARTISAN') return 'artisan';
    return 'http';
  }

  const filteredLogs = safeLogs.filter(log => {
    if (filterType === 'all') return true;
    return getLogType(log) === filterType;
  });

  const selectedLog = filteredLogs.find((l, idx) => getLogId(l, idx) === selectedLogId) || filteredLogs[0] || null

  // Auto-select first log if none selected and logs exist
  if (selectedLog) {
    const curId = getLogId(selectedLog, 0);
    if (!selectedLogId) {
      setSelectedLogId(curId);
    }
  }

  const getStatusColor = (log) => {
    if (!log) return '#6b7280';
    const type = getLogType(log);
    if (type === 'shell' || type === 'artisan') {
      return log.status === 0 ? '#10b981' : '#ef4444';
    }
    const status = Number(log.status) || 200;
    if (status >= 200 && status < 300) return '#10b981';
    if (status >= 300 && status < 400) return '#3b82f6';
    if (status >= 400 && status < 500) return '#f59e0b';
    return '#ef4444';
  }

  const getMethodBadgeStyle = (method) => {
    let bg = '#e5e7eb';
    let fg = '#374151';
    
    if (method === 'SHELL') {
      bg = '#4b5563';
      fg = '#ffffff';
    } else if (method === 'ARTISAN') {
      bg = '#8b5cf6';
      fg = '#ffffff';
    } else if (method === 'GET') {
      bg = '#d1fae5';
      fg = '#065f46';
    } else if (method === 'POST') {
      bg = '#dbeafe';
      fg = '#1e40af';
    } else if (['PUT', 'PATCH'].includes(method)) {
      bg = '#fef3c7';
      fg = '#92400e';
    } else if (method === 'DELETE') {
      bg = '#fee2e2';
      fg = '#991b1b';
    }
    
    return {
      background: bg,
      color: fg,
      padding: '0.125rem 0.375rem',
      borderRadius: '0.25rem',
      fontSize: '0.75rem',
      fontWeight: 'bold',
      marginRight: '0.5rem',
      textTransform: 'uppercase',
      display: 'inline-block'
    };
  }

  const copyToClipboard = (text) => {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  }

  const formatHeaders = (headers) => {
    if (!headers || typeof headers !== 'object') return null;
    const entries = Object.entries(headers);
    if (entries.length === 0) return null;

    return (
      <div style={{ display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '0.5rem 1rem', background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e5e7eb', fontSize: '0.85rem' }}>
        {entries.map(([k, v]) => (
          <div key={k} style={{ display: 'contents' }}>
            <span style={{ fontWeight: 600, color: '#4b5563', fontFamily: 'monospace' }}>{k}:</span>
            <span style={{ color: '#111827', wordBreak: 'break-all', fontFamily: 'monospace' }}>{Array.isArray(v) ? v.join(', ') : String(v)}</span>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', height: 'calc(100vh - 120px)', background: '#fff', borderRadius: '0.5rem', border: '1px solid #e5e7eb', overflow: 'hidden' }}>
      
      {/* Left Pane - List */}
      <div style={{ width: '380px', borderRight: '1px solid #e5e7eb', display: 'flex', flexDirection: 'column', background: '#fafafa' }}>
        <div style={{ padding: '1rem', borderBottom: '1px solid #e5e7eb', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fff' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
            <h3 style={{ margin: 0, fontSize: '1rem', color: '#111827', fontWeight: 600 }}>Server Activity</h3>
          </div>
          <span style={{ fontSize: '0.75rem', color: '#4b5563', background: '#f3f4f6', padding: '0.2rem 0.6rem', borderRadius: '1rem', fontWeight: 600 }}>
            {filteredLogs.length} / {safeLogs.length}
          </span>
        </div>

        {/* Filter Bar */}
        <div style={{ display: 'flex', gap: '0.25rem', padding: '0.5rem', background: '#f3f4f6', borderBottom: '1px solid #e5e7eb' }}>
          {['all', 'http', 'artisan', 'shell'].map(type => (
            <button
              key={type}
              onClick={() => setFilterType(type)}
              style={{
                flex: 1,
                padding: '0.375rem 0.25rem',
                fontSize: '0.75rem',
                fontWeight: filterType === type ? '600' : '500',
                border: 'none',
                background: filterType === type ? '#1e293b' : 'transparent',
                color: filterType === type ? '#fff' : '#4b5563',
                borderRadius: '0.25rem',
                cursor: 'pointer',
                textTransform: 'uppercase',
                transition: 'all 0.15s ease'
              }}
            >
              {type}
            </button>
          ))}
        </div>

        <div style={{ overflowY: 'auto', flex: 1 }}>
          {filteredLogs.length === 0 ? (
            <div style={{ padding: '3rem 1rem', textAlign: 'center', color: '#9ca3af', fontSize: '0.875rem' }}>
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ margin: '0 auto 0.5rem' }}>
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              No server activity recorded yet
            </div>
          ) : (
            filteredLogs.map((log, idx) => {
              const logId = getLogId(log, idx);
              const isSelected = selectedLogId === logId;
              const logType = getLogType(log);
              return (
                <div 
                  key={logId} 
                  onClick={() => setSelectedLogId(logId)}
                  style={{ 
                    padding: '0.75rem 1rem', 
                    borderBottom: '1px solid #f1f5f9',
                    cursor: 'pointer',
                    background: isSelected ? '#1e293b' : '#fff',
                    color: isSelected ? '#fff' : '#111827',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    transition: 'background 0.1s ease'
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis', paddingRight: '0.5rem', flex: 1 }}>
                    <span style={getMethodBadgeStyle(log.method)}>{log.method}</span>
                    <span style={{ fontWeight: 600, fontSize: '0.85rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={log.path || log.url}>
                      {log.path || log.url || '/'}
                    </span>
                  </div>
                  <div style={{ textAlign: 'right', flexShrink: 0 }}>
                    <div style={{ fontSize: '0.8rem', color: isSelected ? '#34d399' : getStatusColor(log), fontWeight: 700 }}>
                      {logType === 'http' ? (log.status || 200) : `exit: ${log.status}`}
                    </div>
                    <div style={{ fontSize: '0.7rem', color: isSelected ? '#94a3b8' : '#64748b' }}>
                      {log.duration > 0 ? `${Number(log.duration).toFixed(1)}ms` : '<1ms'}
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>

      {/* Right Pane - Details */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden', background: '#f8fafc' }}>
        {selectedLog ? (
          <>
            <div style={{ padding: '1.25rem 1.5rem', borderBottom: '1px solid #e2e8f0', background: '#fff' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem', color: '#64748b', fontSize: '0.8rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  <span>{new Date(selectedLog.time || Date.now()).toLocaleString()}</span>
                </div>
                <div style={{ display: 'flex', gap: '1rem', alignItems: 'center' }}>
                  <span>Duration: <strong>{Number(selectedLog.duration || 0).toFixed(2)} ms</strong></span>
                  <span>Client IP: <strong>{selectedLog.ip || '127.0.0.1'}</strong></span>
                </div>
              </div>

              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                <span style={getMethodBadgeStyle(selectedLog.method)}>{selectedLog.method}</span>
                <h2 style={{ margin: 0, fontSize: '1.15rem', fontWeight: 700, color: '#0f172a', fontFamily: getLogType(selectedLog) !== 'http' ? 'monospace' : 'inherit', wordBreak: 'break-all' }}>
                  {selectedLog.path || selectedLog.url || '/'}
                </h2>
              </div>
            </div>
            
            {/* Tabs */}
            <div style={{ display: 'flex', borderBottom: '1px solid #e2e8f0', padding: '0 1rem', background: '#fff' }}>
              {[
                { id: 'summary', label: 'Overview' },
                { id: 'headers', label: 'Headers' },
                { id: 'payload', label: 'Payload' },
                { id: 'raw', label: 'Raw JSON' }
              ].map(tab => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  style={{
                    padding: '0.75rem 1.25rem',
                    background: 'none',
                    border: 'none',
                    borderBottom: activeTab === tab.id ? '2px solid #2563eb' : '2px solid transparent',
                    color: activeTab === tab.id ? '#2563eb' : '#64748b',
                    fontWeight: activeTab === tab.id ? 600 : 500,
                    cursor: 'pointer',
                    fontSize: '0.875rem',
                    transition: 'all 0.15s ease'
                  }}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            {/* Tab Content */}
            <div style={{ padding: '1.5rem', overflowY: 'auto', flex: 1 }}>
              
              {/* Tab: Overview */}
              {activeTab === 'summary' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
                  
                  {/* Status Banner */}
                  <div style={{ 
                    display: 'flex', 
                    alignItems: 'center', 
                    justifyContent: 'space-between',
                    padding: '1rem 1.25rem', 
                    borderRadius: '0.5rem', 
                    background: '#fff', 
                    border: '1px solid #e2e8f0',
                    borderLeft: `4px solid ${getStatusColor(selectedLog)}`
                  }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                      <span style={{ 
                        width: 12, height: 12, borderRadius: '50%', 
                        background: getStatusColor(selectedLog),
                        display: 'inline-block'
                      }}></span>
                      <div>
                        <div style={{ fontWeight: 700, fontSize: '0.95rem', color: '#0f172a' }}>
                          {getLogType(selectedLog) === 'http' 
                            ? `HTTP ${selectedLog.status || 200} Response` 
                            : `Console Execution ${selectedLog.status === 0 ? 'Success' : 'Failed'}`}
                        </div>
                        <div style={{ fontSize: '0.75rem', color: '#64748b' }}>
                          {selectedLog.status >= 200 && selectedLog.status < 300 
                            ? 'Request processed successfully without errors' 
                            : selectedLog.status >= 400 
                            ? 'Request encountered client or server exception' 
                            : 'Executed with standard response'}
                        </div>
                      </div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <span style={{ 
                        padding: '0.25rem 0.6rem', 
                        borderRadius: '0.375rem', 
                        fontSize: '0.75rem', 
                        fontWeight: 700,
                        background: selectedLog.duration > 200 ? '#fee2e2' : selectedLog.duration > 50 ? '#fef3c7' : '#dcfce7',
                        color: selectedLog.duration > 200 ? '#b91c1c' : selectedLog.duration > 50 ? '#b45309' : '#15803d'
                      }}>
                        {Number(selectedLog.duration || 0).toFixed(2)} ms
                      </span>
                    </div>
                  </div>

                  {/* Key Metrics Grid */}
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ fontSize: '0.75rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase' }}>Target Path</div>
                      <div style={{ fontSize: '0.9rem', color: '#0f172a', fontWeight: 600, marginTop: '0.25rem', fontFamily: 'monospace', wordBreak: 'break-all' }}>
                        {selectedLog.path || '/'}
                      </div>
                    </div>

                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ fontSize: '0.75rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase' }}>Client Source</div>
                      <div style={{ fontSize: '0.9rem', color: '#0f172a', fontWeight: 600, marginTop: '0.25rem', fontFamily: 'monospace' }}>
                        {selectedLog.ip || '127.0.0.1'}
                      </div>
                    </div>

                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ fontSize: '0.75rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase' }}>Event Timestamp</div>
                      <div style={{ fontSize: '0.85rem', color: '#0f172a', fontWeight: 600, marginTop: '0.25rem' }}>
                        {new Date(selectedLog.time || Date.now()).toLocaleTimeString()}
                      </div>
                    </div>
                  </div>

                  {/* Full URL with Copy */}
                  {selectedLog.url && (
                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                        <span style={{ fontSize: '0.75rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase' }}>Full Request URL</span>
                        <button
                          onClick={() => copyToClipboard(selectedLog.url)}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '0.35rem',
                            border: '1px solid #cbd5e1',
                            background: '#f8fafc',
                            padding: '0.2rem 0.5rem',
                            borderRadius: '0.25rem',
                            fontSize: '0.75rem',
                            cursor: 'pointer',
                            color: '#334155'
                          }}
                        >
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                          </svg>
                          {copied ? 'Copied!' : 'Copy'}
                        </button>
                      </div>
                      <div style={{ fontSize: '0.85rem', color: '#2563eb', fontFamily: 'monospace', wordBreak: 'break-all' }}>
                        {selectedLog.url}
                      </div>
                    </div>
                  )}

                  {/* Terminal View (If Shell or Artisan) */}
                  {getLogType(selectedLog) !== 'http' ? (
                    <div style={{ 
                      background: '#0f172a', 
                      color: '#f8fafc', 
                      fontFamily: 'Courier New, Courier, monospace', 
                      borderRadius: '0.5rem', 
                      padding: '1.25rem', 
                      boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                      border: '1px solid #1e293b'
                    }}>
                      <div style={{ display: 'flex', gap: '6px', marginBottom: '1rem', borderBottom: '1px solid #334155', paddingBottom: '0.5rem' }}>
                        <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#ef4444', display: 'inline-block' }}></span>
                        <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#f59e0b', display: 'inline-block' }}></span>
                        <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#10b981', display: 'inline-block' }}></span>
                        <span style={{ marginLeft: '0.75rem', fontSize: '0.75rem', color: '#94a3b8', flex: 1, fontFamily: 'sans-serif' }}>
                          {getLogType(selectedLog) === 'shell' ? 'System Shell Console' : 'Laravel Artisan Worker'}
                        </span>
                      </div>
                      
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.85rem', lineHeight: '1.5' }}>
                        <div>
                          <span style={{ color: '#64748b' }}>[Directory]</span> <span style={{ color: '#38bdf8' }}>{selectedLog.request?.headers?.['Working-Dir'] || selectedLog.request?.headers?.['Environment'] || '/'}</span>
                        </div>
                        <div style={{ marginTop: '0.25rem', borderLeft: '3px solid #3b82f6', paddingLeft: '0.75rem' }}>
                          <span style={{ color: '#38bdf8', fontWeight: 'bold' }}>$ </span>
                          <span style={{ color: '#fff', fontWeight: 'bold' }}>{selectedLog.path}</span>
                        </div>
                        <div style={{ marginTop: '0.75rem', color: '#64748b' }}>[Output]</div>
                        <pre style={{ 
                          margin: 0, 
                          background: '#020617', 
                          padding: '0.75rem', 
                          borderRadius: '0.25rem', 
                          whiteSpace: 'pre-wrap', 
                          wordBreak: 'break-all',
                          color: selectedLog.status === 0 ? '#4ade80' : '#f87171',
                          fontSize: '0.8rem',
                          border: '1px solid #1e293b'
                        }}>
                          {selectedLog.response?.body || '(Process exited without standard output)'}
                        </pre>
                      </div>
                    </div>
                  ) : (
                    selectedLog.response?.body && (
                      <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '0.5rem', padding: '1rem' }}>
                        <div style={{ fontSize: '0.75rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.5rem' }}>Response Summary</div>
                        <pre style={{ margin: 0, fontSize: '0.85rem', color: '#334155', whiteSpace: 'pre-wrap' }}>
                          {selectedLog.response?.body}
                        </pre>
                      </div>
                    )
                  )}

                </div>
              )}

              {/* Tab: Headers */}
              {activeTab === 'headers' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                  <div>
                    <h4 style={{ margin: '0 0 0.5rem 0', color: '#0f172a', fontWeight: 600, fontSize: '0.9rem' }}>Request Properties & Headers</h4>
                    {formatHeaders(selectedLog.request?.headers) || (
                      <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0', color: '#64748b', fontSize: '0.85rem' }}>
                        No specific custom headers attached for this request.
                      </div>
                    )}
                  </div>

                  <div>
                    <h4 style={{ margin: '0 0 0.5rem 0', color: '#0f172a', fontWeight: 600, fontSize: '0.9rem' }}>Response Headers</h4>
                    {formatHeaders(selectedLog.response?.headers) || (
                      <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0', color: '#64748b', fontSize: '0.85rem' }}>
                        Standard HTTP headers returned by origin server.
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Tab: Payload */}
              {activeTab === 'payload' && (
                <div>
                  <h4 style={{ margin: '0 0 0.5rem 0', color: '#0f172a', fontWeight: 600, fontSize: '0.9rem' }}>Payload / Input Arguments</h4>
                  <pre style={{ background: '#fff', border: '1px solid #e2e8f0', padding: '1rem', borderRadius: '0.5rem', margin: 0, fontSize: '0.85rem', overflowX: 'auto', color: '#334155', whiteSpace: 'pre-wrap' }}>
                    {selectedLog.request?.body || '(empty request payload)'}
                  </pre>
                </div>
              )}

              {/* Tab: Raw JSON */}
              {activeTab === 'raw' && (
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                    <h4 style={{ margin: 0, color: '#0f172a', fontWeight: 600, fontSize: '0.9rem' }}>Raw Telemetry Object</h4>
                    <button
                      onClick={() => copyToClipboard(JSON.stringify(selectedLog, null, 2))}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '0.35rem',
                        border: '1px solid #cbd5e1',
                        background: '#fff',
                        padding: '0.25rem 0.6rem',
                        borderRadius: '0.25rem',
                        fontSize: '0.75rem',
                        cursor: 'pointer',
                        color: '#334155',
                        fontWeight: 600
                      }}
                    >
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                      </svg>
                      {copied ? 'Copied to Clipboard!' : 'Copy Raw JSON'}
                    </button>
                  </div>
                  <pre style={{ background: '#0f172a', color: '#38bdf8', padding: '1rem', borderRadius: '0.5rem', margin: 0, fontSize: '0.8rem', overflowX: 'auto', border: '1px solid #1e293b', lineHeight: '1.4' }}>
                    {JSON.stringify(selectedLog, null, 2)}
                  </pre>
                </div>
              )}

            </div>
          </>
        ) : (
          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', color: '#94a3b8' }}>
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ marginBottom: '0.75rem' }}>
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            Select an activity from the left list to inspect details
          </div>
        )}
      </div>
    </div>
  )
}

// Export wrapped with ErrorBoundary
export function Inspector(props) {
  return (
    <InspectorErrorBoundary>
      <InspectorInner {...props} />
    </InspectorErrorBoundary>
  )
}
