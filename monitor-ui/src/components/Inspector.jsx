import { useState } from 'react'

export function Inspector({ logs }) {
  const [selectedLogId, setSelectedLogId] = useState(null)
  const [activeTab, setActiveTab] = useState('summary')

  const safeLogs = Array.isArray(logs) ? logs : []
  const selectedLog = safeLogs.find(l => l.id === selectedLogId)

  // Auto-select first log if none selected
  if (!selectedLog && safeLogs.length > 0 && !selectedLogId) {
    setSelectedLogId(safeLogs[0].id)
  }

  const getStatusColor = (status) => {
    if (status >= 200 && status < 300) return '#10b981'
    if (status >= 300 && status < 400) return '#3b82f6'
    if (status >= 400 && status < 500) return '#f59e0b'
    return '#ef4444'
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
      <div style={{ width: '350px', borderRight: '1px solid #e5e7eb', display: 'flex', flexDirection: 'column' }}>
        <div style={{ padding: '1rem', borderBottom: '1px solid #e5e7eb', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ margin: 0, fontSize: '1rem', color: '#111827' }}>All Requests</h3>
          <span style={{ fontSize: '0.75rem', color: '#6b7280', background: '#f3f4f6', padding: '0.2rem 0.5rem', borderRadius: '1rem' }}>
            {safeLogs.length} reqs
          </span>
        </div>
        <div style={{ overflowY: 'auto', flex: 1 }}>
          {safeLogs.length === 0 ? (
            <div style={{ padding: '2rem', textAlign: 'center', color: '#9ca3af' }}>No requests yet</div>
          ) : (
            safeLogs.map((log) => (
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
                <div style={{ overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis', paddingRight: '1rem' }}>
                  <div style={{ fontWeight: 600, fontSize: '0.875rem' }}>{log.method} {log.path}</div>
                </div>
                <div style={{ textAlign: 'right', flexShrink: 0 }}>
                  <div style={{ fontSize: '0.875rem', color: selectedLogId === log.id ? '#10b981' : getStatusColor(log.status), fontWeight: 600 }}>{log.status}</div>
                  <div style={{ fontSize: '0.75rem', color: selectedLogId === log.id ? '#9ca3af' : '#6b7280' }}>{log.duration}ms</div>
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
                <div>Duration: <strong>{selectedLog.duration}ms</strong> | IP: {selectedLog.ip}</div>
              </div>
              <h2 style={{ margin: 0, fontSize: '1.5rem', fontWeight: 600, color: '#111827' }}>
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
                  <h3 style={{ margin: '0 0 1rem 0', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <span style={{ 
                      width: 10, height: 10, borderRadius: '50%', 
                      background: getStatusColor(selectedLog.status) 
                    }}></span>
                    {selectedLog.status} Response
                  </h3>
                  
                  {selectedLog.response.body && (
                    <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '0.5rem', padding: '1rem', overflowX: 'auto' }}>
                      <pre style={{ margin: 0, fontSize: '0.875rem', color: '#374151', whiteSpace: 'pre-wrap' }}>
                        {selectedLog.response.body}
                      </pre>
                    </div>
                  )}
                </div>
              )}

              {activeTab === 'headers' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                  <div>
                    <h4 style={{ margin: '0 0 0.5rem 0', color: '#111827' }}>Request Headers</h4>
                    <pre style={{ background: '#fff', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.5rem', margin: 0, fontSize: '0.875rem', overflowX: 'auto' }}>
                      {formatHeaders(selectedLog.request.headers)}
                    </pre>
                  </div>
                  <div>
                    <h4 style={{ margin: '0 0 0.5rem 0', color: '#111827' }}>Response Headers</h4>
                    <pre style={{ background: '#fff', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.5rem', margin: 0, fontSize: '0.875rem', overflowX: 'auto' }}>
                      {formatHeaders(selectedLog.response.headers)}
                    </pre>
                  </div>
                </div>
              )}

              {activeTab === 'raw' && (
                <div>
                   <h4 style={{ margin: '0 0 0.5rem 0', color: '#111827' }}>Request Body</h4>
                    <pre style={{ background: '#fff', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.5rem', margin: '0 0 1rem 0', fontSize: '0.875rem', overflowX: 'auto' }}>
                      {selectedLog.request.body || '(empty)'}
                    </pre>
                </div>
              )}
            </div>
          </>
        ) : (
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af' }}>
            Select a request to view details
          </div>
        )}
      </div>
    </div>
  )
}
