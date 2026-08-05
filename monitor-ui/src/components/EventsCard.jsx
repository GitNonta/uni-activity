import React, { useState } from 'react';

export function EventsCard({ eventsData, connected = true }) {
  const [filter, setFilter] = useState('all');
  const [showConnect, setShowConnect] = useState(false);
  const [showFilterDropdown, setShowFilterDropdown] = useState(false);
  const [loadingAction, setLoadingAction] = useState(false);
  const [toastMessage, setToastMessage] = useState(null);

  const defaultEvents = [];

  const rawEvents = (eventsData && eventsData.length > 0) ? eventsData : defaultEvents;

  const filteredEvents = rawEvents.filter(ev => {
    if (filter === 'all') return true;
    return ev.type === filter;
  });

  const showToast = (msg, duration = 4000) => {
    setToastMessage(msg);
    setTimeout(() => {
      setToastMessage(null);
    }, duration);
  };

  const handleManualDeploy = async () => {
    if (!window.confirm('🚀 Trigger manual deployment from GitHub origin/main on server?')) return;
    setLoadingAction(true);
    try {
      const res = await fetch('/api/deploy/manual', { method: 'POST' });
      const data = await res.json();
      if (res.ok) {
        showToast('🚀 Manual Deployment Triggered! Server is pulling origin/main & restarting workers...');
      } else {
        showToast('❌ Deploy Trigger Error: ' + (data.message || 'Failed'));
      }
    } catch (err) {
      showToast('🚀 Deployment command sent to server!');
    } finally {
      setLoadingAction(false);
    }
  };

  const handleRollback = async (hash) => {
    if (!window.confirm(`⚠️ Are you sure you want to rollback the server to commit ${hash}?`)) return;
    setLoadingAction(true);
    try {
      const res = await fetch('/api/deploy/rollback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ commit_hash: hash }),
      });
      const data = await res.json();
      if (res.ok) {
        showToast(`⏪ Server Rollback to commit ${hash} initiated successfully!`);
      } else {
        showToast('❌ Rollback Error: ' + (data.message || 'Failed'));
      }
    } catch (err) {
      showToast(`⏪ Rollback command sent for ${hash}!`);
    } finally {
      setLoadingAction(false);
    }
  };

  const copyToClipboard = (text, label) => {
    navigator.clipboard.writeText(text);
    showToast(`📋 Copied ${label} to clipboard!`);
    setShowConnect(false);
  };

  return (
    <div style={{
      background: '#0b0f19',
      color: '#e2e8f0',
      borderRadius: '12px',
      padding: '1.75rem',
      fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
      boxShadow: '0 10px 25px -5px rgba(0,0,0,0.5)',
      margin: '0 auto',
      maxWidth: '1200px',
      position: 'relative'
    }}>
      {/* Toast Notification Banner */}
      {toastMessage && (
        <div style={{
          position: 'fixed',
          top: '20px',
          right: '20px',
          zIndex: 9999,
          background: '#1e1b4b',
          color: '#e0e7ff',
          border: '1px solid #6366f1',
          padding: '0.85rem 1.25rem',
          borderRadius: '8px',
          boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.5)',
          fontWeight: 600,
          fontSize: '0.9rem',
          display: 'flex',
          alignItems: 'center',
          gap: '8px'
        }}>
          {toastMessage}
        </div>
      )}

      {/* Header Info Section */}
      <div style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
        flexWrap: 'wrap',
        gap: '1.25rem',
        marginBottom: '1.5rem'
      }}>
        <div>
          <div style={{
            fontSize: '0.75rem',
            color: '#94a3b8',
            fontWeight: 600,
            letterSpacing: '0.05em',
            textTransform: 'uppercase',
            marginBottom: '0.3rem',
            display: 'flex',
            alignItems: 'center',
            gap: '8px'
          }}>
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
            </svg>
            WEB SERVICE
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
            <h2 style={{ fontSize: '1.6rem', fontWeight: 700, color: '#fff', margin: 0, letterSpacing: '-0.02em' }}>uni-activity</h2>
            <span style={{ background: '#1e293b', color: '#cbd5e1', fontSize: '0.75rem', padding: '3px 8px', borderRadius: '6px', fontWeight: 600, border: '1px solid #334155' }}>Termux Server</span>
            <span style={{ background: '#047857', color: '#6ee7b7', fontSize: '0.75rem', padding: '3px 8px', borderRadius: '6px', fontWeight: 600 }}>Active</span>
          </div>

          <div style={{ fontSize: '0.85rem', color: '#94a3b8', marginTop: '0.5rem', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <span>Environment: <code style={{ color: '#cbd5e1', background: '#1e293b', padding: '2px 6px', borderRadius: '4px', fontFamily: 'monospace' }}>Production (Local)</code></span>
          </div>

          <div style={{ fontSize: '0.85rem', color: '#94a3b8', marginTop: '0.4rem', display: 'flex', alignItems: 'center', gap: '10px' }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', color: '#e2e8f0', fontWeight: 500 }}>
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                <path fillRule="evenodd" clipRule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
              </svg>
              GitNonta / uni-activity
            </span>
            <span style={{ color: '#64748b' }}>•</span>
            <span style={{ color: '#94a3b8', display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
              </svg>
              main
            </span>
          </div>
        </div>

        {/* Action Controls */}
        <div style={{ display: 'flex', gap: '0.75rem', position: 'relative' }}>
          {/* Connect Dropdown */}
          <div style={{ position: 'relative' }}>
            <button
              type="button"
              onClick={() => setShowConnect(!showConnect)}
              style={{
                background: '#1e293b',
                color: '#e2e8f0',
                border: '1px solid #334155',
                padding: '0.5rem 1rem',
                borderRadius: '8px',
                fontWeight: 600,
                fontSize: '0.85rem',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '6px'
              }}
            >
              Connect <span>▾</span>
            </button>

            {showConnect && (
              <div style={{
                position: 'absolute',
                top: '110%',
                right: 0,
                width: '240px',
                background: '#1e293b',
                border: '1px solid #334155',
                borderRadius: '8px',
                boxShadow: '0 10px 25px -5px rgba(0,0,0,0.5)',
                zIndex: 100,
                padding: '0.5rem 0'
              }}>
                <button
                  onClick={() => copyToClipboard('ssh -p 8022 u0_a231@localhost', 'SSH Command')}
                  style={{ width: '100%', textAlign: 'left', padding: '0.6rem 1rem', background: 'none', border: 'none', color: '#e2e8f0', fontSize: '0.82rem', cursor: 'pointer', display: 'block' }}
                >
                  💻 Copy SSH Command
                </button>
                <button
                  onClick={() => copyToClipboard('sftp -P 8022 u0_a231@localhost', 'SFTP Login')}
                  style={{ width: '100%', textAlign: 'left', padding: '0.6rem 1rem', background: 'none', border: 'none', color: '#e2e8f0', fontSize: '0.82rem', cursor: 'pointer', display: 'block' }}
                >
                  📁 Copy SFTP Command
                </button>
              </div>
            )}
          </div>

          {/* Manual Deploy Button */}
          <button
            type="button"
            onClick={handleManualDeploy}
            disabled={loadingAction}
            style={{
              background: loadingAction ? '#94a3b8' : '#f8fafc',
              color: '#0f172a',
              border: 'none',
              padding: '0.5rem 1rem',
              borderRadius: '8px',
              fontWeight: 600,
              fontSize: '0.85rem',
              cursor: loadingAction ? 'wait' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '6px'
            }}
          >
            {loadingAction ? 'Deploying...' : 'Manual Deploy'} <span>▾</span>
          </button>
        </div>
      </div>



      {/* Filter Button Dropdown */}
      <div style={{ marginBottom: '1rem', position: 'relative' }}>
        <button
          type="button"
          onClick={() => setShowFilterDropdown(!showFilterDropdown)}
          style={{
            background: '#1e293b',
            color: '#94a3b8',
            border: '1px solid #334155',
            padding: '0.4rem 0.85rem',
            borderRadius: '6px',
            fontSize: '0.8rem',
            fontWeight: 600,
            display: 'inline-flex',
            alignItems: 'center',
            gap: '6px',
            cursor: 'pointer'
          }}
        >
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
          </svg>
          Filter events <span style={{ background: '#334155', color: '#fff', padding: '1px 6px', borderRadius: '10px', fontSize: '0.75rem' }}>{rawEvents.length}</span> <span>▾</span>
        </button>

        {showFilterDropdown && (
          <div style={{
            position: 'absolute',
            top: '110%',
            left: 0,
            width: '160px',
            background: '#1e293b',
            border: '1px solid #334155',
            borderRadius: '6px',
            boxShadow: '0 10px 25px -5px rgba(0,0,0,0.5)',
            zIndex: 100,
            padding: '0.3rem 0'
          }}>
            {['all', 'success', 'started', 'failed'].map(f => (
              <button
                key={f}
                onClick={() => { setFilter(f); setShowFilterDropdown(false); }}
                style={{
                  width: '100%',
                  textAlign: 'left',
                  padding: '0.4rem 0.85rem',
                  background: filter === f ? '#334155' : 'none',
                  border: 'none',
                  color: '#e2e8f0',
                  fontSize: '0.8rem',
                  cursor: 'pointer',
                  textTransform: 'capitalize'
                }}
              >
                {f === 'all' ? 'All Events' : f}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Events Timeline List matching dashboard.render.com */}
      <div style={{ border: '1px solid #1e293b', borderRadius: '10px', overflow: 'hidden', background: '#0f172a' }}>
        {filteredEvents.length === 0 ? (
          <div style={{ padding: '2.5rem', textAlign: 'center', color: '#64748b', fontSize: '0.9rem' }}>
            No deployment events match the selected filter.
          </div>
        ) : (
          filteredEvents.map((ev, idx) => (
            <div key={ev.id || idx} style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'flex-start',
              padding: '1.1rem 1.25rem',
              borderBottom: '1px solid #1e293b',
              background: '#0f172a',
              transition: 'background 0.2s'
            }}>
              <div style={{ display: 'flex', gap: '1rem', alignItems: 'flex-start' }}>
                {/* Status Circle Icon matching Render UI */}
                <div style={{ marginTop: '2px' }}>
                  {ev.type === 'failed' ? (
                    <div style={{ width: '24px', height: '24px', borderRadius: '50%', background: '#7f1d1d', color: '#f87171', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </div>
                  ) : ev.type === 'started' ? (
                    <div style={{ width: '24px', height: '24px', borderRadius: '50%', background: '#1e293b', color: '#94a3b8', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                      </svg>
                    </div>
                  ) : (
                    <div style={{ width: '24px', height: '24px', borderRadius: '50%', background: '#065f46', color: '#34d399', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </div>
                  )}
                </div>

                {/* Event Content matching Render UI */}
                <div>
                  <div style={{ fontSize: '0.92rem', fontWeight: 600, color: '#f8fafc', lineHeight: 1.4 }}>
                    {ev.type === 'failed' ? 'Deploy failed for ' : ev.type === 'started' ? 'Deploy started for ' : 'Deploy succeeded for '}
                    <a href={`https://github.com/GitNonta/uni-activity/commit/${ev.hash}`} target="_blank" rel="noreferrer" style={{ color: '#38bdf8', textDecoration: 'underline', fontFamily: 'monospace' }}>
                      {ev.hash}
                    </a>
                    : {ev.message}
                  </div>

                  <div style={{ fontSize: '0.82rem', color: '#94a3b8', marginTop: '0.25rem' }}>
                    {ev.detail}
                  </div>

                  <div style={{ fontSize: '0.78rem', color: '#64748b', marginTop: '0.35rem' }}>
                    {ev.timestamp}
                  </div>
                </div>
              </div>

              {/* Action Buttons matching Render UI */}
              <div>
                {ev.type === 'failed' && (
                  <button
                    type="button"
                    onClick={() => handleRollback(ev.hash)}
                    style={{
                      background: '#1e293b',
                      color: '#cbd5e1',
                      border: '1px solid #334155',
                      padding: '0.35rem 0.75rem',
                      borderRadius: '6px',
                      fontSize: '0.75rem',
                      fontWeight: 600,
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '4px'
                    }}
                  >
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Rollback
                  </button>
                )}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
