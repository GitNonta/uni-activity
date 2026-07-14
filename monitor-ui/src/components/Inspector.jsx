import { useState } from 'react'

export function Inspector({ logs }) {
  const [selectedLogId, setSelectedLogId] = useState(null)
  const [activeTab, setActiveTab] = useState('summary')
  const [filterType, setFilterType] = useState('all') // 'all', 'http', 'artisan', 'shell'

  const safeLogs = Array.isArray(logs) ? logs : []

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

  const selectedLog = filteredLogs.find(l => l.id === selectedLogId) || filteredLogs[0] || null

  // Auto-select first log if none selected and logs exist
  if (selectedLog && selectedLog.id !== selectedLogId) {
    setSelectedLogId(selectedLog.id)
  }

  const getStatusColor = (log) => {
    if (!log) return '#6b7280';
    const type = getLogType(log);
    if (type === 'shell' || type === 'artisan') {
      return log.status === 0 ? '#10b981' : '#ef4444';
    }
    const status = log.status;
    if (status >= 200 && status < 300) return '#10b981'
    if (status >= 300 && status < 400) return '#3b82f6'
    if (status >= 400 && status < 500) return '#f59e0b'
    return '#ef4444'
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

  const formatHeaders = (headers) => {
    if (!headers) return ''
    return Object.entries(headers)
      .map(([key, value]) => `${key}: ${Array.isArray(value) ? value.join(', ') : value}`)
      .join('\n')
  }

  return (
    <div style={{ display: 'flex', height: 'calc(100vh - 120px)', background: '#fff', borderRadius: '0.5rem', border: '1px solid #e5e7eb', overflow: 'hidden' }}>
      
      {/* Left Pane - List */}
      <div style={{ width: '380px', borderRight: '1px solid #e5e7eb', display: 'flex', flexDirection: 'column' }}>
        <div style={{ padding: '1rem', borderBottom: '1px solid #e5e7eb', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ margin: 0, fontSize: '1rem', color: '#111827', fontWeight: 600 }}>Server Activity</h3>
          <span style={{ fontSize: '0.75rem', color: '#6b7280', background: '#f3f4f6', padding: '0.2rem 0.5rem', borderRadius: '1rem' }}>
            {filteredLogs.length} / {safeLogs.length}
          </span>
        </div>

        {/* Filter Bar */}
        <div style={{ display: 'flex', gap: '0.25rem', padding: '0.5rem', background: '#f9fafb', borderBottom: '1px solid #e5e7eb' }}>
          {['all', 'http', 'artisan', 'shell'].map(type => (
            <button
              key={type}
              onClick={() => setFilterType(type)}
              style={{
                flex: 1,
                padding: '0.375rem 0.25rem',
                fontSize: '0.75rem',
                fontWeight: filterType === type ? '600' : '400',
                border: 'none',
                background: filterType === type ? '#111827' : 'transparent',
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
            <div style={{ padding: '2rem', textAlign: 'center', color: '#9ca3af' }}>No activity matched</div>
          ) : (
            filteredLogs.map((log) => (
              <div 
                key={log.id} 
                onClick={() => setSelectedLogId(log.id)}
                style={{ 
                  padding: '0.75rem 1rem', 
                  borderBottom: '1px solid #f3f4f6',
                  cursor: 'pointer',
                  background: selectedLogId === log.id ? '#111827' : 'transparent',
                  color: selectedLogId === log.id ? '#fff' : '#111827',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center'
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis', paddingRight: '0.5rem', flex: 1 }}>
                  <span style={getMethodBadgeStyle(log.method)}>{log.method}</span>
                  <span style={{ fontWeight: 600, fontSize: '0.875rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={log.path}>
                    {log.path}
                  </span>
                </div>
                <div style={{ textAlign: 'right', flexShrink: 0 }}>
                  <div style={{ fontSize: '0.875rem', color: selectedLogId === log.id ? '#10b981' : getStatusColor(log), fontWeight: 600 }}>
                    {getLogType(log) === 'http' ? log.status : `exit: ${log.status}`}
                  </div>
                  <div style={{ fontSize: '0.75rem', color: selectedLogId === log.id ? '#9ca3af' : '#6b7280' }}>
                    {log.duration > 0 ? `${log.duration}ms` : '0ms'}
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Right Pane - Details */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        {selectedLog ? (
          <>
            <div style={{ padding: '1.5rem', borderBottom: '1px solid #e5e7eb' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1rem', color: '#6b7280', fontSize: '0.875rem' }}>
                <div>{new Date(selectedLog.time).toLocaleString()}</div>
                <div>Duration: <strong>{selectedLog.duration}ms</strong> | User/IP: {selectedLog.ip}</div>
              </div>
              <h2 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 600, color: '#111827', fontFamily: getLogType(selectedLog) !== 'http' ? 'monospace' : 'inherit', wordBreak: 'break-all' }}>
                {selectedLog.method} {selectedLog.path}
              </h2>
            </div>
            
            {/* Tabs */}
            <div style={{ display: 'flex', borderBottom: '1px solid #e5e7eb', padding: '0 1rem' }}>
              {['summary', 'headers', 'raw'].map(tab => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  style={{
                    padding: '0.75rem 1rem',
                    background: 'none',
                    border: 'none',
                    borderBottom: activeTab === tab ? '2px solid #2563eb' : '2px solid transparent',
                    color: activeTab === tab ? '#2563eb' : '#6b7280',
                    fontWeight: activeTab === tab ? 600 : 400,
                    cursor: 'pointer',
                    textTransform: 'capitalize'
                  }}
                >
                  {tab}
                </button>
              ))}
            </div>

            {/* Tab Content */}
            <div style={{ padding: '1.5rem', overflowY: 'auto', flex: 1, background: '#f9fafb' }}>
              {activeTab === 'summary' && (
                <div>
                  <h3 style={{ margin: '0 0 1rem 0', display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '1rem' }}>
                    <span style={{ 
                      width: 10, height: 10, borderRadius: '50%', 
                      background: getStatusColor(selectedLog) 
                    }}></span>
                    {getLogType(selectedLog) === 'http' 
                      ? `${selectedLog.status} Response` 
                      : `Console execution: ${selectedLog.status === 0 ? 'Success' : 'Failed'}`}
                  </h3>
                  
                  {getLogType(selectedLog) !== 'http' ? (
                    /* Terminal Emulator Preview */
                    <div style={{ 
                      background: '#1e1e1e', 
                      color: '#f1f1f1', 
                      fontFamily: 'Courier New, Courier, monospace', 
                      borderRadius: '0.5rem', 
                      padding: '1.25rem', 
                      boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                      border: '1px solid #333'
                    }}>
                      <div style={{ display: 'flex', gap: '6px', marginBottom: '1rem', borderBottom: '1px solid #333', paddingBottom: '0.5rem' }}>
                        <span style={{ width: 12, height: 12, borderRadius: '50%', background: '#ff5f56', display: 'inline-block' }}></span>
                        <span style={{ width: 12, height: 12, borderRadius: '50%', background: '#ffbd2e', display: 'inline-block' }}></span>
                        <span style={{ width: 12, height: 12, borderRadius: '50%', background: '#27c93f', display: 'inline-block' }}></span>
                        <span style={{ marginLeft: '1rem', fontSize: '0.75rem', color: '#888', flex: 1, fontFamily: 'sans-serif' }}>
                          {getLogType(selectedLog) === 'shell' ? 'bash/zsh terminal' : 'laravel artisan'}
                        </span>
                      </div>
                      
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.9rem', lineHeight: '1.4' }}>
                        <div>
                          <span style={{ color: '#888' }}>[User]</span> <span style={{ color: '#569cd6' }}>{selectedLog.ip}</span>
                        </div>
                        <div>
                          <span style={{ color: '#888' }}>[Path]</span> <span style={{ color: '#ce9178' }}>{selectedLog.request.headers?.['Working-Dir'] || selectedLog.request.headers?.['Environment'] || '/'}</span>
                        </div>
                        <div style={{ marginTop: '0.5rem', borderLeft: '3px solid #2563eb', paddingLeft: '0.75rem' }}>
                          <span style={{ color: '#4fc1ff', fontWeight: 'bold' }}>$ </span>
                          <span style={{ color: '#fff', fontWeight: 'bold' }}>{selectedLog.path}</span>
                        </div>
                        <div style={{ marginTop: '1rem' }}>
                          <span style={{ color: '#888' }}>[Output Summary]</span>
                        </div>
                        <pre style={{ 
                          margin: '0.25rem 0 0 0', 
                          background: '#151515', 
                          padding: '0.75rem', 
                          borderRadius: '0.25rem', 
                          whiteSpace: 'pre-wrap', 
                          wordBreak: 'break-all',
                          color: selectedLog.status === 0 ? '#4af626' : '#ff5f56',
                          fontSize: '0.85rem',
                          border: '1px solid #222'
                        }}>
                          {selectedLog.response?.body || '(No output recorded)'}
                        </pre>
                        <div style={{ marginTop: '0.5rem', fontSize: '0.8rem', color: '#888', display: 'flex', justifyContent: 'space-between' }}>
                          <span>Duration: {selectedLog.duration > 0 ? `${selectedLog.duration} ms` : 'N/A'}</span>
                          <span>Exit code: <strong style={{ color: selectedLog.status === 0 ? '#4af626' : '#ff5f56' }}>{selectedLog.status}</strong></span>
                        </div>
                      </div>
                    </div>
                  ) : (
                    selectedLog.response.body && (
                      <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '0.5rem', padding: '1rem', overflowX: 'auto' }}>
                        <pre style={{ margin: 0, fontSize: '0.875rem', color: '#374151', whiteSpace: 'pre-wrap' }}>
                          {selectedLog.response.body}
                        </pre>
                      </div>
                    )
                  )}
                </div>
              )}

              {activeTab === 'headers' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                  <div>
                    <h4 style={{ margin: '0 0 0.5rem 0', color: '#111827', fontWeight: 600 }}>Properties / Headers</h4>
                    <pre style={{ background: '#fff', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.5rem', margin: 0, fontSize: '0.875rem', overflowX: 'auto' }}>
                      {formatHeaders(selectedLog.request.headers)}
                    </pre>
                  </div>
                  {getLogType(selectedLog) === 'http' && (
                    <div>
                      <h4 style={{ margin: '0 0 0.5rem 0', color: '#111827', fontWeight: 600 }}>Response Headers</h4>
                      <pre style={{ background: '#fff', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.5rem', margin: 0, fontSize: '0.875rem', overflowX: 'auto' }}>
                        {formatHeaders(selectedLog.response.headers)}
                      </pre>
                    </div>
                  )}
                </div>
              )}

              {activeTab === 'raw' && (
                <div>
                   <h4 style={{ margin: '0 0 0.5rem 0', color: '#111827', fontWeight: 600 }}>Raw Body / Input</h4>
                    <pre style={{ background: '#fff', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.5rem', margin: '0 0 1rem 0', fontSize: '0.875rem', overflowX: 'auto' }}>
                      {selectedLog.request.body || '(empty)'}
                    </pre>
                </div>
              )}
            </div>
          </>
        ) : (
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af' }}>
            Select an activity to view details
          </div>
        )}
      </div>
    </div>
  )
}

