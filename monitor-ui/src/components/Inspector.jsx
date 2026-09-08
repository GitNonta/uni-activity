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

  const thaiProvinceMap = {
    'Phuket': 'ภูเก็ต',
    'Bangkok': 'กรุงเทพมหานคร',
    'Chiang Mai': 'เชียงใหม่',
    'Nonthaburi': 'นนทบุรี',
    'Pathum Thani': 'ปทุมธานี',
    'Samut Prakan': 'สมุทรปราการ',
    'Chon Buri': 'ชลบุรี',
    'Khon Kaen': 'ขอนแก่น',
    'Nakhon Ratchasima': 'นครราชสีมา',
    'Songkhla': 'สงขลา',
    'Surat Thani': 'สุราษฎร์ธานี',
    'Udon Thani': 'อุดรธานี',
    'Rayong': 'ระยอง',
    'Krabi': 'กระบี่',
    'Phang Nga': 'พังงา',
    'Trang': 'ตรัง',
    'Nakhon Si Thammarat': 'นครศรีธรรมราช',
    'Pattani': 'ปัตตานี',
    'Yala': 'ยะลา',
    'Narathiwat': 'นราธิวาส',
    'Lampang': 'ลำปาง',
    'Chiang Rai': 'เชียงราย',
    'Phitsanulok': 'พิษณุโลก',
    'Sukhothai': 'สุโขทัย',
    'Ubon Ratchathani': 'อุบลราชธานี'
  };

  const getOriginInfo = (log) => {
    if (!log) return { ip: '127.0.0.1', origin: 'Localhost', type: 'loopback', location: 'Server Internal (Loopback)', gateway: 'Internal Loopback', city: '', region: '', isp: '', country: '' };
    const ip = log.ip || '127.0.0.1';
    let type = log.origin_type;
    if (!type) {
      if (ip === '127.0.0.1' || ip === '::1' || ip === 'localhost') type = 'loopback';
      else if (ip.startsWith('192.168.') || ip.startsWith('10.') || ip.startsWith('172.')) type = 'lan';
      else type = 'wan';
    }
    const origin = log.origin || (type === 'loopback' ? 'Localhost' : type === 'lan' ? 'Local Network (LAN)' : 'Public Internet');
    const location = log.location || (type === 'loopback' ? 'Server Internal (Loopback)' : type === 'lan' ? 'Local Area Network / Wi-Fi' : `${origin} (Public Internet)`);
    const gateway = log.gateway || (log.ray ? 'Cloudflare Tunnel' : type === 'loopback' ? 'Internal Loopback' : 'Direct HTTP');
    const city = log.city || '';
    const region = log.region || '';
    const isp = log.isp || '';
    const country = log.country_name || log.country || '';
    return { ip, origin, type, location, gateway, city, region, isp, country };
  };

  const getQueryParams = (log) => {
    if (log?.request?.query && typeof log.request.query === 'object' && Object.keys(log.request.query).length > 0) {
      return log.request.query;
    }
    const urlStr = log?.url || log?.path || '';
    if (urlStr.includes('?')) {
      try {
        const queryStr = urlStr.split('?')[1];
        const params = new URLSearchParams(queryStr);
        const obj = {};
        for (const [k, v] of params.entries()) {
          obj[k] = v;
        }
        return Object.keys(obj).length > 0 ? obj : null;
      } catch (e) {
        return null;
      }
    }
    return null;
  };

  const queryParams = selectedLog ? getQueryParams(selectedLog) : null;
  const hasBody = Boolean(selectedLog?.request?.body && String(selectedLog.request.body).trim() !== '');

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
                  <div style={{ display: 'flex', flexDirection: 'column', overflow: 'hidden', paddingRight: '0.5rem', flex: 1, gap: '0.2rem' }}>
                    {/* Method & Section Badge */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem', overflow: 'hidden' }}>
                      <span style={getMethodBadgeStyle(log.method)}>{log.method}</span>
                      {log.section && (
                        <span style={{
                          fontSize: '0.68rem',
                          fontWeight: 600,
                          padding: '0.1rem 0.4rem',
                          borderRadius: '0.25rem',
                          background: isSelected ? 'rgba(255,255,255,0.18)' : '#e0e7ff',
                          color: isSelected ? '#fff' : '#3730a3',
                          whiteSpace: 'nowrap',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          maxWidth: '180px'
                        }}>
                          {log.section.split(' (')[0]}
                        </span>
                      )}
                    </div>

                    {/* Action Title (What they are doing) */}
                    <div style={{ fontWeight: 700, fontSize: '0.85rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: isSelected ? '#fff' : '#0f172a' }}>
                      {log.action || log.path || '/'}
                    </div>

                    {/* User Identity (Who) */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem', fontSize: '0.72rem', color: isSelected ? '#cbd5e1' : '#475569' }}>
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                      </svg>
                      <span style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '160px' }}>
                        {log.user?.name || 'Guest Visitor'}
                      </span>
                      {log.user?.role && (
                        <span style={{
                          fontSize: '0.62rem',
                          padding: '0.05rem 0.3rem',
                          borderRadius: '0.2rem',
                          fontWeight: 600,
                          background: isSelected ? 'rgba(255,255,255,0.2)' : log.user.role === 'admin' ? '#fef3c7' : log.user.role === 'student' ? '#dbeafe' : '#f1f5f9',
                          color: isSelected ? '#fff' : log.user.role === 'admin' ? '#92400e' : log.user.role === 'student' ? '#1e40af' : '#475569'
                        }}>
                          {log.user.role.toUpperCase()}
                        </span>
                      )}
                    </div>

                    {/* Origin & IP Sub-row */}
                    <div style={{ 
                      display: 'flex', 
                      alignItems: 'center', 
                      gap: '0.35rem', 
                      fontSize: '0.7rem',
                      color: isSelected ? '#94a3b8' : '#64748b' 
                    }}>
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                      </svg>
                      <span style={{ fontFamily: 'monospace' }}>{log.ip || '127.0.0.1'}</span>
                      <span>•</span>
                      <span style={{ 
                        fontWeight: 600, 
                        color: (log.origin_type === 'wan' || (!log.origin_type && log.ip && !log.ip.startsWith('127.') && !log.ip.startsWith('192.168.'))) 
                          ? (isSelected ? '#93c5fd' : '#2563eb') 
                          : (isSelected ? '#cbd5e1' : '#475569'),
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap'
                      }}>
                        {log.city 
                          ? `${log.city}${thaiProvinceMap[log.city] ? ` (${thaiProvinceMap[log.city]})` : ''}, ${log.country || log.origin}`
                          : (log.origin || (log.ip === '127.0.0.1' ? 'Localhost' : 'LAN'))}
                      </span>
                    </div>
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
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem', color: '#64748b', fontSize: '0.8rem', flexWrap: 'wrap', gap: '0.5rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  <span>{new Date(selectedLog.time || Date.now()).toLocaleString()}</span>
                </div>

                {/* User Identity Badge */}
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', background: '#f8fafc', padding: '0.2rem 0.6rem', borderRadius: '0.375rem', border: '1px solid #e2e8f0' }}>
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  <span style={{ color: '#64748b' }}>User:</span>
                  <strong style={{ color: '#0f172a' }}>{selectedLog.user?.name || 'Guest Visitor'}</strong>
                  <span style={{
                    fontSize: '0.68rem',
                    fontWeight: 600,
                    padding: '0.1rem 0.4rem',
                    borderRadius: '0.2rem',
                    background: selectedLog.user?.role === 'admin' ? '#fef3c7' : selectedLog.user?.role === 'student' ? '#dbeafe' : '#f1f5f9',
                    color: selectedLog.user?.role === 'admin' ? '#92400e' : selectedLog.user?.role === 'student' ? '#1e40af' : '#475569'
                  }}>
                    {selectedLog.user?.role ? selectedLog.user.role.toUpperCase() : 'GUEST'}
                  </span>
                </div>

                <div style={{ display: 'flex', gap: '1.25rem', alignItems: 'center', flexWrap: 'wrap' }}>
                  <span>Duration: <strong>{Number(selectedLog.duration || 0).toFixed(2)} ms</strong></span>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', flexWrap: 'wrap' }}>
                    <span>Client IP:</span>
                    <strong style={{ fontFamily: 'monospace', color: '#0f172a' }}>{selectedLog.ip || '127.0.0.1'}</strong>
                    <span style={{
                      fontSize: '0.7rem',
                      fontWeight: 600,
                      padding: '0.12rem 0.45rem',
                      borderRadius: '0.25rem',
                      background: selectedLog.origin_type === 'wan' ? '#eff6ff' : selectedLog.origin_type === 'lan' ? '#ecfdf5' : '#f1f5f9',
                      color: selectedLog.origin_type === 'wan' ? '#1d4ed8' : selectedLog.origin_type === 'lan' ? '#047857' : '#475569',
                      border: selectedLog.origin_type === 'wan' ? '1px solid #bfdbfe' : selectedLog.origin_type === 'lan' ? '1px solid #a7f3d0' : '1px solid #e2e8f0',
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '0.25rem'
                    }}>
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                      </svg>
                      {selectedLog.city 
                        ? `${selectedLog.city}${thaiProvinceMap[selectedLog.city] ? ` (${thaiProvinceMap[selectedLog.city]})` : ''}${selectedLog.region && selectedLog.region !== selectedLog.city ? `, ${selectedLog.region}` : ''} • ${selectedLog.country_name || selectedLog.country || selectedLog.origin}`
                        : (selectedLog.origin || (selectedLog.ip === '127.0.0.1' ? 'Localhost' : 'LAN'))}
                    </span>
                  </div>
                </div>
              </div>

              {/* Main Action Title & Section */}
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
                <span style={getMethodBadgeStyle(selectedLog.method)}>{selectedLog.method}</span>
                <h2 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 700, color: '#0f172a' }}>
                  {selectedLog.action || selectedLog.path || '/'}
                </h2>
                {selectedLog.section && (
                  <span style={{
                    fontSize: '0.75rem',
                    fontWeight: 600,
                    padding: '0.15rem 0.6rem',
                    borderRadius: '0.375rem',
                    background: '#e0e7ff',
                    color: '#3730a3',
                    border: '1px solid #c7d2fe'
                  }}>
                    {selectedLog.section}
                  </span>
                )}
              </div>

              {/* Path, Route & Controller */}
              <div style={{ marginTop: '0.4rem', fontSize: '0.8rem', color: '#64748b', display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
                <span>Path: <strong style={{ fontFamily: 'monospace', color: '#334155' }}>{selectedLog.path || '/'}</strong></span>
                {selectedLog.route && <span>• Route: <strong style={{ fontFamily: 'monospace', color: '#2563eb' }}>{selectedLog.route}</strong></span>}
                {selectedLog.controller && <span>• Controller: <strong style={{ fontFamily: 'monospace', color: '#059669' }}>{selectedLog.controller}</strong></span>}
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

                  {/* 1. Who & What Key Summary Grid */}
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
                    {/* User Summary Box */}
                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                          <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Who (ผู้ใช้งาน)</span>
                      </div>
                      <div style={{ fontSize: '0.95rem', color: '#0f172a', fontWeight: 700, marginTop: '0.35rem', display: 'flex', alignItems: 'center', gap: '0.4rem', flexWrap: 'wrap' }}>
                        <span>{selectedLog.user?.name || 'Guest Visitor'}</span>
                        <span style={{
                          fontSize: '0.65rem',
                          fontWeight: 600,
                          padding: '0.1rem 0.4rem',
                          borderRadius: '0.2rem',
                          background: selectedLog.user?.role === 'admin' ? '#fef3c7' : selectedLog.user?.role === 'student' ? '#dbeafe' : '#f1f5f9',
                          color: selectedLog.user?.role === 'admin' ? '#92400e' : selectedLog.user?.role === 'student' ? '#1e40af' : '#475569'
                        }}>
                          {selectedLog.user?.role ? selectedLog.user.role.toUpperCase() : 'GUEST'}
                        </span>
                      </div>
                      <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '0.2rem' }}>
                        {selectedLog.user?.student_id ? `Student ID: ${selectedLog.user.student_id}` : (selectedLog.user?.email || 'ไม่ได้เข้าสู่ระบบ (Anonymous)')}
                      </div>
                    </div>

                    {/* Action Summary Box */}
                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#059669" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        <span>What (การกระทำ)</span>
                      </div>
                      <div style={{ fontSize: '0.95rem', color: '#0f172a', fontWeight: 700, marginTop: '0.35rem' }}>
                        {selectedLog.action || selectedLog.path || '/'}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: '#2563eb', marginTop: '0.2rem', fontWeight: 500 }}>
                        {selectedLog.section || 'General / Public'}
                      </div>
                    </div>

                    {/* Target & Route Box */}
                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6366f1" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <circle cx="12" cy="12" r="10"></circle>
                          <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                        </svg>
                        <span>Section & Route (ส่วนระบบ)</span>
                      </div>
                      <div style={{ fontSize: '0.88rem', color: '#0f172a', fontWeight: 600, marginTop: '0.35rem', fontFamily: 'monospace' }}>
                        {selectedLog.route || selectedLog.path || '/'}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '0.2rem', fontFamily: 'monospace' }}>
                        {selectedLog.controller || 'Direct View / Closure'}
                      </div>
                    </div>

                    {/* Source & Location Box */}
                    <div style={{ background: '#fff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                      <div style={{ fontSize: '0.72rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#d97706" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="2" y1="12" x2="22" y2="12"></line>
                          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span>Client Location (ตำแหน่ง)</span>
                      </div>
                      <div style={{ fontSize: '0.9rem', color: '#0f172a', fontWeight: 600, marginTop: '0.35rem', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                        <span style={{ fontFamily: 'monospace' }}>{selectedLog.ip || '127.0.0.1'}</span>
                      </div>
                      <div style={{ fontSize: '0.75rem', color: '#2563eb', marginTop: '0.2rem', fontWeight: 600 }}>
                        {selectedLog.city ? `${selectedLog.city}${thaiProvinceMap[selectedLog.city] ? ` (${thaiProvinceMap[selectedLog.city]})` : ''}, ${selectedLog.country_name || selectedLog.country || selectedLog.origin}` : (selectedLog.origin || 'LAN')}
                      </div>
                    </div>
                  </div>

                  {/* 2. User & Activity Deep Dive Card */}
                  <div style={{ background: '#fff', borderRadius: '0.5rem', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                    <div style={{ padding: '0.75rem 1rem', borderBottom: '1px solid #e2e8f0', background: '#f8fafc', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontWeight: 600, fontSize: '0.85rem', color: '#0f172a' }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                          <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>User & Activity Details (ใครใช้เว็บ และทำอะไรส่วนไหน)</span>
                      </div>
                      <span style={{
                        fontSize: '0.72rem',
                        fontWeight: 600,
                        padding: '0.15rem 0.5rem',
                        borderRadius: '1rem',
                        background: selectedLog.user?.is_authenticated ? '#dcfce7' : '#f1f5f9',
                        color: selectedLog.user?.is_authenticated ? '#15803d' : '#475569'
                      }}>
                        {selectedLog.user?.is_authenticated ? 'Authenticated User' : 'Anonymous Guest'}
                      </span>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1px', background: '#e2e8f0' }}>
                      {/* Name & Role */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>User Full Name</div>
                        <div style={{ fontWeight: 700, fontSize: '0.95rem', color: '#0f172a' }}>
                          {selectedLog.user?.name || 'Guest Visitor (ผู้เยี่ยมชม)'}
                        </div>
                        <div style={{ fontSize: '0.72rem', color: '#64748b', marginTop: '0.2rem' }}>
                          Role: <strong style={{ textTransform: 'uppercase' }}>{selectedLog.user?.role || 'guest'}</strong>
                          {selectedLog.user?.id && <span> • ID: #{selectedLog.user.id}</span>}
                        </div>
                      </div>

                      {/* Student ID / Faculty */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>Student ID / Contact</div>
                        <div style={{ fontWeight: 600, fontSize: '0.9rem', color: '#0f172a', fontFamily: selectedLog.user?.student_id ? 'monospace' : 'inherit' }}>
                          {selectedLog.user?.student_id || selectedLog.user?.email || 'N/A (Guest)'}
                        </div>
                        {selectedLog.user?.faculty && (
                          <div style={{ fontSize: '0.72rem', color: '#64748b', marginTop: '0.2rem' }}>
                            Faculty: <strong>{selectedLog.user.faculty}</strong>
                          </div>
                        )}
                      </div>

                      {/* Action Title */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>Action (สิ่งที่ทำ)</div>
                        <div style={{ fontWeight: 700, fontSize: '0.9rem', color: '#2563eb' }}>
                          {selectedLog.action || selectedLog.path || '/'}
                        </div>
                        <div style={{ fontSize: '0.72rem', color: '#64748b', marginTop: '0.2rem' }}>
                          Method: <strong>{selectedLog.method}</strong> • Status: <strong>{selectedLog.status}</strong>
                        </div>
                      </div>

                      {/* System Section */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>System Section (ส่วนของระบบ)</div>
                        <div style={{ fontWeight: 600, fontSize: '0.9rem', color: '#059669' }}>
                          {selectedLog.section || 'General / Public'}
                        </div>
                        {selectedLog.route && (
                          <div style={{ fontSize: '0.7rem', color: '#64748b', marginTop: '0.2rem', fontFamily: 'monospace' }}>
                            Route: {selectedLog.route}
                          </div>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* 3. Origin Entry URL & Referer Card */}
                  <div style={{ background: '#fff', borderRadius: '0.5rem', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                    <div style={{ padding: '0.75rem 1rem', borderBottom: '1px solid #e2e8f0', background: '#f8fafc', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontWeight: 600, fontSize: '0.85rem', color: '#0f172a' }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                        <span>Origin Entry URL & Referer (ต้นทางของ URL และการเข้าถึง)</span>
                      </div>
                      <span style={{ fontSize: '0.72rem', color: '#64748b' }}>Full HTTP Entry Path</span>
                    </div>

                    <div style={{ padding: '1rem', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                      {/* Full Public URL */}
                      <div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.25rem' }}>
                          <span style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase' }}>Full Request URL (ตั้งแต่ต้นทาง)</span>
                          <button
                            onClick={() => copyToClipboard(selectedLog.url || '')}
                            style={{
                              display: 'flex',
                              alignItems: 'center',
                              gap: '0.3rem',
                              border: '1px solid #cbd5e1',
                              background: '#f8fafc',
                              padding: '0.15rem 0.45rem',
                              borderRadius: '0.25rem',
                              fontSize: '0.7rem',
                              cursor: 'pointer',
                              color: '#334155'
                            }}
                          >
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            {copied ? 'Copied!' : 'Copy'}
                          </button>
                        </div>
                        <div style={{ fontSize: '0.85rem', color: '#2563eb', fontFamily: 'monospace', wordBreak: 'break-all', background: '#f8fafc', padding: '0.5rem 0.75rem', borderRadius: '0.375rem', border: '1px solid #e2e8f0' }}>
                          {selectedLog.url || '/'}
                        </div>
                      </div>

                      {/* Referer URL */}
                      {selectedLog.referer && (
                        <div>
                          <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>Referer (หน้าที่กดลิงก์เข้ามา)</div>
                          <div style={{ fontSize: '0.8rem', color: '#475569', fontFamily: 'monospace', wordBreak: 'break-all', background: '#f8fafc', padding: '0.4rem 0.75rem', borderRadius: '0.375rem', border: '1px solid #e2e8f0' }}>
                            {selectedLog.referer}
                          </div>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* 4. Client Source & Origin Details Card */}
                  <div style={{ background: '#fff', borderRadius: '0.5rem', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                    <div style={{ padding: '0.75rem 1rem', borderBottom: '1px solid #e2e8f0', background: '#f8fafc', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontWeight: 600, fontSize: '0.85rem', color: '#0f172a' }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="2" y1="12" x2="22" y2="12"></line>
                          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span>Client Location & Network Information (ที่อยู่และข้อมูลเครือข่าย)</span>
                      </div>
                      <span style={{ 
                        fontSize: '0.72rem', 
                        fontWeight: 600, 
                        padding: '0.15rem 0.5rem', 
                        borderRadius: '1rem',
                        background: selectedLog.origin_type === 'wan' ? '#dbeafe' : selectedLog.origin_type === 'lan' ? '#d1fae5' : '#f1f5f9',
                        color: selectedLog.origin_type === 'wan' ? '#1e40af' : selectedLog.origin_type === 'lan' ? '#065f46' : '#475569'
                      }}>
                        {selectedLog.origin_type === 'wan' ? 'External WAN' : selectedLog.origin_type === 'lan' ? 'Private LAN' : 'Localhost Loopback'}
                      </span>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1px', background: '#e2e8f0' }}>
                      
                      {/* 1. IP Address */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>Client IP Address</div>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                          <span style={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '0.95rem', color: '#0f172a' }}>
                            {selectedLog.ip || '127.0.0.1'}
                          </span>
                          <button
                            onClick={() => copyToClipboard(selectedLog.ip || '127.0.0.1')}
                            style={{ border: '1px solid #e2e8f0', background: '#f8fafc', padding: '0.15rem 0.4rem', borderRadius: '0.25rem', fontSize: '0.7rem', cursor: 'pointer', color: '#475569' }}
                          >
                            Copy IP
                          </button>
                        </div>
                      </div>

                      {/* 2. City & Province (เมือง / จังหวัด) */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>City & Province (เมือง / จังหวัด)</div>
                        <div style={{ fontWeight: 600, fontSize: '0.9rem', color: '#2563eb', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                          </svg>
                          <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {selectedLog.city 
                              ? `${selectedLog.city}${thaiProvinceMap[selectedLog.city] ? ` (${thaiProvinceMap[selectedLog.city]})` : ''}`
                              : (selectedLog.origin_type === 'lan' ? 'Local Subnet' : selectedLog.origin_type === 'loopback' ? 'Localhost' : 'Unknown City')}
                          </span>
                        </div>
                        <div style={{ fontSize: '0.72rem', color: '#64748b', marginTop: '0.2rem', display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                          <span>Province: <strong>{selectedLog.region ? `${selectedLog.region}${thaiProvinceMap[selectedLog.region] ? ` (${thaiProvinceMap[selectedLog.region]})` : ''}` : '-'}</strong></span>
                          <span>•</span>
                          <span>Country: <strong>{selectedLog.country_name || selectedLog.country || '-'}</strong></span>
                        </div>
                      </div>

                      {/* 3. ISP / Network Provider */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>ISP / Network Provider (ผู้ให้บริการ)</div>
                        <div style={{ fontWeight: 600, fontSize: '0.9rem', color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                            <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                            <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                            <line x1="12" y1="20" x2="12.01" y2="20"></line>
                          </svg>
                          <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {selectedLog.isp || (selectedLog.origin_type === 'lan' ? 'Local Area Network' : selectedLog.origin_type === 'loopback' ? 'Internal Loopback' : 'External Carrier')}
                          </span>
                        </div>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', marginTop: '0.15rem' }}>
                          Location: <strong>{selectedLog.location || selectedLog.origin || 'Direct'}</strong>
                        </div>
                      </div>

                      {/* 4. Gateway & Protocol */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>Connection Gateway</div>
                        <div style={{ fontWeight: 600, fontSize: '0.9rem', color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                            <line x1="6" y1="6" x2="6.01" y2="6"></line>
                            <line x1="6" y1="18" x2="6.01" y2="18"></line>
                          </svg>
                          <span>{selectedLog.gateway || (selectedLog.ray ? 'Cloudflare Tunnel' : selectedLog.ip === '127.0.0.1' ? 'Internal Loopback' : 'Direct HTTP')}</span>
                        </div>
                        {selectedLog.ray && (
                          <div style={{ fontSize: '0.7rem', color: '#64748b', marginTop: '0.15rem', fontFamily: 'monospace' }}>
                            CF-Ray: {selectedLog.ray}
                          </div>
                        )}
                      </div>

                      {/* 5. Client Agent */}
                      <div style={{ background: '#fff', padding: '0.85rem 1rem' }}>
                        <div style={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: '0.25rem' }}>Client Agent / Browser</div>
                        <div style={{ fontSize: '0.75rem', color: '#334155', fontFamily: 'monospace', wordBreak: 'break-all', lineHeight: '1.3' }}>
                          {selectedLog.request?.headers?.['user-agent'] || selectedLog.request?.headers?.['User-Agent'] || 'Standard HTTP Client'}
                        </div>
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
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
                  
                  {/* Query Parameters Section */}
                  {queryParams && (
                    <div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                        <h4 style={{ margin: 0, color: '#0f172a', fontWeight: 600, fontSize: '0.9rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                          </svg>
                          URL Query Parameters ({Object.keys(queryParams).length})
                        </h4>
                        <button
                          onClick={() => copyToClipboard(JSON.stringify(queryParams, null, 2))}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '0.35rem',
                            border: '1px solid #cbd5e1',
                            background: '#fff',
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
                          {copied ? 'Copied!' : 'Copy Query'}
                        </button>
                      </div>

                      <div style={{ background: '#fff', borderRadius: '0.5rem', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
                          <thead>
                            <tr style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0', textAlign: 'left', color: '#64748b', fontSize: '0.75rem', textTransform: 'uppercase' }}>
                              <th style={{ padding: '0.6rem 1rem', width: '35%' }}>Parameter Key</th>
                              <th style={{ padding: '0.6rem 1rem' }}>Value</th>
                            </tr>
                          </thead>
                          <tbody>
                            {Object.entries(queryParams).map(([k, v], idx) => (
                              <tr key={k} style={{ borderBottom: idx < Object.keys(queryParams).length - 1 ? '1px solid #f1f5f9' : 'none' }}>
                                <td style={{ padding: '0.6rem 1rem', fontWeight: 600, color: '#1e293b', fontFamily: 'monospace' }}>{k}</td>
                                <td style={{ padding: '0.6rem 1rem', color: '#2563eb', fontFamily: 'monospace', wordBreak: 'break-all' }}>{String(v)}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* Request Body Payload Section */}
                  {hasBody && (
                    <div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                        <h4 style={{ margin: 0, color: '#0f172a', fontWeight: 600, fontSize: '0.9rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                          </svg>
                          Request Body Payload
                        </h4>
                        <button
                          onClick={() => copyToClipboard(selectedLog.request.body)}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '0.35rem',
                            border: '1px solid #cbd5e1',
                            background: '#fff',
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
                          {copied ? 'Copied!' : 'Copy Body'}
                        </button>
                      </div>

                      <pre style={{ 
                        background: '#0f172a', 
                        color: '#f8fafc', 
                        border: '1px solid #1e293b', 
                        padding: '1rem', 
                        borderRadius: '0.5rem', 
                        margin: 0, 
                        fontSize: '0.85rem', 
                        overflowX: 'auto', 
                        whiteSpace: 'pre-wrap', 
                        lineHeight: '1.4' 
                      }}>
                        {selectedLog.request.body}
                      </pre>
                    </div>
                  )}

                  {/* Empty state if neither exists */}
                  {!queryParams && !hasBody && (
                    <div style={{ 
                      background: '#fff', 
                      borderRadius: '0.5rem', 
                      border: '1px solid #e2e8f0', 
                      padding: '2.5rem 1.5rem', 
                      textAlign: 'center',
                      display: 'flex',
                      flexDirection: 'column',
                      alignItems: 'center',
                      gap: '0.75rem'
                    }}>
                      <div style={{ width: 44, height: 44, borderRadius: '50%', background: '#f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="8" y1="12" x2="16" y2="12"></line>
                        </svg>
                      </div>
                      <div style={{ fontWeight: 600, color: '#0f172a', fontSize: '1rem' }}>
                        No Input Arguments or Payload Transmitted
                      </div>
                      <div style={{ color: '#64748b', fontSize: '0.85rem', maxWidth: '420px', lineHeight: '1.4' }}>
                        This <strong>{selectedLog.method}</strong> request to <code style={{ color: '#2563eb', background: '#eff6ff', padding: '0.1rem 0.3rem', borderRadius: '0.2rem' }}>{selectedLog.path || '/'}</code> did not carry any URL query parameters or HTTP request body payload.
                      </div>
                      <div style={{ fontSize: '0.75rem', color: '#94a3b8', marginTop: '0.5rem' }}>
                        Standard HTTP {selectedLog.method} requests typically query resources without submitting data.
                      </div>
                    </div>
                  )}

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
