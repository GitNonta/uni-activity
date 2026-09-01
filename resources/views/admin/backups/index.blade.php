@extends('layouts.admin')
@section('title', 'สำรองและกู้คืนข้อมูลระบบ (Backup & Recovery)')

@section('styles')
<style>
/* ═════════════════════════════════════════════════════════════
   BACKUP & RECOVERY SUITE — Executive Modern Theme Styles
   ═════════════════════════════════════════════════════════════ */

.backup-page {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* ─── Hero Header ─── */
.backup-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, rgba(234, 88, 12, 0.08) 0%, rgba(245, 158, 11, 0.05) 50%, rgba(99, 102, 241, 0.04) 100%);
    border: 1px solid rgba(234, 88, 12, 0.18);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(12px);
}
html[data-theme="dark"] .backup-hero {
    background: linear-gradient(135deg, rgba(234, 88, 12, 0.14) 0%, rgba(24, 24, 27, 0.8) 60%, rgba(99, 102, 241, 0.1) 100%);
    border-color: rgba(234, 88, 12, 0.25);
}

.backup-hero-left {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.backup-hero-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, #ea580c 0%, #f59e0b 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(234, 88, 12, 0.45);
    flex-shrink: 0;
}
.backup-hero-icon svg {
    width: 28px;
    height: 28px;
}

.backup-hero-titles h1 {
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    color: var(--adm-page-title, #0f172a);
}

.backup-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
}
html[data-theme="dark"] .backup-status-pill {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}
.backup-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
    animation: pulse-dot 2s infinite ease-in-out;
}
@keyframes pulse-dot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.6; }
}

.backup-hero-desc {
    font-size: 0.85rem;
    color: var(--adm-section-label, #64748b);
    margin-top: 0.35rem;
}

.backup-hero-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.btn-hero-primary {
    background: linear-gradient(135deg, #ea580c 0%, #d97706 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.875rem;
    padding: 0.65rem 1.25rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(234, 88, 12, 0.3);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-hero-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(234, 88, 12, 0.45);
    color: #ffffff;
}

.btn-hero-clean {
    background: var(--adm-btn-bg, #ffffff);
    color: var(--adm-btn-text, #475569);
    border: 1px solid var(--adm-btn-border, #e2e8f0);
    font-weight: 600;
    font-size: 0.85rem;
    padding: 0.65rem 1.15rem;
    border-radius: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}
.btn-hero-clean:hover {
    background: rgba(239, 68, 68, 0.08);
    color: #dc2626;
    border-color: rgba(239, 68, 68, 0.3);
}

.kbd-shortcut {
    font-family: inherit;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.35);
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* ─── Metric & Health Cards ─── */
.backup-grid-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.metric-card {
    background: var(--adm-topbar-bg, #ffffff);
    border: 1px solid var(--adm-topbar-border, #e2e8f0);
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
.metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
}

.metric-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}
.metric-card.card-blue::after { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.metric-card.card-green::after { background: linear-gradient(90deg, #10b981, #34d399); }
.metric-card.card-purple::after { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.metric-card.card-amber::after { background: linear-gradient(90deg, #f59e0b, #fbbf24); }

.metric-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}
.metric-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--adm-section-label, #94a3b8);
}
.metric-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.metric-icon-wrap svg {
    width: 20px;
    height: 20px;
}
.card-blue .metric-icon-wrap { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.card-green .metric-icon-wrap { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.card-purple .metric-icon-wrap { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.card-amber .metric-icon-wrap { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

.metric-body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.metric-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--adm-page-title, #0f172a);
    line-height: 1.2;
}
.metric-sub {
    font-size: 0.75rem;
    color: var(--adm-section-label, #64748b);
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Disk progress */
.disk-progress-track {
    height: 7px;
    border-radius: 999px;
    background: var(--adm-btn-bg, #f1f5f9);
    overflow: hidden;
    margin: 0.5rem 0 0.35rem;
}
.disk-progress-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.6s ease;
}

/* ─── PDPA & Biometric Protection Callout ─── */
.security-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: var(--adm-topbar-bg, #ffffff);
    border: 1px solid var(--adm-topbar-border, #e2e8f0);
    border-left: 4px solid #ea580c;
    border-radius: 14px;
}
.security-banner-info {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    flex: 1;
    min-width: 280px;
}
.security-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(234, 88, 12, 0.1);
    color: #ea580c;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.security-icon svg { width: 20px; height: 20px; }
.security-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--adm-page-title, #0f172a);
    margin-bottom: 2px;
}
.security-text {
    font-size: 0.78rem;
    color: var(--adm-section-label, #64748b);
    line-height: 1.4;
}
.security-link {
    font-size: 0.8rem;
    font-weight: 600;
    color: #ea580c;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 8px;
    background: rgba(234, 88, 12, 0.08);
    transition: all 0.15s;
    white-space: nowrap;
}
.security-link:hover {
    background: #ea580c;
    color: #ffffff;
}

/* ─── Archive Explorer & Table Card ─── */
.backup-table-card {
    background: var(--adm-topbar-bg, #ffffff);
    border: 1px solid var(--adm-topbar-border, #e2e8f0);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

.table-toolbar {
    padding: 1.15rem 1.35rem;
    border-bottom: 1px solid var(--adm-topbar-border, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.table-title {
    font-size: 1rem;
    font-weight: 750;
    color: var(--adm-page-title, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}
.table-badge-counter {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--adm-btn-bg, #f1f5f9);
    color: var(--adm-section-label, #64748b);
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Search input */
.backup-search-wrap {
    position: relative;
    min-width: 220px;
}
.backup-search-wrap svg {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    color: #94a3b8;
    pointer-events: none;
}
.backup-search-input {
    width: 100%;
    font-size: 0.8rem;
    padding: 0.45rem 0.75rem 0.45rem 2rem;
    border-radius: 10px;
    border: 1px solid var(--adm-topbar-border, #cbd5e1);
    background: var(--adm-search-bg, #f8fafc);
    color: var(--adm-page-title, #0f172a);
    outline: none;
    transition: all 0.2s;
}
.backup-search-input:focus {
    border-color: #ea580c;
    background: var(--adm-topbar-bg, #ffffff);
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
}

/* Filter pills */
.filter-pills-group {
    display: inline-flex;
    background: var(--adm-btn-bg, #f1f5f9);
    padding: 3px;
    border-radius: 11px;
    gap: 2px;
}
.filter-pill-btn {
    border: none;
    background: transparent;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 5px 11px;
    border-radius: 8px;
    color: var(--adm-section-label, #64748b);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.18s ease;
}
.filter-pill-btn:hover {
    color: var(--adm-page-title, #0f172a);
}
.filter-pill-btn.active {
    background: var(--adm-topbar-bg, #ffffff);
    color: #ea580c;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}
html[data-theme="dark"] .filter-pill-btn.active {
    background: #27272a;
    color: #fb923c;
}

/* Table styling */
.backup-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.83rem;
}
.backup-table th {
    background: var(--adm-btn-bg, #f8fafc);
    color: var(--adm-section-label, #94a3b8);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1.15rem;
    border-bottom: 1px solid var(--adm-topbar-border, #e2e8f0);
    white-space: nowrap;
}
.backup-table td {
    padding: 0.875rem 1.15rem;
    border-bottom: 1px solid var(--adm-topbar-border, #f1f5f9);
    color: var(--adm-page-title, #1e293b);
    vertical-align: middle;
}
.backup-tr {
    transition: background 0.15s ease;
}
.backup-tr:hover {
    background: rgba(234, 88, 12, 0.03);
}
html[data-theme="dark"] .backup-tr:hover {
    background: rgba(255, 255, 255, 0.02);
}

/* File info cell */
.file-meta-wrap {
    display: flex;
    align-items: center;
    gap: 0.875rem;
}
.file-type-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.file-type-avatar svg { width: 20px; height: 20px; }

.avatar-full { background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.25)); color: #8b5cf6; }
.avatar-db { background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.25)); color: #2563eb; }
.avatar-biometrics { background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.25)); color: #059669; }
.avatar-files { background: linear-gradient(135deg, rgba(100, 116, 139, 0.15), rgba(71, 85, 105, 0.25)); color: #475569; }

.file-name-text {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--adm-page-title, #0f172a);
    margin: 0;
    max-width: 320px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-sub-text {
    font-size: 0.72rem;
    color: var(--adm-section-label, #94a3b8);
    margin: 2px 0 0;
}

/* Badges */
.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 7px;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.type-badge.badge-full { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.type-badge.badge-db { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.type-badge.badge-biometrics { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.type-badge.badge-files { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

html[data-theme="dark"] .type-badge.badge-full { background: rgba(139, 92, 246, 0.2); color: #c084fc; border-color: rgba(139, 92, 246, 0.35); }
html[data-theme="dark"] .type-badge.badge-db { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border-color: rgba(59, 130, 246, 0.35); }
html[data-theme="dark"] .type-badge.badge-biometrics { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border-color: rgba(16, 185, 129, 0.35); }
html[data-theme="dark"] .type-badge.badge-files { background: rgba(100, 116, 139, 0.2); color: #cbd5e1; border-color: rgba(100, 116, 139, 0.35); }

/* Hash button */
.btn-hash-copy {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
    font-size: 0.72rem;
    padding: 4px 9px;
    border-radius: 7px;
    background: var(--adm-btn-bg, #f8fafc);
    border: 1px solid var(--adm-topbar-border, #e2e8f0);
    color: var(--adm-section-label, #64748b);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}
.btn-hash-copy:hover {
    border-color: #ea580c;
    color: #ea580c;
    background: rgba(234, 88, 12, 0.05);
}

/* Action buttons */
.action-btn-group {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 6px;
}
.btn-action-download {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(59, 130, 246, 0.08);
    color: #2563eb;
    border: 1px solid rgba(59, 130, 246, 0.2);
    text-decoration: none;
    transition: all 0.15s;
}
.btn-action-download:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}
.btn-action-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 29px;
    height: 29px;
    border-radius: 8px;
    background: rgba(239, 68, 68, 0.08);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.2);
    cursor: pointer;
    transition: all 0.15s;
}
.btn-action-delete:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}

/* ─── Schedule & Policy Side Panel ─── */
.backup-bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.policy-card {
    background: var(--adm-topbar-bg, #ffffff);
    border: 1px solid var(--adm-topbar-border, #e2e8f0);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
}
.policy-title {
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--adm-page-title, #0f172a);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 1rem;
}
.policy-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.policy-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    background: var(--adm-btn-bg, #f8fafc);
    border: 1px solid var(--adm-topbar-border, #f1f5f9);
    font-size: 0.8rem;
}
.policy-item-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--adm-page-title, #334155);
}
.policy-item-val {
    font-weight: 700;
    color: #ea580c;
    font-size: 0.8rem;
}

/* ─── Modal ─── */
.backup-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px);
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    opacity: 0;
    transition: opacity 0.25s ease;
}
.backup-modal-backdrop.active {
    display: flex;
    opacity: 1;
}

.backup-modal-box {
    width: 100%;
    max-width: 520px;
    background: var(--adm-topbar-bg, #ffffff);
    border: 1px solid var(--adm-topbar-border, #e2e8f0);
    border-radius: 22px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    transform: scale(0.95);
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.backup-modal-backdrop.active .backup-modal-box {
    transform: scale(1);
}

.modal-header-custom {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--adm-topbar-border, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-title-custom {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--adm-page-title, #0f172a);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.modal-close-btn {
    background: transparent;
    border: none;
    color: var(--adm-section-label, #94a3b8);
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.modal-close-btn:hover {
    background: var(--adm-btn-bg, #f1f5f9);
    color: var(--adm-page-title, #0f172a);
}

.modal-body-custom {
    padding: 1.5rem;
}

.backup-option-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 0.95rem 1.15rem;
    border-radius: 14px;
    border: 2px solid var(--adm-topbar-border, #e2e8f0);
    background: var(--adm-topbar-bg, #ffffff);
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: 0.75rem;
    position: relative;
}
.backup-option-card:hover {
    border-color: #ea580c;
    background: rgba(234, 88, 12, 0.02);
}
.backup-option-card.selected {
    border-color: #ea580c;
    background: rgba(234, 88, 12, 0.05);
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.12);
}
.backup-option-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}
.backup-option-icon svg { width: 18px; height: 18px; }

.modal-footer-custom {
    padding: 1rem 1.5rem;
    background: var(--adm-btn-bg, #f8fafc);
    border-top: 1px solid var(--adm-topbar-border, #f1f5f9);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Spinner */
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #ffffff;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ─── Responsive ─── */
@media (max-width: 1024px) {
    .backup-grid-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .backup-bottom-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 640px) {
    .backup-grid-stats {
        grid-template-columns: 1fr;
    }
    .backup-hero {
        padding: 1.25rem;
    }
    .backup-hero-left {
        flex-direction: column;
        align-items: flex-start;
    }
    .table-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .toolbar-right {
        flex-direction: column;
        align-items: stretch;
    }
    .backup-search-wrap {
        width: 100%;
    }
}
</style>
@endsection

@section('content')
<div class="backup-page">

    {{-- ══════════════════════════════════════════════════
         1. HERO HEADER WITH EXECUTIVE CONTROLS
         ══════════════════════════════════════════════════ --}}
    <div class="backup-hero">
        <div class="backup-hero-left">
            <div class="backup-hero-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
            </div>
            <div class="backup-hero-titles">
                <h1>
                    สำรองและกู้คืนข้อมูลระบบ
                    <span class="backup-status-pill">
                        <span class="backup-status-dot"></span>
                        ระบบพร้อมทำงาน
                    </span>
                </h1>
                <p class="backup-hero-desc">
                    ระบบสร้างชุดข้อมูลสำรองอัตโนมัติ — ครอบคลุมฐานข้อมูล, สื่อบันทึก, เวกเตอร์ชีวมิติ (InsightFace 512D) และบันทึกเวลา
                </p>
            </div>
        </div>

        <div class="backup-hero-actions">
            <form action="{{ route('admin.backups.clean') }}" method="POST" onsubmit="return confirm('ยืนยันล้างไฟล์สำรองที่หมดอายุตามเงื่อนไข (เก่าเกิน {{ $scheduleInfo['retention_days'] }} วัน)?');">
                @csrf
                <button type="submit" class="btn-hero-clean" title="ล้างไฟล์สำรองที่เก่าเกินระยะเวลา Retention">
                    <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    ล้างไฟล์เก่า
                </button>
            </form>

            <button onclick="openBackupModal()" class="btn-hero-primary" id="btnOpenBackupModal">
                <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                สำรองข้อมูลทันที
                <kbd class="kbd-shortcut">Ctrl+B</kbd>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         2. SYSTEM HEALTH & STORAGE METRICS (4 CARDS)
         ══════════════════════════════════════════════════ --}}
    <div class="backup-grid-stats">
        {{-- Card 1: Total Archives --}}
        <div class="metric-card card-blue">
            <div class="metric-header">
                <span class="metric-label">ขนาดชุดสำรองทั้งหมด</span>
                <div class="metric-icon-wrap">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                </div>
            </div>
            <div class="metric-body">
                <div class="metric-value">{{ $formattedTotalSize }}</div>
                <div class="metric-sub">
                    <svg style="width:13px; height:13px; color:#3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    จัดเก็บทั้งหมด {{ count($backups) }} ไฟล์ในระบบ
                </div>
            </div>
        </div>

        {{-- Card 2: Latest Backup Status --}}
        <div class="metric-card card-green">
            <div class="metric-header">
                <span class="metric-label">การสำรองข้อมูลล่าสุด</span>
                <div class="metric-icon-wrap">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="metric-body">
                @if($latestBackup)
                    <div class="metric-value" style="font-size:1.15rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $latestBackup['created_at'] }}">
                        {{ $latestBackup['created_at'] }}
                    </div>
                    <div class="metric-sub" style="color:#059669; font-weight:600;">
                        <span class="type-badge badge-{{ $latestBackup['type'] }}" style="font-size:0.6rem; padding:1px 6px;">{{ strtoupper($latestBackup['type']) }}</span>
                        {{ $latestBackup['formatted_size'] }} · ตรวจสอบสมบูรณ์
                    </div>
                @else
                    <div class="metric-value text-muted" style="font-size:1.15rem;">ยังไม่มีไฟล์</div>
                    <div class="metric-sub">พร้อมรับการสร้างสำรองชุดแรก</div>
                @endif
            </div>
        </div>

        {{-- Card 3: Schedules --}}
        <div class="metric-card card-purple">
            <div class="metric-header">
                <span class="metric-label">ตารางอัตโนมัติ (Cron)</span>
                <div class="metric-icon-wrap">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="metric-body">
                <div class="metric-value" style="font-size:1.1rem;">รายวัน 01:00 น.</div>
                <div class="metric-sub">
                    <span>Full: {{ $scheduleInfo['weekly_full'] }}</span>
                </div>
            </div>
        </div>

        {{-- Card 4: Disk Storage Gauge --}}
        <div class="metric-card card-amber">
            <div class="metric-header">
                <span class="metric-label">พื้นที่จัดเก็บดิสก์เซิร์ฟเวอร์</span>
                <div class="metric-icon-wrap">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                </div>
            </div>
            <div class="metric-body">
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <span class="metric-value" style="font-size:1.15rem;">{{ $formattedDiskUsed }}</span>
                    <span style="font-size:0.75rem; color:var(--adm-section-label,#64748b); font-weight:600;">/ {{ $formattedDiskTotal }}</span>
                </div>
                <div class="disk-progress-track">
                    <div class="disk-progress-bar" style="width: {{ min(100, max(2, $diskPercent)) }}%; background: linear-gradient(90deg, {{ $diskPercent > 90 ? '#ef4444, #dc2626' : ($diskPercent > 70 ? '#f59e0b, #d97706' : '#10b981, #059669') }});"></div>
                </div>
                <div class="metric-sub" style="justify-content:space-between;">
                    <span>{{ $diskPercent }}% ใช้งาน</span>
                    <span style="font-weight:600;">ว่าง {{ $formattedDiskFree }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         3. SECURITY, ENCRYPTION & PDPA COMPLIANCE BANNER
         ══════════════════════════════════════════════════ --}}
    <div class="security-banner">
        <div class="security-banner-info">
            <div class="security-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <div class="security-title">การคุ้มครองข้อมูลส่วนบุคคลและชีวมิติ (PDPA & Biometric Protection Standard)</div>
                <div class="security-text">
                    เวกเตอร์ใบหน้า (512D InsightFace / 128D) มีการเข้ารหัสระดับคลังข้อมูล และทุกการดาวน์โหลดหรือลบไฟล์จะถูกบันทึกประวัติแบบ Immutable ใน Audit Trail อย่างโปร่งใส
                </div>
            </div>
        </div>
        <a href="{{ route('admin.audit-logs.index') }}" class="security-link">
            ตรวจสอบ Audit Logs
            <svg style="width:14px; height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
        </a>
    </div>

    {{-- ══════════════════════════════════════════════════
         4. ARCHIVE EXPLORER & BACKUP LIST TABLE
         ══════════════════════════════════════════════════ --}}
    <div class="backup-table-card">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <h2 class="table-title">
                    <svg style="width:20px; height:20px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                    รายการไฟล์สำรองข้อมูล
                </h2>
                <span class="table-badge-counter" id="backupCountBadge">{{ count($backups) }} ไฟล์</span>
            </div>

            <div class="toolbar-right">
                {{-- Live Client-side Search --}}
                <div class="backup-search-wrap">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="backupSearchInput" class="backup-search-input" placeholder="ค้นหาชื่อไฟล์ หรือ Hash..." oninput="handleSearch(this.value)">
                </div>

                {{-- Filter Pills --}}
                <div class="filter-pills-group" id="filterPills">
                    <button type="button" class="filter-pill-btn active" data-filter="all" onclick="filterBackups('all', this)">
                        ทั้งหมด
                    </button>
                    <button type="button" class="filter-pill-btn" data-filter="full" onclick="filterBackups('full', this)">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#8b5cf6;"></span>
                        FULL
                    </button>
                    <button type="button" class="filter-pill-btn" data-filter="db" onclick="filterBackups('db', this)">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#3b82f6;"></span>
                        DB
                    </button>
                    <button type="button" class="filter-pill-btn" data-filter="biometrics" onclick="filterBackups('biometrics', this)">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#10b981;"></span>
                        Bio
                    </button>
                    <button type="button" class="filter-pill-btn" data-filter="files" onclick="filterBackups('files', this)">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#64748b;"></span>
                        Files
                    </button>
                </div>
            </div>
        </div>

        @if(empty($backups))
            {{-- Empty State --}}
            <div style="padding: 4rem 1.5rem; text-align: center;">
                <div style="width: 72px; height: 72px; border-radius: 20px; background: rgba(234, 88, 12, 0.08); color: #ea580c; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg style="width: 36px; height: 36px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h3 style="font-weight: 800; font-size: 1.15rem; color: var(--adm-page-title, #0f172a); margin: 0 0 0.5rem;">ยังไม่มีไฟล์สำรองข้อมูลในระบบ</h3>
                <p style="font-size: 0.85rem; color: var(--adm-section-label, #64748b); max-width: 420px; margin: 0 auto 1.5rem;">
                    คุณสามารถกดปุ่มสำรองข้อมูลทันที หรือรอตารางเวลาอัตโนมัติของเซิร์ฟเวอร์ (01:00 น. ทุกวัน) เพื่อสร้างไฟล์สำรองชุดแรก
                </p>
                <button onclick="openBackupModal()" class="btn-hero-primary" style="display:inline-flex; align-items:center; gap:6px;">
                    <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    สร้างไฟล์สำรองชุดแรก
                </button>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="backup-table" id="backupMainTable">
                    <thead>
                        <tr>
                            <th style="width: 320px;">ชื่อไฟล์และรายละเอียด</th>
                            <th style="text-align: center;">ประเภท</th>
                            <th style="text-align: center;">ขนาดไฟล์</th>
                            <th style="text-align: center;">วันที่สร้าง</th>
                            <th style="text-align: center;">อายุไฟล์</th>
                            <th>SHA-256 Checksum</th>
                            <th style="text-align: right; padding-right: 1.5rem;">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody id="backupTableBody">
                        @foreach($backups as $b)
                            @php
                                $ts = strtotime($b['created_at']);
                                $diffHours = max(0, (time() - $ts) / 3600);
                                if ($diffHours < 24) {
                                    $ageColor = '#15803d'; $ageBg = '#dcfce7'; $ageText = (round($diffHours) ?: 1) . ' ชม. ที่แล้ว';
                                } elseif ($diffHours < 168) {
                                    $ageColor = '#1d4ed8'; $ageBg = '#dbeafe'; $ageText = round($diffHours / 24) . ' วันที่แล้ว';
                                } else {
                                    $ageColor = '#b45309'; $ageBg = '#fef3c7'; $ageText = round($diffHours / 168) . ' สัปดาห์ที่แล้ว';
                                }
                            @endphp
                            <tr class="backup-tr" data-type="{{ $b['type'] }}" data-filename="{{ strtolower($b['filename']) }}" data-hash="{{ strtolower((string)$b['sha256']) }}">
                                <td>
                                    <div class="file-meta-wrap">
                                        <div class="file-type-avatar avatar-{{ $b['type'] }}">
                                            @if($b['type'] === 'full')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                            @elseif($b['type'] === 'db')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                                            @elseif($b['type'] === 'biometrics')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            @else
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="file-name-text" title="{{ $b['filename'] }}">{{ $b['filename'] }}</div>
                                            <div class="file-sub-text">
                                                ZIP Archive · เข้ารหัสความปลอดภัย
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="type-badge badge-{{ $b['type'] }}">
                                        {{ strtoupper($b['type']) }}
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 700;">
                                    {{ $b['formatted_size'] }}
                                </td>
                                <td style="text-align: center; font-size: 0.8rem; color: var(--adm-section-label, #64748b);">
                                    {{ $b['created_at'] }}
                                </td>
                                <td style="text-align: center;">
                                    <span style="font-size: 0.7rem; font-weight: 600; padding: 3px 8px; border-radius: 999px; background: {{ $ageBg }}; color: {{ $ageColor }};">
                                        {{ $ageText }}
                                    </span>
                                </td>
                                <td>
                                    <button type="button" onclick="copyHash('{{ $b['sha256'] }}', this)" class="btn-hash-copy" title="คลิกเพื่อคัดลอก SHA-256 Checksum">
                                        <span>{{ substr((string)$b['sha256'], 0, 12) }}…</span>
                                        <svg style="width: 12px; height: 12px; opacity: 0.6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </td>
                                <td style="text-align: right; padding-right: 1.5rem;">
                                    <div class="action-btn-group">
                                        <a href="{{ route('admin.backups.download', $b['filename']) }}" class="btn-action-download" title="ดาวน์โหลดไฟล์สำรอง">
                                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            ดาวน์โหลด
                                        </a>
                                        <form action="{{ route('admin.backups.destroy', $b['filename']) }}" method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบไฟล์สำรอง {{ $b['filename'] }} ออกจากระบบอย่างถาวร?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-action-delete" title="ลบไฟล์">
                                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════
         5. AUTOMATION SCHEDULE & RETENTION RULES OVERVIEW
         ══════════════════════════════════════════════════ --}}
    <div class="backup-bottom-grid">
        <div class="policy-card">
            <h3 class="policy-title">
                <svg style="width:18px; height:18px; color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                กำหนดการทำงานอัตโนมัติ (Automated Schedule)
            </h3>
            <div class="policy-list">
                <div class="policy-item">
                    <div class="policy-item-label">
                        <span style="width:8px; height:8px; border-radius:50%; background:#3b82f6;"></span>
                        สำรองฐานข้อมูลประจำวัน (Daily Database)
                    </div>
                    <span class="policy-item-val">{{ $scheduleInfo['daily_db'] }}</span>
                </div>
                <div class="policy-item">
                    <div class="policy-item-label">
                        <span style="width:8px; height:8px; border-radius:50%; background:#8b5cf6;"></span>
                        สำรองระบบเต็มรูปแบบ (Weekly Full Backup)
                    </div>
                    <span class="policy-item-val">{{ $scheduleInfo['weekly_full'] }}</span>
                </div>
                <div class="policy-item">
                    <div class="policy-item-label">
                        <span style="width:8px; height:8px; border-radius:50%; background:#ef4444;"></span>
                        ล้างไฟล์หมดอายุ (Retention Cleanup)
                    </div>
                    <span class="policy-item-val">{{ $scheduleInfo['daily_clean'] }}</span>
                </div>
            </div>
        </div>

        <div class="policy-card">
            <h3 class="policy-title">
                <svg style="width:18px; height:18px; color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                นโยบายการเก็บรักษาข้อมูล (Retention & Security)
            </h3>
            <div class="policy-list">
                <div class="policy-item">
                    <div class="policy-item-label">ระยะเวลาจัดเก็บสูงสุด (Keep Window)</div>
                    <span class="policy-item-val">{{ $scheduleInfo['retention_days'] }} วัน</span>
                </div>
                <div class="policy-item">
                    <div class="policy-item-label">จำนวนไฟล์สำรองขั้นต่ำที่ต้องคงไว้</div>
                    <span class="policy-item-val">{{ $scheduleInfo['keep_minimum'] }} ชุดล่าสุด</span>
                </div>
                <div class="policy-item">
                    <div class="policy-item-label">การตรวจสอบความถูกต้อง (Integrity Check)</div>
                    <span class="policy-item-val" style="color:#10b981;">SHA-256 Checksum</span>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════
     6. ENHANCED BACKUP CREATION MODAL
     ══════════════════════════════════════════════════ --}}
<div id="manualBackupModal" class="backup-modal-backdrop">
    <div class="backup-modal-box">
        <div class="modal-header-custom">
            <h3 class="modal-title-custom">
                <span style="width:32px; height:32px; border-radius:10px; background:linear-gradient(135deg, #ea580c, #f59e0b); display:inline-flex; align-items:center; justify-content:center; color:#fff;">
                    <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                </span>
                สร้างการสำรองข้อมูลระบบ
            </h3>
            <button type="button" onclick="closeBackupModal()" class="modal-close-btn">
                <svg style="width:20px; height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="{{ route('admin.backups.store') }}" method="POST" id="backupSubmitForm" onsubmit="handleFormSubmit(this)">
            @csrf
            <div class="modal-body-custom">
                <p style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--adm-section-label,#94a3b8); margin:0 0 0.85rem;">
                    เลือกขอบเขตข้อมูลที่ต้องการสำรอง:
                </p>

                {{-- Option 1: Full --}}
                <label class="backup-option-card selected" onclick="selectOption(this)">
                    <input type="radio" name="type" value="full" checked style="accent-color:#ea580c; margin-top:3px;">
                    <div class="backup-option-icon avatar-full">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:750; font-size:0.875rem; color:var(--adm-page-title,#0f172a); display:flex; align-items:center; justify-content:space-between;">
                            Full System Backup
                            <span style="font-size:0.65rem; background:rgba(139,92,246,0.15); color:#8b5cf6; padding:2px 6px; border-radius:6px; font-weight:700;">แนะนำ</span>
                        </div>
                        <div style="font-size:0.75rem; color:var(--adm-section-label,#64748b); margin-top:2px;">
                            สำรองฐานข้อมูล SQL + ไฟล์สื่อที่อัปโหลด + เวกเตอร์ชีวมิติใบหน้า ครบทุกตาราง
                        </div>
                    </div>
                </label>

                {{-- Option 2: DB --}}
                <label class="backup-option-card" onclick="selectOption(this)">
                    <input type="radio" name="type" value="db" style="accent-color:#ea580c; margin-top:3px;">
                    <div class="backup-option-icon avatar-db">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:750; font-size:0.875rem; color:var(--adm-page-title,#0f172a);">
                            Database Only
                        </div>
                        <div style="font-size:0.75rem; color:var(--adm-section-label,#64748b); margin-top:2px;">
                            SQL Dump ข้อมูลผู้ใช้, กิจกรรม, ทรานแซกชัน, และประวัติการเข้าร่วม
                        </div>
                    </div>
                </label>

                {{-- Option 3: Biometrics --}}
                <label class="backup-option-card" onclick="selectOption(this)">
                    <input type="radio" name="type" value="biometrics" style="accent-color:#ea580c; margin-top:3px;">
                    <div class="backup-option-icon avatar-biometrics">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:750; font-size:0.875rem; color:var(--adm-page-title,#0f172a);">
                            Biometrics & Attendance
                        </div>
                        <div style="font-size:0.75rem; color:var(--adm-section-label,#64748b); margin-top:2px;">
                            เวกเตอร์ใบหน้า 512D InsightFace และประวัติเช็คอิน GPS/Face Scan
                        </div>
                    </div>
                </label>

                {{-- Option 4: Files --}}
                <label class="backup-option-card" onclick="selectOption(this)">
                    <input type="radio" name="type" value="files" style="accent-color:#ea580c; margin-top:3px;">
                    <div class="backup-option-icon avatar-files">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:750; font-size:0.875rem; color:var(--adm-page-title,#0f172a);">
                            Storage Media Files
                        </div>
                        <div style="font-size:0.75rem; color:var(--adm-section-label,#64748b); margin-top:2px;">
                            ไฟล์รูปภาพกิจกรรม, เกียรติบัตร, และเอกสารแนบใน Storage
                        </div>
                    </div>
                </label>
            </div>

            <div class="modal-footer-custom">
                <button type="button" onclick="closeBackupModal()" class="btn-hero-clean" style="padding:0.5rem 1rem;">
                    ยกเลิก
                </button>
                <button type="submit" id="btnSubmitBackup" class="btn-hero-primary" style="padding:0.5rem 1.25rem;">
                    <span id="btnSubmitText" style="display:inline-flex; align-items:center; gap:6px;">
                        <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        เริ่มสำรองข้อมูล
                    </span>
                    <span id="btnSubmitSpinner" style="display:none; align-items:center; gap:6px;">
                        <span class="spinner"></span>
                        กำลังสร้างไฟล์สำรอง...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Toast Container --}}
<div id="backupToastContainer" style="position:fixed; bottom:24px; right:24px; z-index:99999; display:flex; flex-direction:column; gap:8px;"></div>
@endsection

@section('scripts')
<script>
(function() {
    // Flash Messages
    @if(session('success'))
        showToast("{{ addslashes(session('success')) }}", 'success');
    @endif
    @if(session('error'))
        showToast("{{ addslashes(session('error')) }}", 'error');
    @endif

    var currentFilter = 'all';
    var currentSearch = '';

    window.selectOption = function(labelEl) {
        document.querySelectorAll('.backup-option-card').forEach(function(card) {
            card.classList.remove('selected');
        });
        labelEl.classList.add('selected');
        var radio = labelEl.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
    };

    window.filterBackups = function(type, btn) {
        currentFilter = type;
        document.querySelectorAll('.filter-pill-btn').forEach(function(el) {
            el.classList.remove('active');
        });
        if (btn) btn.classList.add('active');
        applyFilters();
    };

    window.handleSearch = function(query) {
        currentSearch = query.trim().toLowerCase();
        applyFilters();
    };

    function applyFilters() {
        var rows = document.querySelectorAll('#backupTableBody tr');
        var visibleCount = 0;

        rows.forEach(function(row) {
            var typeMatch = (currentFilter === 'all') || (row.dataset.type === currentFilter);
            var filename = row.dataset.filename || '';
            var hash = row.dataset.hash || '';
            var searchMatch = !currentSearch || filename.indexOf(currentSearch) !== -1 || hash.indexOf(currentSearch) !== -1;

            if (typeMatch && searchMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        var badge = document.getElementById('backupCountBadge');
        if (badge) badge.textContent = visibleCount + ' ไฟล์';
    }

    window.copyHash = function(hash, btn) {
        navigator.clipboard.writeText(hash).then(function() {
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<span style="color:#10b981; font-weight:bold;">✓ Copied</span>';
            btn.style.borderColor = '#10b981';
            showToast('คัดลอก SHA-256 Checksum เรียบร้อยแล้ว', 'success');
            setTimeout(function() {
                btn.innerHTML = origHtml;
                btn.style.borderColor = '';
            }, 1800);
        }).catch(function() {
            showToast('ไม่สามารถคัดลอกได้ กรุณาลองใหม่อีกครั้ง', 'error');
        });
    };

    window.openBackupModal = function() {
        var modal = document.getElementById('manualBackupModal');
        modal.classList.add('active');
    };

    window.closeBackupModal = function() {
        var modal = document.getElementById('manualBackupModal');
        modal.classList.remove('active');
    };

    document.getElementById('manualBackupModal').addEventListener('click', function(e) {
        if (e.target === this) closeBackupModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && (e.key === 'b' || e.key === 'B')) {
            e.preventDefault();
            openBackupModal();
        }
        if (e.key === 'Escape') {
            closeBackupModal();
        }
    });

    window.handleFormSubmit = function(form) {
        var btn = document.getElementById('btnSubmitBackup');
        var text = document.getElementById('btnSubmitText');
        var spinner = document.getElementById('btnSubmitSpinner');
        btn.disabled = true;
        btn.style.opacity = '0.8';
        text.style.display = 'none';
        spinner.style.display = 'inline-flex';
    };

    function showToast(message, type) {
        var container = document.getElementById('backupToastContainer');
        var toast = document.createElement('div');
        var isSuccess = type === 'success';
        
        toast.style.cssText = 'padding: 0.85rem 1.25rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 8px; max-width: 400px; animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); ' +
            (isSuccess
                ? 'background: #0f172a; color: #ffffff; border: 1px solid rgba(16,185,129,0.3);'
                : 'background: #991b1b; color: #ffffff; border: 1px solid #f87171;');

        var icon = isSuccess
            ? '<svg style="width:18px; height:18px; color:#10b981; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'
            : '<svg style="width:18px; height:18px; color:#fca5a5; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';

        toast.innerHTML = icon + '<span>' + message + '</span>';
        container.appendChild(toast);

        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(function() { toast.remove(); }, 300);
        }, 4000);
    }
})();
</script>
@endsection
