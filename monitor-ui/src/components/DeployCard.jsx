import React, { useEffect, useRef, useState, useMemo } from 'react';

export function DeployCard({
  deployLog,
  deployChannels = {},
  deployStatus = null,
  logFilesInfo = { count: 0, total_size_mb: 0, files: [] },
  sshSessions = [],
  sftpSessions = 0,
  scpSessions = 0,
  events = [],
  githubDeployLogs = {},
  selectedEvent,
  onSelectEvent,
  onBack,
}) {
  const terminalContainerRef = useRef(null);
  const consoleRef = useRef(null);

  // Search & Filtering
  const [logSearchText, setLogSearchText] = useState('');
  const [logFilter, setLogFilter] = useState('All logs');
  const [logOrder, setLogOrder] = useState('Ascending');
  const [channelTab, setChannelTab] = useState('all'); // 'all' | 'ssh' | 'scp' | 'sftp' | 'git' | 'file' | 'live'
  
  // Storage Logs File Viewer
  const [selectedLogFile, setSelectedLogFile] = useState(null);
  const [showLogFilesDropdown, setShowLogFilesDropdown] = useState(false);
  const [fileContent, setFileContent] = useState('');
  const [fileLoading, setFileLoading] = useState(false);
  const [fileError, setFileError] = useState('');

  // Dropdowns & Menus
  const [isAllLogsMenuOpen, setIsAllLogsMenuOpen] = useState(false);
  const [isMoreMenuOpen, setIsMoreMenuOpen] = useState(false);
  const [showDeployDropdown, setShowDeployDropdown] = useState(false);

  // Terminal Display Settings
  const [wrapLines, setWrapLines] = useState(true);
  const [terminalTheme, setTerminalTheme] = useState('dark'); // 'dark' | 'oled' | 'matrix' | 'amber'
  const [isFullscreen, setIsFullscreen] = useState(false);

  // Action Loading & Feedback Toast
  const [actionLoading, setActionLoading] = useState(false);
  const [toast, setToast] = useState(null);

  // Specific commit log (when viewing past commit)
  const [commitLogContent, setCommitLogContent] = useState('');
  const [commitLogLoading, setCommitLogLoading] = useState(false);

  // Auto show toast helper
  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => {
      setToast(prev => (prev?.message === message ? null : prev));
    }, 4000);
  };

  // Close menus when clicking outside
  useEffect(() => {
    const handleClickOutside = () => {
      setIsAllLogsMenuOpen(false);
      setIsMoreMenuOpen(false);
      setShowDeployDropdown(false);
      setShowLogFilesDropdown(false);
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);

  // Listen to fullscreen change
  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
    };
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  // Auto-scroll to bottom of logs on update (if Ascending)
  useEffect(() => {
    if (consoleRef.current && logOrder === 'Ascending') {
      consoleRef.current.scrollTop = consoleRef.current.scrollHeight;
    }
  }, [deployLog, fileContent, commitLogContent, deployStatus, logOrder]);

  // Fetch selected log file content from the monitor API
  useEffect(() => {
    if (channelTab !== 'file' || !selectedLogFile) return undefined;
    let cancelled = false;
    setFileLoading(true);
    setFileError('');
    fetch(`/api/log-file?name=${encodeURIComponent(selectedLogFile.name)}&lines=400`)
      .then(r => r.json().then(j => ({ ok: r.ok, j })))
      .then(({ ok, j }) => {
        if (cancelled) return;
        if (!ok || j.status !== 'ok') throw new Error(j.message || 'Failed to load log file');
        setFileContent(j.content || '');
      })
      .catch(err => {
        if (cancelled) return;
        setFileError(err.message || 'Failed to load log file');
        setFileContent('');
      })
      .finally(() => { if (!cancelled) setFileLoading(false); });
    return () => { cancelled = true; };
  }, [channelTab, selectedLogFile]);

  // Fetch specific commit log if selectedEvent is active and not already loaded
  useEffect(() => {
    if (!selectedEvent?.hash) {
      setCommitLogContent('');
      return;
    }

    const cached = githubDeployLogs?.[selectedEvent.hash];
    if (cached) {
      setCommitLogContent(cached);
      return;
    }

    let cancelled = false;
    setCommitLogLoading(true);
    fetch(`/api/deploy/log?hash=${encodeURIComponent(selectedEvent.hash)}`)
      .then(r => r.json())
      .then(data => {
        if (cancelled) return;
        if (data.status === 'ok' && data.content) {
          setCommitLogContent(data.content);
        } else {
          setCommitLogContent(githubDeployLogs?.latest || deployLog || '');
        }
      })
      .catch(() => {
        if (cancelled) return;
        setCommitLogContent(deployLog || '');
      })
      .finally(() => {
        if (!cancelled) setCommitLogLoading(false);
      });

    return () => { cancelled = true; };
  }, [selectedEvent, githubDeployLogs, deployLog]);

  // Toggle Fullscreen
  const toggleFullscreen = () => {
    if (!terminalContainerRef.current) return;
    if (document.fullscreenElement) {
      document.exitFullscreen().catch(() => {});
    } else {
      terminalContainerRef.current.requestFullscreen().catch(() => {});
    }
  };

  // Determine active raw log text
  const activeRawLog = useMemo(() => {
    if (selectedEvent) {
      return commitLogContent || deployLog || '';
    }
    if (channelTab === 'file' && selectedLogFile) {
      return fileContent;
    }
    if (channelTab === 'ssh') {
      return deployChannels?.ssh || 'No active SSH session logs recorded.';
    }
    if (channelTab === 'scp') {
      return deployChannels?.scp || 'No active SCP transfer logs recorded.';
    }
    if (channelTab === 'sftp') {
      return deployChannels?.sftp || 'No active SFTP subsystem logs recorded.';
    }
    if (channelTab === 'git') {
      return deployChannels?.git || 'No Git sync logs recorded.';
    }
    if (deployStatus?.is_deploying && deployStatus.output_lines?.length > 0) {
      return deployStatus.output_lines.join('\n');
    }
    return deployLog || deployChannels?.deploy || deployChannels?.git || 'No deployment logs recorded yet.';
  }, [selectedEvent, commitLogContent, deployLog, channelTab, selectedLogFile, fileContent, deployChannels, deployStatus]);

  // Process and parse log lines with timestamps
  const processedLogs = useMemo(() => {
    if (!activeRawLog) return [];
    const lines = activeRawLog.split('\n').filter(line => line.trim());
    
    const parsedTime = selectedEvent?.timestamp ? new Date(selectedEvent.timestamp.replace(' at ', ' ')).getTime() : NaN;
    const baseTime = !isNaN(parsedTime) ? parsedTime : Date.now() - (lines.length * 1000);

    let lastParsedDate = new Date(baseTime);
    let items = lines.map((line, idx) => {
      let lineDate;
      let text = line;
      
      const timeMatch = line.match(/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.*)/);
      if (timeMatch) {
        lineDate = new Date(timeMatch[1].replace(/-/g, '/'));
        lastParsedDate = lineDate;
        text = timeMatch[2];
      } else {
        lineDate = new Date(lastParsedDate.getTime() + (idx * 20));
      }

      return {
        id: idx,
        dateObj: lineDate,
        dateString: lineDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        time: lineDate.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        text: text,
      };
    });

    // Category filter
    if (logFilter === 'Build logs') {
      items = items.filter(l => /npm|composer|build|vite|install|transforming|rendering/i.test(l.text));
    } else if (logFilter === 'Application logs') {
      items = items.filter(l => !/npm|composer|build|vite|install|transforming|rendering/i.test(l.text));
    } else if (logFilter === 'Errors & Warnings') {
      items = items.filter(l => /error|fail|warn|exception|fatal/i.test(l.text));
    }

    // Search filter
    if (logSearchText.trim()) {
      const q = logSearchText.toLowerCase();
      items = items.filter(l => l.text.toLowerCase().includes(q));
    }

    if (logOrder === 'Descending') {
      items = [...items].reverse();
    }

    return items;
  }, [activeRawLog, selectedEvent, logFilter, logSearchText, logOrder]);

  // Calculate real timestamps and elapsed duration
  const logTimingInfo = useMemo(() => {
    if (processedLogs.length === 0) return { start: '--:--', end: '--:--', duration: '' };
    const first = processedLogs[0].dateObj;
    const last = processedLogs[processedLogs.length - 1].dateObj;
    const diffSec = Math.max(0, Math.round(Math.abs(last.getTime() - first.getTime()) / 1000));
    
    const fmt = d => d.toLocaleTimeString('en-US', { hour12: true, hour: 'numeric', minute: '2-digit' });
    const durationStr = diffSec > 60 ? `${Math.floor(diffSec / 60)}m ${diffSec % 60}s` : `${diffSec}s`;
    
    return {
      start: fmt(first),
      end: fmt(last),
      duration: diffSec > 0 ? durationStr : 'Live',
    };
  }, [processedLogs]);

  // Local Timezone String
  const localTimezone = useMemo(() => {
    try {
      return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local';
    } catch {
      return 'UTC';
    }
  }, []);

  // Theme Styles
  const themeStyles = useMemo(() => {
    switch (terminalTheme) {
      case 'oled':
        return {
          bg: '#000000',
          headerBg: '#050505',
          border: '#171717',
          text: '#ffffff',
          textMuted: '#737373',
          timeColor: '#525252',
          activeTabBg: '#262626',
        };
      case 'matrix':
        return {
          bg: '#021206',
          headerBg: '#05220c',
          border: '#14532d',
          text: '#4ade80',
          textMuted: '#22c55e',
          timeColor: '#16a34a',
          activeTabBg: '#14532d',
        };
      case 'amber':
        return {
          bg: '#140d02',
          headerBg: '#211503',
          border: '#78350f',
          text: '#fbbf24',
          textMuted: '#d97706',
          timeColor: '#b45309',
          activeTabBg: '#78350f',
        };
      default: // 'dark'
        return {
          bg: '#0a0a0a',
          headerBg: '#121212',
          border: '#262626',
          text: '#e5e5e5',
          textMuted: '#a3a3a3',
          timeColor: '#737373',
          activeTabBg: '#262626',
        };
    }
  }, [terminalTheme]);

  // Deployment Actions
  const handleDeployLatest = async () => {
    if (!window.confirm('Trigger deployment from GitHub origin/main on server?')) return;
    try {
      setActionLoading(true);
      const res = await fetch('/api/deploy/manual', { method: 'POST' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Deployment trigger failed');
      showToast('Deployment initiated! Pulling origin/main and building assets...', 'success');
    } catch (err) {
      showToast('Deploy Error: ' + err.message, 'error');
    } finally {
      setActionLoading(false);
    }
  };

  const handleClearCacheAndDeploy = async () => {
    if (!window.confirm('Clear all build, framework caches and deploy latest code?')) return;
    try {
      setActionLoading(true);
      const res = await fetch('/api/deploy/manual?clear_cache=true', { method: 'POST' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Cache clear & deploy failed');
      showToast('Cache cleared & deployment started!', 'success');
    } catch (err) {
      showToast('Deploy Error: ' + err.message, 'error');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDeploySpecificCommit = async () => {
    const hash = window.prompt('Enter specific Git commit hash or branch name to deploy:');
    if (!hash || !hash.trim()) return;
    const cleanHash = hash.trim();
    if (!window.confirm(`Deploy commit ${cleanHash}? This will reset server code to ${cleanHash}.`)) return;
    try {
      setActionLoading(true);
      const res = await fetch('/api/deploy/rollback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ commit: cleanHash, commit_hash: cleanHash }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Rollback request failed');
      showToast(`Rollback to ${cleanHash} initiated! Reloading workers...`, 'success');
    } catch (err) {
      showToast('Rollback Error: ' + err.message, 'error');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRestartServices = async () => {
    if (!window.confirm('Restart PHP-FPM and application worker servers?')) return;
    try {
      setActionLoading(true);
      const res = await fetch('/api/deploy/restart', { method: 'POST' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Restart failed');
      showToast('PHP-FPM and worker servers restarted successfully!', 'success');
    } catch (err) {
      showToast('Restart Error: ' + err.message, 'error');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRollbackSelected = async () => {
    const targetHash = selectedEvent?.hash;
    if (!targetHash) {
      handleDeploySpecificCommit();
      return;
    }
    if (!window.confirm(`Are you sure you want to rollback to commit ${targetHash}?`)) return;
    try {
      setActionLoading(true);
      const res = await fetch('/api/deploy/rollback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ commit: targetHash, commit_hash: targetHash }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Rollback failed');
      showToast(`Rollback to ${targetHash} initiated!`, 'success');
    } catch (err) {
      showToast('Rollback Error: ' + err.message, 'error');
    } finally {
      setActionLoading(false);
    }
  };

  // Download Logs Helper
  const handleDownloadLog = () => {
    const content = processedLogs.map(l => `[${l.time}] ${l.text}`).join('\n') || activeRawLog;
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `deploy-${selectedEvent?.hash || channelTab}-${new Date().toISOString().slice(0, 10)}.log`;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Log file downloaded successfully.', 'success');
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem', minHeight: 'calc(100vh - 180px)', position: 'relative' }}>
      
      {/* Toast Notification Banner */}
      {toast && (
        <div style={{
          position: 'fixed',
          top: '20px',
          right: '24px',
          zIndex: 9999,
          display: 'flex',
          alignItems: 'center',
          gap: '0.65rem',
          padding: '0.75rem 1.25rem',
          background: toast.type === 'error' ? '#7f1d1d' : '#064e3b',
          color: toast.type === 'error' ? '#fecaca' : '#a7f3d0',
          border: `1px solid ${toast.type === 'error' ? '#ef4444' : '#10b981'}`,
          borderRadius: '8px',
          boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.5)',
          fontSize: '0.85rem',
          fontWeight: 600,
          animation: 'fadeIn 0.2s ease',
        }}>
          {toast.type === 'error' ? (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          ) : (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          )}
          <span>{toast.message}</span>
        </div>
      )}

      {/* Top Deployment Action Center & State Bar */}
      <div style={{
        background: '#0f172a',
        border: '1px solid #1e293b',
        borderRadius: '8px',
        padding: '1rem 1.25rem',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: '1rem',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.85rem' }}>
          <div style={{
            width: '36px',
            height: '36px',
            borderRadius: '8px',
            background: deployStatus?.is_deploying ? '#1e3a8a' : '#1e293b',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: deployStatus?.is_deploying ? '#38bdf8' : '#94a3b8',
          }}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5z" />
              <path d="M2 17l10 5 10-5" />
              <path d="M2 12l10 5 10-5" />
            </svg>
          </div>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <h2 style={{ margin: 0, fontSize: '1rem', fontWeight: 700, color: '#f8fafc' }}>
                Deployment Control Center
              </h2>
              <span style={{
                background: deployStatus?.is_deploying ? '#1e3a8a' : (deployStatus?.status === 'failed' ? '#7f1d1d' : '#064e3b'),
                color: deployStatus?.is_deploying ? '#93c5fd' : (deployStatus?.status === 'failed' ? '#fca5a5' : '#6ee7b7'),
                fontSize: '0.72rem',
                padding: '0.15rem 0.5rem',
                borderRadius: '4px',
                fontWeight: 600,
                display: 'inline-flex',
                alignItems: 'center',
                gap: '4px',
              }}>
                <span style={{
                  width: '6px',
                  height: '6px',
                  borderRadius: '50%',
                  background: deployStatus?.is_deploying ? '#38bdf8' : (deployStatus?.status === 'failed' ? '#ef4444' : '#10b981'),
                  animation: deployStatus?.is_deploying ? 'pulse 1s infinite' : 'none',
                }} />
                {deployStatus?.is_deploying ? 'Deploying...' : (deployStatus?.status === 'failed' ? 'Failed' : 'Production Live')}
              </span>
            </div>
            <div style={{ margin: '0.2rem 0 0 0', fontSize: '0.75rem', color: '#94a3b8' }}>
              {deployStatus?.is_deploying ? (
                <span style={{ color: '#38bdf8', fontWeight: 600 }}>{deployStatus.current_step}</span>
              ) : (
                <span>Branch: <strong style={{ color: '#cbd5e1' }}>main</strong> • Server: <strong style={{ color: '#cbd5e1' }}>S1 (192.168.1.222)</strong></span>
              )}
            </div>
          </div>
        </div>

        {/* Action Buttons Group */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
          
          {/* Rollback Button */}
          <button
            type="button"
            onClick={handleRollbackSelected}
            disabled={actionLoading || deployStatus?.is_deploying}
            style={{
              background: '#1e293b',
              border: '1px solid #334155',
              color: '#e2e8f0',
              padding: '0.5rem 0.85rem',
              borderRadius: '6px',
              fontSize: '0.8rem',
              fontWeight: 600,
              cursor: actionLoading ? 'wait' : 'pointer',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '0.4rem',
              transition: 'background 0.15s ease',
            }}
            title="Rollback code to specific or previous commit"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M3 10h10a8 8 0 0 1 8 8v2M3 10l6 6m-6-6l6-6"/>
            </svg>
            Rollback
          </button>

          {/* Restart Workers Button */}
          <button
            type="button"
            onClick={handleRestartServices}
            disabled={actionLoading || deployStatus?.is_deploying}
            style={{
              background: '#1e293b',
              border: '1px solid #334155',
              color: '#cbd5e1',
              padding: '0.5rem 0.85rem',
              borderRadius: '6px',
              fontSize: '0.8rem',
              fontWeight: 600,
              cursor: actionLoading ? 'wait' : 'pointer',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '0.4rem',
            }}
            title="Restart PHP-FPM and artisan serve workers"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
            Restart Workers
          </button>

          {/* Primary Deploy Dropdown Button Group */}
          <div style={{ position: 'relative', display: 'flex' }}>
            <button
              type="button"
              onClick={handleDeployLatest}
              disabled={actionLoading || deployStatus?.is_deploying}
              style={{
                background: deployStatus?.is_deploying ? '#475569' : '#9333ea',
                color: '#ffffff',
                border: 'none',
                padding: '0.5rem 1rem',
                borderTopLeftRadius: '6px',
                borderBottomLeftRadius: '6px',
                fontSize: '0.82rem',
                fontWeight: 600,
                cursor: (actionLoading || deployStatus?.is_deploying) ? 'wait' : 'pointer',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.45rem',
              }}
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              <span>{deployStatus?.is_deploying ? 'Deploying...' : 'Deploy Latest (main)'}</span>
            </button>

            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setShowDeployDropdown(!showDeployDropdown); }}
              disabled={actionLoading || deployStatus?.is_deploying}
              style={{
                background: deployStatus?.is_deploying ? '#475569' : '#9333ea',
                color: '#ffffff',
                border: 'none',
                borderLeft: '1px solid rgba(255, 255, 255, 0.2)',
                padding: '0.5rem 0.55rem',
                borderTopRightRadius: '6px',
                borderBottomRightRadius: '6px',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ transform: showDeployDropdown ? 'rotate(180deg)' : 'none', transition: 'transform 0.15s ease' }}>
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>

            {showDeployDropdown && (
              <div 
                onClick={(e) => e.stopPropagation()}
                style={{
                  position: 'absolute',
                  top: '115%',
                  right: 0,
                  width: '230px',
                  background: '#0f172a',
                  border: '1px solid #334155',
                  borderRadius: '6px',
                  boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.6)',
                  zIndex: 100,
                  padding: '0.4rem 0',
                }}
              >
                <button
                  type="button"
                  onClick={() => { setShowDeployDropdown(false); handleDeployLatest(); }}
                  style={{ width: '100%', textAlign: 'left', padding: '0.55rem 0.9rem', background: 'transparent', border: 'none', color: '#f8fafc', fontSize: '0.8rem', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '0.5rem' }}
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                  Deploy latest commit (main)
                </button>
                <button
                  type="button"
                  onClick={() => { setShowDeployDropdown(false); handleDeploySpecificCommit(); }}
                  style={{ width: '100%', textAlign: 'left', padding: '0.55rem 0.9rem', background: 'transparent', border: 'none', color: '#f8fafc', fontSize: '0.8rem', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '0.5rem' }}
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  Deploy a specific commit / tag
                </button>
                <button
                  type="button"
                  onClick={() => { setShowDeployDropdown(false); handleClearCacheAndDeploy(); }}
                  style={{ width: '100%', textAlign: 'left', padding: '0.55rem 0.9rem', background: 'transparent', border: 'none', color: '#f8fafc', fontSize: '0.8rem', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '0.5rem' }}
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  Clear build cache & deploy
                </button>
                <div style={{ height: '1px', background: '#1e293b', margin: '0.35rem 0' }} />
                <button
                  type="button"
                  onClick={() => { setShowDeployDropdown(false); handleRestartServices(); }}
                  style={{ width: '100%', textAlign: 'left', padding: '0.55rem 0.9rem', background: 'transparent', border: 'none', color: '#f8fafc', fontSize: '0.8rem', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '0.5rem' }}
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                  Restart PHP runtime & workers
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Session Access Panel (SSH / SFTP / SCP) */}
      {!selectedEvent && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
          {/* SSH Card */}
          <div 
            onClick={() => setChannelTab('ssh')}
            style={{ 
              padding: '1.15rem', 
              background: '#0f172a', 
              border: `1px solid ${channelTab === 'ssh' ? '#38bdf8' : '#1e293b'}`, 
              borderRadius: '8px', 
              cursor: 'pointer', 
              transition: 'border-color 0.15s ease' 
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.65rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '30px', height: '30px', background: '#1e3a8a', borderRadius: '6px', color: '#93c5fd' }}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
                    <line x1="6" y1="6" x2="6.01" y2="6" />
                    <line x1="6" y1="18" x2="6.01" y2="18" />
                  </svg>
                </span>
                <div>
                  <h3 style={{ margin: 0, fontSize: '0.85rem', fontWeight: 600, color: '#f8fafc' }}>SSH Connections</h3>
                  <p style={{ margin: 0, fontSize: '0.72rem', color: '#94a3b8' }}>Port 8022 daemon</p>
                </div>
              </div>
              <span style={{ fontSize: '1.25rem', fontWeight: 700, color: sshSessions.length > 0 ? '#38bdf8' : '#64748b' }}>
                {sshSessions.length}
              </span>
            </div>
            <div style={{ fontSize: '0.72rem', color: '#64748b' }}>
              {sshSessions.length > 0 ? `${sshSessions.length} active connection(s) • Click to view log` : 'No active sessions • Click to view history'}
            </div>
          </div>

          {/* SFTP Card */}
          <div 
            onClick={() => setChannelTab('sftp')}
            style={{ 
              padding: '1.15rem', 
              background: '#0f172a', 
              border: `1px solid ${channelTab === 'sftp' ? '#fbbf24' : '#1e293b'}`, 
              borderRadius: '8px', 
              cursor: 'pointer',
              transition: 'border-color 0.15s ease'
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.65rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '30px', height: '30px', background: '#713f12', borderRadius: '6px', color: '#fde047' }}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="16 16 12 12 8 16" />
                    <line x1="12" y1="12" x2="12" y2="21" />
                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
                  </svg>
                </span>
                <div>
                  <h3 style={{ margin: 0, fontSize: '0.85rem', fontWeight: 600, color: '#f8fafc' }}>SFTP Subsystem</h3>
                  <p style={{ margin: 0, fontSize: '0.72rem', color: '#94a3b8' }}>Secure file transfer</p>
                </div>
              </div>
              <span style={{ fontSize: '1.25rem', fontWeight: 700, color: sftpSessions > 0 ? '#fbbf24' : '#64748b' }}>
                {sftpSessions}
              </span>
            </div>
            <div style={{ fontSize: '0.72rem', color: '#64748b' }}>
              {sftpSessions > 0 ? `${sftpSessions} file transfer active • Click to view log` : 'Subsystem ready • Click to view history'}
            </div>
          </div>

          {/* SCP Card */}
          <div 
            onClick={() => setChannelTab('scp')}
            style={{ 
              padding: '1.15rem', 
              background: '#0f172a', 
              border: `1px solid ${channelTab === 'scp' ? '#4ade80' : '#1e293b'}`, 
              borderRadius: '8px', 
              cursor: 'pointer',
              transition: 'border-color 0.15s ease'
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.65rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '30px', height: '30px', background: '#14532d', borderRadius: '6px', color: '#86efac' }}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                  </svg>
                </span>
                <div>
                  <h3 style={{ margin: 0, fontSize: '0.85rem', fontWeight: 600, color: '#f8fafc' }}>SCP Transfers</h3>
                  <p style={{ margin: 0, fontSize: '0.72rem', color: '#94a3b8' }}>Secure copy protocol</p>
                </div>
              </div>
              <span style={{ fontSize: '1.25rem', fontWeight: 700, color: scpSessions > 0 ? '#4ade80' : '#64748b' }}>
                {scpSessions}
              </span>
            </div>
            <div style={{ fontSize: '0.72rem', color: '#64748b' }}>
              {scpSessions > 0 ? `${scpSessions} copy active • Click to view log` : 'Channel idle • Click to view history'}
            </div>
          </div>
        </div>
      )}

      {/* Back button when inspecting specific commit event */}
      {selectedEvent && (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <button 
            type="button"
            onClick={onBack}
            style={{ 
              display: 'inline-flex', 
              alignItems: 'center', 
              gap: '0.4rem', 
              background: 'transparent', 
              border: 'none', 
              color: '#94a3b8', 
              fontSize: '0.85rem', 
              fontWeight: 600, 
              cursor: 'pointer', 
              padding: 0 
            }}
          >
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to All Deployments
          </button>

          <span style={{ fontSize: '0.75rem', color: '#64748b' }}>
            Viewing Deployment Archive: <strong style={{ color: '#cbd5e1' }}>{selectedEvent.hash}</strong>
          </span>
        </div>
      )}

      {/* Selected Event Details Header */}
      {selectedEvent && (
        <div style={{ background: '#0a0a0a', border: '1px solid #262626', borderRadius: '8px', padding: '1.15rem', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <span style={{ fontSize: '0.88rem', color: '#e5e5e5', fontWeight: 600 }}>{selectedEvent.timestamp}</span>
                <span style={{ 
                  background: selectedEvent.type === 'failed' ? '#7f1d1d' : '#064e3b', 
                  color: selectedEvent.type === 'failed' ? '#fca5a5' : '#6ee7b7', 
                  padding: '0.15rem 0.5rem', 
                  borderRadius: '4px', 
                  fontSize: '0.72rem', 
                  fontWeight: 600,
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '4px'
                }}>
                  {selectedEvent.type === 'failed' ? (
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  ) : (
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><polyline points="20 6 9 17 4 12"/></svg>
                  )}
                  {selectedEvent.type === 'failed' ? 'Failed' : 'Succeeded'}
                </span>
                <span style={{ fontFamily: 'monospace', color: '#a855f7', background: '#1e1b4b', padding: '0.15rem 0.4rem', borderRadius: '4px', fontSize: '0.75rem' }}>
                  {selectedEvent.hash}
                </span>
              </div>
              <div style={{ fontSize: '0.85rem', color: '#cbd5e1' }}>
                {selectedEvent.message}
              </div>
            </div>

            {/* Rollback button with functional onClick */}
            <button
              type="button"
              onClick={handleRollbackSelected}
              disabled={actionLoading}
              style={{ 
                background: 'transparent', 
                border: '1px solid #404040', 
                color: '#e5e5e5', 
                padding: '0.45rem 0.85rem', 
                borderRadius: '6px', 
                fontSize: '0.78rem', 
                fontWeight: 600,
                display: 'flex', 
                alignItems: 'center', 
                gap: '0.4rem',
                cursor: actionLoading ? 'wait' : 'pointer'
              }}
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 10h10a8 8 0 0 1 8 8v2M3 10l6 6m-6-6l6-6"/></svg>
              Rollback to this commit
            </button>
          </div>

          <div style={{ 
            marginTop: '0.25rem', 
            paddingLeft: '0.75rem', 
            borderLeft: `3px solid ${selectedEvent.type === 'failed' ? '#ef4444' : '#10b981'}`,
            display: 'flex',
            flexDirection: 'column',
            gap: '0.2rem'
          }}>
            <div style={{ fontSize: '0.85rem', fontWeight: 600, color: '#f5f5f5' }}>
              {selectedEvent.detail || 'Automated deployment completed via Git Sync.'}
            </div>
            <div style={{ fontSize: '0.75rem', color: '#a3a3a3' }}>
              Author: {selectedEvent.author || 'Deploy Agent'} • Branch: {selectedEvent.branch || 'main'}
            </div>
          </div>
        </div>
      )}

      {/* Terminal Log Console */}
      <div 
        ref={terminalContainerRef}
        style={{ 
          display: 'flex', 
          flexDirection: 'column', 
          flex: 1, 
          background: themeStyles.bg, 
          border: `1px solid ${themeStyles.border}`, 
          borderRadius: isFullscreen ? '0' : '8px', 
          overflow: 'hidden',
          boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.3)',
          ...(isFullscreen ? { position: 'fixed', top: 0, left: 0, width: '100vw', height: '100vh', zIndex: 99999 } : {})
        }}
      >
        {/* Terminal Header & Toolbar */}
        <div style={{ 
          display: 'flex', 
          alignItems: 'center', 
          justifyContent: 'space-between', 
          borderBottom: `1px solid ${themeStyles.border}`, 
          background: themeStyles.headerBg, 
          padding: '0.4rem 0.6rem',
          flexWrap: 'wrap',
          gap: '0.5rem',
        }}>
          {/* Left Side: Filter Dropdown & Search Input */}
          <div style={{ display: 'flex', alignItems: 'center', height: '32px', flexWrap: 'wrap', gap: '0.35rem' }}>
            
            {/* Filter Dropdown */}
            <div style={{ position: 'relative', height: '100%' }}>
              <button 
                type="button"
                onClick={(e) => { e.stopPropagation(); setIsAllLogsMenuOpen(!isAllLogsMenuOpen); setIsMoreMenuOpen(false); }}
                style={{ 
                  display: 'flex', 
                  alignItems: 'center', 
                  gap: '0.45rem', 
                  background: 'transparent', 
                  border: `1px solid ${themeStyles.border}`,
                  borderRadius: '4px',
                  color: themeStyles.text, 
                  fontSize: '0.8rem', 
                  fontWeight: 600,
                  padding: '0 0.75rem', 
                  height: '100%', 
                  cursor: 'pointer' 
                }}
              >
                <span>{logFilter}</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              </button>

              {isAllLogsMenuOpen && (
                <div style={{ 
                  position: 'absolute', 
                  top: '110%', 
                  left: 0, 
                  background: '#0f0f0f', 
                  border: '1px solid #262626', 
                  borderRadius: '6px', 
                  width: '180px', 
                  padding: '0.35rem', 
                  zIndex: 200, 
                  boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.7)' 
                }}>
                  {['All logs', 'Build logs', 'Application logs', 'Errors & Warnings'].map(opt => (
                    <div 
                      key={opt}
                      onClick={(e) => { e.stopPropagation(); setLogFilter(opt); setIsAllLogsMenuOpen(false); }}
                      style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        gap: '0.65rem', 
                        padding: '0.45rem 0.65rem', 
                        cursor: 'pointer', 
                        borderRadius: '4px', 
                        background: logFilter === opt ? '#1e293b' : 'transparent',
                        color: logFilter === opt ? '#38bdf8' : '#cbd5e1',
                        fontSize: '0.8rem',
                        fontWeight: logFilter === opt ? 600 : 400
                      }}
                    >
                      <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: logFilter === opt ? '#38bdf8' : '#475569' }} />
                      <span>{opt}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Search Input with Match Count */}
            <div style={{ 
              display: 'flex', 
              alignItems: 'center', 
              background: '#00000033', 
              border: `1px solid ${themeStyles.border}`, 
              borderRadius: '4px',
              padding: '0 0.5rem', 
              height: '100%',
              color: themeStyles.textMuted
            }}>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input 
                type="text" 
                placeholder="Search logs..." 
                value={logSearchText}
                onChange={(e) => setLogSearchText(e.target.value)}
                style={{ 
                  background: 'transparent', 
                  border: 'none', 
                  color: themeStyles.text, 
                  outline: 'none', 
                  marginLeft: '0.4rem', 
                  fontSize: '0.78rem', 
                  width: '130px' 
                }} 
              />
              {logSearchText && (
                <span style={{ fontSize: '0.7rem', color: '#94a3b8', background: '#1e293b', padding: '0.1rem 0.35rem', borderRadius: '3px' }}>
                  {processedLogs.length}
                </span>
              )}
            </div>
          </div>

          {/* Right Side: Channel Selector & Utility Controls */}
          <div style={{ display: 'flex', alignItems: 'center', height: '32px', flexWrap: 'wrap', gap: '0.35rem' }}>
            
            {/* Storage Logs Dropdown */}
            <div style={{ position: 'relative', height: '100%' }}>
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); setShowLogFilesDropdown(!showLogFilesDropdown); }}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '0.35rem',
                  background: 'transparent',
                  border: `1px solid ${themeStyles.border}`,
                  borderRadius: '4px',
                  color: '#38bdf8',
                  padding: '0 0.65rem',
                  height: '100%',
                  fontSize: '0.76rem',
                  fontWeight: 600,
                  cursor: 'pointer'
                }}
                title="Open raw log files from storage/logs"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>{selectedLogFile ? selectedLogFile.name : `Files (${logFilesInfo?.count || 0})`}</span>
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 12 15 18 9"/></svg>
              </button>

              {showLogFilesDropdown && (
                <div 
                  onClick={(e) => e.stopPropagation()}
                  style={{
                    position: 'absolute',
                    top: '115%',
                    right: 0,
                    width: '300px',
                    background: '#0f172a',
                    border: '1px solid #334155',
                    borderRadius: '6px',
                    boxShadow: '0 10px 25px -5px rgba(0,0,0,0.7)',
                    zIndex: 200,
                    padding: '0.4rem 0',
                    maxHeight: '300px',
                    overflowY: 'auto'
                  }}
                >
                  <div style={{ padding: '0.35rem 0.75rem', borderBottom: '1px solid #1e293b', fontSize: '0.72rem', color: '#94a3b8', fontWeight: 600, display: 'flex', justifyContent: 'space-between' }}>
                    <span>storage/logs ({logFilesInfo?.count || 0})</span>
                    <span>{logFilesInfo?.total_size_mb || 0} MB Total</span>
                  </div>
                  {(!logFilesInfo?.files || logFilesInfo.files.length === 0) ? (
                    <div style={{ padding: '0.75rem', color: '#64748b', fontSize: '0.75rem', textAlign: 'center' }}>No log files found</div>
                  ) : (
                    logFilesInfo.files.map((file, idx) => (
                      <button
                        key={idx}
                        type="button"
                        onClick={() => {
                          setSelectedLogFile(file);
                          setChannelTab('file');
                          setShowLogFilesDropdown(false);
                        }}
                        style={{
                          width: '100%',
                          textAlign: 'left',
                          padding: '0.45rem 0.75rem',
                          background: selectedLogFile?.name === file.name && channelTab === 'file' ? '#1e3a8a' : 'transparent',
                          border: 'none',
                          color: '#f8fafc',
                          fontSize: '0.76rem',
                          cursor: 'pointer',
                          display: 'flex',
                          justifyContent: 'space-between',
                          alignItems: 'center',
                          borderBottom: '1px solid #172554'
                        }}
                      >
                        <div>
                          <div style={{ fontWeight: 600, color: '#38bdf8' }}>{file.name}</div>
                          <div style={{ fontSize: '0.66rem', color: '#64748b' }}>{file.modified}</div>
                        </div>
                        <span style={{ fontSize: '0.7rem', color: '#94a3b8', background: '#1e293b', padding: '0.1rem 0.35rem', borderRadius: '3px' }}>
                          {file.size_kb > 1024 ? `${file.size_mb} MB` : `${file.size_kb} KB`}
                        </span>
                      </button>
                    ))
                  )}
                </div>
              )}
            </div>

            {/* Real Dynamic Timestamp Range (Instead of Hardcoded Aug 5 Mock) */}
            <div style={{ 
              display: 'flex', 
              alignItems: 'center', 
              gap: '0.35rem', 
              background: 'transparent', 
              border: `1px solid ${themeStyles.border}`, 
              borderRadius: '4px',
              color: themeStyles.textMuted, 
              fontSize: '0.75rem', 
              padding: '0 0.6rem', 
              height: '100%' 
            }}>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>{logTimingInfo.start} - {logTimingInfo.end}</span>
              {logTimingInfo.duration && (
                <span style={{ color: '#10b981', fontWeight: 600, marginLeft: '2px' }}>({logTimingInfo.duration})</span>
              )}
            </div>

            {/* Local Timezone Indicator */}
            <div style={{ 
              display: 'flex', 
              alignItems: 'center', 
              padding: '0 0.5rem', 
              color: themeStyles.textMuted, 
              fontSize: '0.72rem', 
              border: `1px solid ${themeStyles.border}`, 
              borderRadius: '4px',
              height: '100%' 
            }}>
              {localTimezone}
            </div>

            {/* Fullscreen Toggle Button */}
            <button 
              type="button"
              onClick={toggleFullscreen}
              style={{ 
                background: isFullscreen ? '#1e3a8a' : 'transparent', 
                border: `1px solid ${themeStyles.border}`, 
                borderRadius: '4px',
                color: isFullscreen ? '#38bdf8' : themeStyles.textMuted, 
                padding: '0 0.55rem', 
                height: '100%', 
                cursor: 'pointer', 
                display: 'flex', 
                alignItems: 'center' 
              }}
              title={isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'}
            >
              {isFullscreen ? (
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>
              ) : (
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
              )}
            </button>

            {/* More Menu Dropdown */}
            <div style={{ position: 'relative', height: '100%' }}>
              <button 
                type="button"
                onClick={(e) => { e.stopPropagation(); setIsMoreMenuOpen(!isMoreMenuOpen); setIsAllLogsMenuOpen(false); }}
                style={{ 
                  background: isMoreMenuOpen ? '#262626' : 'transparent', 
                  border: `1px solid ${themeStyles.border}`, 
                  borderRadius: '4px',
                  color: isMoreMenuOpen ? '#ffffff' : themeStyles.textMuted, 
                  padding: '0 0.55rem', 
                  height: '100%', 
                  cursor: 'pointer', 
                  display: 'flex', 
                  alignItems: 'center' 
                }}
                title="Terminal Display Options"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
              </button>

              {isMoreMenuOpen && (
                <div 
                  onClick={(e) => e.stopPropagation()}
                  style={{ 
                    position: 'absolute', 
                    top: '115%', 
                    right: 0, 
                    background: '#0f0f0f', 
                    border: '1px solid #262626', 
                    borderRadius: '6px', 
                    width: '210px', 
                    zIndex: 200, 
                    boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.7)' 
                  }}
                >
                  {/* Copy Logs */}
                  <div 
                    onClick={() => {
                      const text = processedLogs.map(l => l.text).join('\n') || activeRawLog;
                      navigator.clipboard.writeText(text);
                      setIsMoreMenuOpen(false);
                      showToast('Logs copied to clipboard!', 'success');
                    }}
                    style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', padding: '0.65rem 0.85rem', cursor: 'pointer', borderBottom: '1px solid #262626', color: '#d4d4d8', fontSize: '0.8rem' }}
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span>Copy all logs</span>
                  </div>

                  {/* Download Logs */}
                  <div 
                    onClick={() => {
                      setIsMoreMenuOpen(false);
                      handleDownloadLog();
                    }}
                    style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', padding: '0.65rem 0.85rem', cursor: 'pointer', borderBottom: '1px solid #262626', color: '#d4d4d8', fontSize: '0.8rem' }}
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Download log file</span>
                  </div>

                  {/* Line Wrap Toggle */}
                  <div 
                    onClick={() => setWrapLines(!wrapLines)}
                    style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.65rem 0.85rem', cursor: 'pointer', borderBottom: '1px solid #262626', color: '#d4d4d8', fontSize: '0.8rem' }}
                  >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                      <span>Word Wrap</span>
                    </div>
                    <span style={{ fontSize: '0.72rem', color: wrapLines ? '#10b981' : '#737373', fontWeight: 600 }}>
                      {wrapLines ? 'ON' : 'OFF'}
                    </span>
                  </div>

                  {/* Sort Order */}
                  <div style={{ padding: '0.5rem 0.85rem', borderBottom: '1px solid #262626' }}>
                    <div style={{ fontSize: '0.68rem', color: '#737373', letterSpacing: '0.05em', marginBottom: '0.35rem' }}>SORT ORDER</div>
                    <div 
                      onClick={() => setLogOrder('Ascending')}
                      style={{ display: 'flex', justifyContent: 'space-between', padding: '0.25rem 0', cursor: 'pointer', color: logOrder === 'Ascending' ? '#38bdf8' : '#a3a3a3', fontSize: '0.78rem' }}
                    >
                      <span>Ascending (Newest last)</span>
                      {logOrder === 'Ascending' && <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>}
                    </div>
                    <div 
                      onClick={() => setLogOrder('Descending')}
                      style={{ display: 'flex', justifyContent: 'space-between', padding: '0.25rem 0', cursor: 'pointer', color: logOrder === 'Descending' ? '#38bdf8' : '#a3a3a3', fontSize: '0.78rem' }}
                    >
                      <span>Descending (Newest first)</span>
                      {logOrder === 'Descending' && <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>}
                    </div>
                  </div>

                  {/* Themes Selector */}
                  <div style={{ padding: '0.5rem 0.85rem' }}>
                    <div style={{ fontSize: '0.68rem', color: '#737373', letterSpacing: '0.05em', marginBottom: '0.35rem' }}>CONSOLE THEME</div>
                    {[
                      { id: 'dark', label: 'Dark Slate' },
                      { id: 'oled', label: 'Pitch Black (OLED)' },
                      { id: 'matrix', label: 'Matrix Green' },
                      { id: 'amber', label: 'Terminal Amber' },
                    ].map(th => (
                      <div 
                        key={th.id}
                        onClick={() => setTerminalTheme(th.id)}
                        style={{ display: 'flex', justifyContent: 'space-between', padding: '0.25rem 0', cursor: 'pointer', color: terminalTheme === th.id ? '#a855f7' : '#a3a3a3', fontSize: '0.78rem' }}
                      >
                        <span>{th.label}</span>
                        {terminalTheme === th.id && <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Channel Tab Bar (When viewing live system) */}
        {!selectedEvent && (
          <div style={{ 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'space-between', 
            borderBottom: `1px solid ${themeStyles.border}`, 
            background: themeStyles.headerBg, 
            padding: '0.35rem 0.6rem',
            gap: '0.5rem',
            flexWrap: 'wrap',
          }}>
            <div style={{ display: 'inline-flex', background: '#00000044', padding: '0.15rem', borderRadius: '5px', gap: '0.2rem', flexWrap: 'wrap' }}>
              {[
                { id: 'all', label: 'All Deploy Logs', icon: <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/> },
                { id: 'git', label: 'Git Sync Log', icon: <path d="M18 9a9 9 0 0 1-9 9M6 9a9 9 0 0 0 9 9M6 21v-4M18 3v4"/> },
                { id: 'ssh', label: 'SSH Stream', count: sshSessions.length, icon: <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/> },
                { id: 'sftp', label: 'SFTP Subsystem', count: sftpSessions, icon: <polyline points="16 16 12 12 8 16"/> },
                { id: 'scp', label: 'SCP Transfers', count: scpSessions, icon: <polyline points="14 2 14 8 20 8"/> },
              ].map(tab => (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => { setChannelTab(tab.id); setSelectedLogFile(null); }}
                  style={{
                    background: channelTab === tab.id ? themeStyles.activeTabBg : 'transparent',
                    color: channelTab === tab.id ? '#ffffff' : themeStyles.textMuted,
                    border: 'none',
                    borderRadius: '4px',
                    padding: '0.3rem 0.65rem',
                    fontSize: '0.74rem',
                    fontWeight: 600,
                    cursor: 'pointer',
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '0.35rem',
                    transition: 'all 0.15s ease',
                  }}
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    {tab.icon}
                  </svg>
                  <span>{tab.label}</span>
                  {tab.count !== undefined && tab.count > 0 && (
                    <span style={{ background: '#38bdf8', color: '#0f172a', fontSize: '0.62rem', padding: '0.05rem 0.3rem', borderRadius: '10px', fontWeight: 700 }}>
                      {tab.count}
                    </span>
                  )}
                </button>
              ))}
            </div>

            <span style={{ fontSize: '0.72rem', color: themeStyles.textMuted }}>
              Viewing: <strong style={{ color: themeStyles.text }}>
                {channelTab === 'file' && selectedLogFile 
                  ? `storage/logs/${selectedLogFile.name}` 
                  : (channelTab === 'ssh' ? 'SSH Daemon & Sessions' 
                    : (channelTab === 'sftp' ? 'SFTP File Subsystem' 
                    : (channelTab === 'scp' ? 'SCP Stream' 
                    : (channelTab === 'git' ? 'git-sync.log' : 'Combined Deploy Console'))))}
              </strong>
            </span>
          </div>
        )}

        {/* Terminal Screen & Log Lines */}
        <div 
          ref={consoleRef}
          style={{ 
            overflowY: 'auto', 
            flex: 1, 
            maxHeight: isFullscreen ? 'calc(100vh - 100px)' : '550px', 
            background: themeStyles.bg, 
            padding: '0.75rem 0',
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
          }}
        >
          {commitLogLoading || fileLoading ? (
            <div style={{ padding: '2rem', textAlign: 'center', color: '#38bdf8', fontSize: '0.85rem', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              <span>Loading log stream from server...</span>
            </div>
          ) : fileError ? (
            <div style={{ padding: '1.5rem', color: '#ef4444', fontSize: '0.85rem' }}>
              Error loading log file: {fileError}
            </div>
          ) : processedLogs.length > 0 ? (
            processedLogs.map((log, index) => {
              const showDateHeader = index === 0 || processedLogs[index - 1].dateString !== log.dateString;
              return (
                <React.Fragment key={log.id}>
                  {showDateHeader && (
                    <div style={{ display: 'flex', fontSize: '0.75rem', lineHeight: '1.5', borderBottom: `1px solid ${themeStyles.border}` }}>
                      <div style={{ width: '110px', flexShrink: 0, padding: '0.2rem 0.5rem', color: themeStyles.text, fontWeight: 700, borderRight: `1px solid ${themeStyles.border}`, textAlign: 'center', background: '#00000022' }}>
                        {log.dateString}
                      </div>
                      <div style={{ flexGrow: 1, padding: '0.2rem 0.75rem' }} />
                    </div>
                  )}
                  <div style={{ display: 'flex', fontSize: '0.78rem', lineHeight: '1.55', borderBottom: '1px solid rgba(255, 255, 255, 0.03)' }}>
                    <div style={{ width: '110px', flexShrink: 0, padding: '0.1rem 0.5rem', color: themeStyles.timeColor, borderRight: `1px solid ${themeStyles.border}`, textAlign: 'right', userSelect: 'none' }}>
                      {log.time}
                    </div>
                    <div style={{ 
                      flexGrow: 1, 
                      padding: '0.1rem 0.75rem', 
                      color: /error|fail|fatal/i.test(log.text) ? '#f87171' : (/warn/i.test(log.text) ? '#fbbf24' : (/success|succeeded|done/i.test(log.text) ? '#4ade80' : themeStyles.text)), 
                      whiteSpace: wrapLines ? 'pre-wrap' : 'pre', 
                      wordBreak: 'break-all' 
                    }}>
                      {log.text}
                    </div>
                  </div>
                </React.Fragment>
              );
            })
          ) : (
            <div style={{ display: 'flex', fontSize: '0.78rem', lineHeight: '1.55', padding: '1rem' }}>
              <div style={{ width: '110px', flexShrink: 0, color: themeStyles.timeColor, borderRight: `1px solid ${themeStyles.border}`, textAlign: 'right', paddingRight: '0.5rem' }}>
                --:--:--
              </div>
              <div style={{ paddingLeft: '0.75rem', color: themeStyles.textMuted }}>
                {logSearchText ? 'No matching log entries found for current search filter.' : 'No deployment log lines found. Click "Deploy Latest" above to trigger a fresh deployment.'}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Recent Deployment History List on #deploy */}
      {!selectedEvent && events && events.length > 0 && (
        <div style={{ background: '#0f172a', border: '1px solid #1e293b', borderRadius: '8px', padding: '1.15rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.85rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
              <h3 style={{ margin: 0, fontSize: '0.9rem', fontWeight: 700, color: '#f8fafc' }}>
                Recent Deployments History ({events.length})
              </h3>
            </div>
            <span style={{ fontSize: '0.75rem', color: '#94a3b8' }}>
              Click any deployment to inspect full build logs & rollback
            </span>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem', maxHeight: '220px', overflowY: 'auto' }}>
            {events.slice(0, 10).map((ev, idx) => (
              <div
                key={ev.id || idx}
                onClick={() => {
                  if (onSelectEvent) onSelectEvent(ev);
                }}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  padding: '0.5rem 0.85rem',
                  background: '#020617',
                  border: '1px solid #1e293b',
                  borderRadius: '6px',
                  cursor: 'pointer',
                  transition: 'border-color 0.15s ease',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                  <span style={{ 
                    width: '8px', 
                    height: '8px', 
                    borderRadius: '50%', 
                    background: ev.type === 'failed' ? '#ef4444' : '#10b981' 
                  }} />
                  <span style={{ fontFamily: 'monospace', fontSize: '0.78rem', color: '#38bdf8', fontWeight: 600 }}>
                    {ev.hash}
                  </span>
                  <span style={{ fontSize: '0.8rem', color: '#cbd5e1', maxWidth: '350px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {ev.message}
                  </span>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '0.85rem' }}>
                  <span style={{ fontSize: '0.72rem', color: '#94a3b8' }}>
                    {ev.relative || ev.timestamp}
                  </span>
                  <button
                    type="button"
                    style={{
                      background: '#1e293b',
                      border: '1px solid #334155',
                      color: '#f8fafc',
                      padding: '0.2rem 0.5rem',
                      borderRadius: '4px',
                      fontSize: '0.72rem',
                      fontWeight: 600,
                      cursor: 'pointer',
                    }}
                  >
                    View Logs
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
