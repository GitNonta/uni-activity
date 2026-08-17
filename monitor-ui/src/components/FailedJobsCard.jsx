import React, { useState, useEffect } from 'react';

export function FailedJobsCard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [queueFilter, setQueueFilter] = useState('');
  const [page, setPage] = useState(1);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [lastUpdated, setLastUpdated] = useState('');
  const [selectedJob, setSelectedJob] = useState(null);
  const [actionLoading, setActionLoading] = useState(null);
  const [message, setMessage] = useState(null);

  const showToast = (text, type = 'success') => {
    setMessage({ text, type });
    setTimeout(() => setMessage(null), 4000);
  };

  const fetchFailedJobs = async (targetPage = page) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('page', targetPage);
      if (search) params.set('search', search);
      if (queueFilter) params.set('queue', queueFilter);

      const res = await fetch(`/api/failed-jobs?${params.toString()}`, {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });

      if (res.ok) {
        const json = await res.json();
        if (json.data) {
          setData(json.data);
          setLastUpdated(new Date().toLocaleTimeString('th-TH'));
          return;
        }
      }
    } catch (err) {
      console.warn('Failed to fetch from /api/failed-jobs', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFailedJobs(1);
    setPage(1);
  }, [search, queueFilter]);

  useEffect(() => {
    let interval = null;
    if (autoRefresh) {
      interval = setInterval(() => {
        fetchFailedJobs(page);
      }, 6000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [autoRefresh, page, search, queueFilter]);

  // Actions
  const handleRetry = async (id) => {
    setActionLoading(`retry-${id}`);
    try {
      const res = await fetch(`/api/failed-jobs/${id}/retry`, {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (json.status === 'ok') {
        showToast(json.message || 'ส่งงานกลับเข้าคิวเรียบร้อยแล้ว');
        if (selectedJob && selectedJob.id === id) setSelectedJob(null);
        fetchFailedJobs(page);
      } else {
        showToast(json.message || 'เกิดข้อผิดพลาดในการลองใหม่', 'error');
      }
    } catch (err) {
      showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการงานนี้ทิ้ง?')) return;
    setActionLoading(`delete-${id}`);
    try {
      const res = await fetch(`/api/failed-jobs/${id}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (json.status === 'ok') {
        showToast(json.message || 'ลบงานที่ล้มเหลวเรียบร้อยแล้ว');
        if (selectedJob && selectedJob.id === id) setSelectedJob(null);
        fetchFailedJobs(page);
      } else {
        showToast(json.message || 'เกิดข้อผิดพลาดในการลบ', 'error');
      }
    } catch (err) {
      showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    } finally {
      setActionLoading(null);
    }
  };

  const handleRetryAll = async () => {
    if (!window.confirm('คุณต้องการลองใหม่สำหรับงานที่ล้มเหลวทั้งหมดใช่หรือไม่?')) return;
    setActionLoading('retry-all');
    try {
      const res = await fetch('/api/failed-jobs/retry-all', {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (json.status === 'ok') {
        showToast(json.message || 'ส่งงานทั้งหมดกลับเข้าคิวเรียบร้อยแล้ว');
        fetchFailedJobs(1);
      } else {
        showToast(json.message || 'เกิดข้อผิดพลาด', 'error');
      }
    } catch (err) {
      showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    } finally {
      setActionLoading(null);
    }
  };

  const handleFlushAll = async () => {
    if (!window.confirm('คำเตือน: คุณต้องการล้างประวัติงานที่ล้มเหลวทั้งหมดทิ้งใช่หรือไม่? ข้อมูลจะไม่สามารถกู้คืนได้')) return;
    setActionLoading('flush-all');
    try {
      const res = await fetch('/api/failed-jobs/flush', {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (json.status === 'ok') {
        showToast(json.message || 'ล้างประวัติงานทั้งหมดเรียบร้อยแล้ว');
        fetchFailedJobs(1);
      } else {
        showToast(json.message || 'เกิดข้อผิดพลาด', 'error');
      }
    } catch (err) {
      showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    } finally {
      setActionLoading(null);
    }
  };

  const jobs = data?.failed_jobs || [];
  const totalFailed = data?.total_failed ?? 0;
  const queues = data?.queues || [];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem', width: '100%', position: 'relative' }}>

      {/* Toast Notification */}
      {message && (
        <div style={{
          position: 'fixed',
          top: 20,
          right: 20,
          zIndex: 9999,
          background: message.type === 'error' ? '#fee2e2' : '#dcfce7',
          color: message.type === 'error' ? '#991b1b' : '#166534',
          border: `1px solid ${message.type === 'error' ? '#fca5a5' : '#86efac'}`,
          padding: '0.75rem 1.25rem',
          borderRadius: 10,
          boxShadow: '0 10px 25px rgba(0,0,0,0.1)',
          fontSize: '0.875rem',
          fontWeight: 600,
          display: 'flex',
          alignItems: 'center',
          gap: '0.6rem',
          animation: 'fadeIn 0.2s ease'
        }}>
          {message.type === 'error' ? (
            <svg width="18" height="18" fill="none" stroke="#dc2626" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ) : (
            <svg width="18" height="18" fill="none" stroke="#16a34a" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
            </svg>
          )}
          <span>{message.text}</span>
        </div>
      )}

      {/* ── 1. Top Header & Action Controls ───────────────────────────── */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', flexWrap: 'wrap' }}>
            <h2 style={{ margin: 0, fontSize: '1.5rem', fontWeight: 800, color: '#0f172a', display: 'flex', alignItems: 'center', gap: '0.6rem' }}>
              <span style={{
                display: 'inline-block',
                width: 10,
                height: 10,
                borderRadius: '50%',
                background: totalFailed > 0 ? '#ef4444' : '#10b981',
                boxShadow: totalFailed > 0 ? '0 0 10px #ef4444' : '0 0 10px #10b981'
              }}></span>
              คิวงานที่ล้มเหลว
              <span style={{ fontSize: '1.05rem', fontWeight: 500, color: '#64748b' }}>Failed Queue Jobs</span>
            </h2>
            {totalFailed > 0 ? (
              <span style={{ background: '#fee2e2', color: '#b91c1c', fontSize: '0.75rem', fontWeight: 700, padding: '0.2rem 0.6rem', borderRadius: 9999, border: '1px solid #fecaca' }}>
                {totalFailed} งานล้มเหลวค้างอยู่
              </span>
            ) : (
              <span style={{ background: '#dcfce7', color: '#15803d', fontSize: '0.75rem', fontWeight: 700, padding: '0.2rem 0.6rem', borderRadius: 9999, border: '1px solid #bbf7d0' }}>
                0 งานล้มเหลว (สมบูรณ์ 100%)
              </span>
            )}
          </div>
          <p style={{ margin: '0.35rem 0 0 0', color: '#64748b', fontSize: '0.875rem' }}>
            ศูนย์กลางตรวจสอบและจัดการข้อผิดพลาดของงานเบื้องหลัง (Background Worker Pipeline): ตรวจสอบ Exception Stack Trace, รองรับการ Retry และ Flush คิวงาน
          </p>
        </div>

        {/* Global Action Buttons */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
          {totalFailed > 0 && (
            <>
              <button
                onClick={handleRetryAll}
                disabled={actionLoading === 'retry-all'}
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '0.4rem',
                  background: '#ea580c',
                  color: '#fff',
                  border: 'none',
                  padding: '0.45rem 0.85rem',
                  borderRadius: 8,
                  fontSize: '0.825rem',
                  fontWeight: 700,
                  cursor: 'pointer',
                  boxShadow: '0 2px 6px rgba(234, 88, 12, 0.3)'
                }}
              >
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>ลองใหม่ทั้งหมด ({totalFailed})</span>
              </button>

              <button
                onClick={handleFlushAll}
                disabled={actionLoading === 'flush-all'}
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '0.4rem',
                  background: '#fff',
                  color: '#ef4444',
                  border: '1px solid #fca5a5',
                  padding: '0.45rem 0.85rem',
                  borderRadius: 8,
                  fontSize: '0.825rem',
                  fontWeight: 600,
                  cursor: 'pointer'
                }}
              >
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <span>ล้างทั้งหมด</span>
              </button>
            </>
          )}

          <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', background: '#fff', border: '1px solid #e2e8f0', padding: '0.35rem 0.75rem', borderRadius: 8, fontSize: '0.8rem', color: '#475569' }}>
            <input
              type="checkbox"
              id="failedAutoRefresh"
              checked={autoRefresh}
              onChange={(e) => setAutoRefresh(e.target.checked)}
              style={{ cursor: 'pointer', accentColor: '#ea580c' }}
            />
            <label htmlFor="failedAutoRefresh" style={{ cursor: 'pointer', userSelect: 'none', fontWeight: 600 }}>ออโต้รีเฟรช (6s)</label>
          </div>

          <button
            onClick={() => fetchFailedJobs(page)}
            disabled={loading}
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '0.4rem',
              background: '#fff',
              border: '1px solid #cbd5e1',
              color: '#334155',
              padding: '0.45rem 0.85rem',
              borderRadius: 8,
              fontSize: '0.825rem',
              fontWeight: 600,
              cursor: 'pointer'
            }}
          >
            <svg style={{ width: 14, height: 14, animation: loading ? 'spin 1s linear infinite' : 'none' }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>รีเฟรช</span>
          </button>
        </div>
      </div>

      {/* ── 2. Filters & Search Bar ───────────────────────────────────── */}
      <div style={{
        background: '#fff',
        border: '1px solid #e2e8f0',
        borderRadius: 14,
        padding: '0.85rem 1.25rem',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: '0.75rem',
        boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flex: 1, minWidth: 260 }}>
          {/* Search Input with SVG Icon */}
          <div style={{ position: 'relative', flex: 1 }}>
            <div style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)', display: 'flex', alignItems: 'center', pointerEvents: 'none' }}>
              <svg width="15" height="15" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              type="text"
              placeholder="ค้นหาตามชื่อ Job, Exception, หรือ UUID..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              style={{
                width: '100%',
                padding: '0.45rem 0.75rem 0.45rem 2.1rem',
                border: '1px solid #cbd5e1',
                borderRadius: 8,
                fontSize: '0.85rem',
                outline: 'none'
              }}
            />
          </div>

          {/* Queue Filter Dropdown */}
          <select
            value={queueFilter}
            onChange={(e) => setQueueFilter(e.target.value)}
            style={{
              padding: '0.45rem 0.75rem',
              border: '1px solid #cbd5e1',
              borderRadius: 8,
              fontSize: '0.85rem',
              background: '#fff',
              outline: 'none',
              cursor: 'pointer'
            }}
          >
            <option value="">ทุกช่องทาง (All Queues)</option>
            {queues.map(q => (
              <option key={q} value={q}>queue:{q}</option>
            ))}
          </select>
        </div>

        <div style={{ fontSize: '0.8rem', color: '#64748b' }}>
          พบ <strong>{data?.total ?? 0}</strong> รายการ {lastUpdated && `· อัปเดต ${lastUpdated}`}
        </div>
      </div>

      {/* ── 3. Table / List of Failed Jobs ───────────────────────────── */}
      <div style={{
        background: '#fff',
        border: '1px solid #e2e8f0',
        borderRadius: 14,
        overflow: 'hidden',
        boxShadow: '0 1px 3px rgba(0,0,0,0.03)'
      }}>
        {jobs.length > 0 ? (
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.875rem' }}>
              <thead>
                <tr style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0', color: '#64748b', fontSize: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                  <th style={{ padding: '0.85rem 1rem' }}>ชื่องาน (Job Name)</th>
                  <th style={{ padding: '0.85rem 1rem' }}>ช่องทางคิว (Queue)</th>
                  <th style={{ padding: '0.85rem 1rem' }}>สาเหตุข้อผิดพลาด (Exception)</th>
                  <th style={{ padding: '0.85rem 1rem' }}>เวลาที่ล้มเหลว</th>
                  <th style={{ padding: '0.85rem 1rem', textAlign: 'right' }}>การจัดการ</th>
                </tr>
              </thead>
              <tbody>
                {jobs.map((job) => {
                  const isRetrying = actionLoading === `retry-${job.id}`;
                  const isDeleting = actionLoading === `delete-${job.id}`;

                  return (
                    <tr key={job.id} style={{ borderBottom: '1px solid #f1f5f9', transition: 'background 0.15s' }} onMouseEnter={(e) => e.currentTarget.style.background = '#f8fafc'} onMouseLeave={(e) => e.currentTarget.style.background = '#fff'}>
                      {/* Name */}
                      <td style={{ padding: '0.85rem 1rem', verticalAlign: 'top' }}>
                        <div style={{ fontWeight: 700, color: '#0f172a', fontSize: '0.9rem' }}>
                          {job.display_name}
                        </div>
                        <div style={{ fontSize: '0.7rem', color: '#94a3b8', fontFamily: 'monospace', marginTop: '0.15rem' }}>
                          UUID: {job.uuid ? job.uuid.substring(0, 18) + '...' : `ID #${job.id}`}
                        </div>
                      </td>

                      {/* Queue */}
                      <td style={{ padding: '0.85rem 1rem', verticalAlign: 'top' }}>
                        <span style={{
                          background: job.queue === 'ai' ? '#e0e7ff' : (job.queue === 'notifications' ? '#dcfce7' : '#f1f5f9'),
                          color: job.queue === 'ai' ? '#4338ca' : (job.queue === 'notifications' ? '#15803d' : '#475569'),
                          padding: '0.2rem 0.55rem',
                          borderRadius: 6,
                          fontSize: '0.75rem',
                          fontWeight: 700
                        }}>
                          queue:{job.queue}
                        </span>
                      </td>

                      {/* Exception */}
                      <td style={{ padding: '0.85rem 1rem', verticalAlign: 'top', maxWidth: 380 }}>
                        <div style={{ color: '#b91c1c', fontWeight: 600, fontSize: '0.8rem', wordBreak: 'break-word' }}>
                          {job.exception_summary}
                        </div>
                        <div style={{ fontSize: '0.725rem', color: '#64748b', marginTop: '0.2rem' }}>
                          Connection: {job.connection}
                        </div>
                      </td>

                      {/* Timestamp */}
                      <td style={{ padding: '0.85rem 1rem', verticalAlign: 'top', whiteSpace: 'nowrap', fontSize: '0.8rem', color: '#64748b' }}>
                        {job.failed_at}
                      </td>

                      {/* Actions */}
                      <td style={{ padding: '0.85rem 1rem', verticalAlign: 'top', textAlign: 'right', whiteSpace: 'nowrap' }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
                          <button
                            onClick={() => setSelectedJob(job)}
                            style={{
                              display: 'inline-flex',
                              alignItems: 'center',
                              gap: '0.35rem',
                              background: '#f1f5f9',
                              color: '#334155',
                              border: '1px solid #cbd5e1',
                              padding: '0.3rem 0.6rem',
                              borderRadius: 6,
                              fontSize: '0.75rem',
                              fontWeight: 600,
                              cursor: 'pointer'
                            }}
                            title="ดู Stack Trace & Payload"
                          >
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>ดูข้อมูล</span>
                          </button>

                          <button
                            onClick={() => handleRetry(job.id)}
                            disabled={isRetrying}
                            style={{
                              display: 'inline-flex',
                              alignItems: 'center',
                              gap: '0.35rem',
                              background: '#ea580c',
                              color: '#fff',
                              border: 'none',
                              padding: '0.3rem 0.6rem',
                              borderRadius: 6,
                              fontSize: '0.75rem',
                              fontWeight: 700,
                              cursor: 'pointer'
                            }}
                            title="ส่งกลับเข้าคิวใหม่"
                          >
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style={{ animation: isRetrying ? 'spin 1s linear infinite' : 'none' }}>
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>{isRetrying ? 'กำลังส่ง...' : 'ลองใหม่'}</span>
                          </button>

                          <button
                            onClick={() => handleDelete(job.id)}
                            disabled={isDeleting}
                            style={{
                              display: 'inline-flex',
                              alignItems: 'center',
                              justifyContent: 'center',
                              background: '#fee2e2',
                              color: '#b91c1c',
                              border: '1px solid #fecaca',
                              padding: '0.3rem 0.55rem',
                              borderRadius: 6,
                              fontSize: '0.75rem',
                              fontWeight: 600,
                              cursor: 'pointer'
                            }}
                            title="ลบทิ้ง"
                          >
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ) : (
          <div style={{ padding: '3.5rem 1.5rem', textAlign: 'center' }}>
            <div style={{
              width: 56,
              height: 56,
              borderRadius: '50%',
              background: '#ecfdf5',
              border: '1.5px solid #a7f3d0',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              margin: '0 auto 1rem auto',
              color: '#10b981'
            }}>
              <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h4 style={{ margin: '0 0 0.35rem 0', fontSize: '1.1rem', fontWeight: 700, color: '#0f172a' }}>
              ไม่มีรายการงานที่ล้มเหลว (Queue Operational)
            </h4>
            <p style={{ margin: 0, color: '#64748b', fontSize: '0.875rem' }}>
              คิวงานทั้งหมด (InsightFace AI, LINE Notification, Exports) ทำงานผ่านได้อย่างสมบูรณ์
            </p>
          </div>
        )}

        {/* Pagination */}
        {data && data.last_page > 1 && (
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            padding: '0.85rem 1.25rem',
            borderTop: '1px solid #e2e8f0',
            background: '#fafbfc'
          }}>
            <span style={{ fontSize: '0.8rem', color: '#64748b' }}>
              หน้า {data.current_page} จากทั้งหมด {data.last_page}
            </span>
            <div style={{ display: 'flex', gap: '0.4rem' }}>
              <button
                onClick={() => { setPage(p => Math.max(1, p - 1)); fetchFailedJobs(page - 1); }}
                disabled={page <= 1}
                style={{
                  padding: '0.3rem 0.75rem',
                  border: '1px solid #cbd5e1',
                  borderRadius: 6,
                  background: '#fff',
                  fontSize: '0.8rem',
                  cursor: page <= 1 ? 'not-allowed' : 'pointer',
                  opacity: page <= 1 ? 0.5 : 1
                }}
              >
                ย้อนกลับ
              </button>
              <button
                onClick={() => { setPage(p => Math.min(data.last_page, p + 1)); fetchFailedJobs(page + 1); }}
                disabled={page >= data.last_page}
                style={{
                  padding: '0.3rem 0.75rem',
                  border: '1px solid #cbd5e1',
                  borderRadius: 6,
                  background: '#fff',
                  fontSize: '0.8rem',
                  cursor: page >= data.last_page ? 'not-allowed' : 'pointer',
                  opacity: page >= data.last_page ? 0.5 : 1
                }}
              >
                ถัดไป
              </button>
            </div>
          </div>
        )}
      </div>

      {/* ── 4. Job Details Modal ──────────────────────────────────────── */}
      {selectedJob && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(15, 23, 42, 0.6)',
          backdropFilter: 'blur(3px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 9999,
          padding: '1.5rem'
        }}>
          <div style={{
            background: '#fff',
            borderRadius: 16,
            width: '100%',
            maxWidth: 780,
            maxHeight: '90vh',
            display: 'flex',
            flexDirection: 'column',
            boxShadow: '0 25px 50px -12px rgba(0,0,0,0.25)',
            overflow: 'hidden'
          }}>
            {/* Modal Header */}
            <div style={{
              padding: '1.25rem 1.5rem',
              borderBottom: '1px solid #e2e8f0',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center'
            }}>
              <div>
                <h3 style={{ margin: 0, fontSize: '1.15rem', fontWeight: 800, color: '#0f172a' }}>
                  รายละเอียดงานที่ล้มเหลว #{selectedJob.id}
                </h3>
                <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '0.2rem' }}>
                  UUID: {selectedJob.uuid} · Queue: queue:{selectedJob.queue}
                </div>
              </div>
              <button
                onClick={() => setSelectedJob(null)}
                style={{
                  background: 'none',
                  border: 'none',
                  color: '#94a3b8',
                  cursor: 'pointer',
                  padding: '0.35rem',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center'
                }}
                title="ปิด"
              >
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Modal Body */}
            <div style={{ padding: '1.5rem', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              <div>
                <label style={{ fontSize: '0.75rem', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase' }}>Exception Stack Trace</label>
                <pre style={{
                  background: '#0f172a',
                  color: '#f87171',
                  padding: '1rem',
                  borderRadius: 10,
                  fontSize: '0.775rem',
                  fontFamily: 'monospace',
                  overflowX: 'auto',
                  maxHeight: 240,
                  marginTop: '0.35rem',
                  whiteSpace: 'pre-wrap',
                  wordBreak: 'break-word'
                }}>
                  {selectedJob.exception}
                </pre>
              </div>

              <div>
                <label style={{ fontSize: '0.75rem', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase' }}>Job Payload</label>
                <pre style={{
                  background: '#f8fafc',
                  color: '#334155',
                  border: '1px solid #e2e8f0',
                  padding: '1rem',
                  borderRadius: 10,
                  fontSize: '0.75rem',
                  fontFamily: 'monospace',
                  overflowX: 'auto',
                  maxHeight: 200,
                  marginTop: '0.35rem',
                  whiteSpace: 'pre-wrap',
                  wordBreak: 'break-word'
                }}>
                  {typeof selectedJob.payload === 'object' ? JSON.stringify(selectedJob.payload, null, 2) : selectedJob.payload}
                </pre>
              </div>
            </div>

            {/* Modal Footer */}
            <div style={{
              padding: '1rem 1.5rem',
              borderTop: '1px solid #e2e8f0',
              background: '#fafbfc',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center'
            }}>
              <button
                onClick={() => handleDelete(selectedJob.id)}
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '0.4rem',
                  background: '#fee2e2',
                  color: '#b91c1c',
                  border: '1px solid #fecaca',
                  padding: '0.45rem 0.9rem',
                  borderRadius: 8,
                  fontSize: '0.825rem',
                  fontWeight: 600,
                  cursor: 'pointer'
                }}
              >
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <span>ลบงานนี้</span>
              </button>

              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <button
                  onClick={() => setSelectedJob(null)}
                  style={{
                    background: '#fff',
                    border: '1px solid #cbd5e1',
                    color: '#475569',
                    padding: '0.45rem 0.9rem',
                    borderRadius: 8,
                    fontSize: '0.825rem',
                    cursor: 'pointer'
                  }}
                >
                  ปิด
                </button>
                <button
                  onClick={() => handleRetry(selectedJob.id)}
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '0.4rem',
                    background: '#ea580c',
                    color: '#fff',
                    border: 'none',
                    padding: '0.45rem 1rem',
                    borderRadius: 8,
                    fontSize: '0.825rem',
                    fontWeight: 700,
                    cursor: 'pointer'
                  }}
                >
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  <span>ส่งกลับเข้าคิวลองใหม่</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
