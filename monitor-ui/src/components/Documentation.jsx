import { useState } from 'react'

export function Documentation() {
  const [copiedText, setCopiedText] = useState('')

  const handleCopy = (text) => {
    navigator.clipboard.writeText(text)
    setCopiedText(text)
    setTimeout(() => setCopiedText(''), 2000)
  }

  const sections = [
    {
      title: "System Architecture",
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="12 2 2 7 12 12 22 7 12 2" />
          <polyline points="2 17 12 22 22 17" />
          <polyline points="2 12 12 17 22 12" />
        </svg>
      ),
      content: (
        <div>
          <p style={{ margin: '0 0 1rem 0', color: '#4b5563', lineHeight: '1.5' }}>
            The application stack is hosted in <strong>Termux (Android)</strong> and tunneled to the public internet using Cloudflared.
          </p>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1rem' }}>
            <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.75rem' }}>
              <div style={{ fontWeight: 600, color: '#111827', marginBottom: '0.5rem' }}>🌐 Public Access</div>
              <ul style={{ margin: 0, paddingLeft: '1.25rem', color: '#4b5563', fontSize: '0.85rem', lineHeight: '1.6' }}>
                <li><strong>Cloudflared:</strong> Dynamic Tunnel</li>
                <li><strong>Web Entry:</strong> Port 8080 (Nginx)</li>
                <li><strong>WS Handshake:</strong> location /app/</li>
              </ul>
            </div>
            <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.75rem' }}>
              <div style={{ fontWeight: 600, color: '#111827', marginBottom: '0.5rem' }}>🚀 Application Services</div>
              <ul style={{ margin: 0, paddingLeft: '1.25rem', color: '#4b5563', fontSize: '0.85rem', lineHeight: '1.6' }}>
                <li><strong>PHP-FPM:</strong> Unix Socket Handler</li>
                <li><strong>Reverb:</strong> Port 8082 (WebSockets)</li>
                <li><strong>Queue:</strong> Redis Worker</li>
              </ul>
            </div>
            <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', padding: '1rem', borderRadius: '0.75rem' }}>
              <div style={{ fontWeight: 600, color: '#111827', marginBottom: '0.5rem' }}>💾 Data Infrastructure</div>
              <ul style={{ margin: 0, paddingLeft: '1.25rem', color: '#4b5563', fontSize: '0.85rem', lineHeight: '1.6' }}>
                <li><strong>PostgreSQL:</strong> Port 5432</li>
                <li><strong>Redis Server:</strong> Port 6379</li>
                <li><strong>Telemetry logs:</strong> UDP Port 9998</li>
              </ul>
            </div>
          </div>
        </div>
      )
    },
    {
      title: "Boot & Autostart Configurations",
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
          <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
          <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
        </svg>
      ),
      content: (
        <div>
          <p style={{ margin: '0 0 1rem 0', color: '#4b5563', lineHeight: '1.5' }}>
            System startup is automated using two distinct layers to handle boots and app-reopens:
          </p>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', padding: '1rem', borderRadius: '0.75rem' }}>
              <div style={{ fontWeight: 600, color: '#1e3a8a', marginBottom: '0.25rem' }}>📱 1. Termux:Boot Integration (Device Reboot)</div>
              <p style={{ margin: '0 0 0.5rem 0', fontSize: '0.85rem', color: '#1e40af' }}>
                Runs automatically on Android boot. Script path on server:
              </p>
              <code style={{ background: '#fff', color: '#1d4ed8', border: '1px solid #bfdbfe', padding: '0.25rem 0.5rem', borderRadius: '0.25rem', fontSize: '0.8rem', fontWeight: 600 }}>
                ~/.termux/boot/start_server.sh
              </code>
            </div>
            <div style={{ background: '#f5f3ff', border: '1px solid #ddd6fe', padding: '1rem', borderRadius: '0.75rem' }}>
              <div style={{ fontWeight: 600, color: '#4c1d95', marginBottom: '0.25rem' }}>🐚 2. Shell Hook Autostart (Termux App Launch)</div>
              <p style={{ margin: '0 0 0.5rem 0', fontSize: '0.85rem', color: '#5b21b6' }}>
                Monitors Port 9999 and starts the python server automatically whenever you open Termux. Sourced in:
              </p>
              <code style={{ background: '#fff', color: '#6d28d9', border: '1px solid #ddd6fe', padding: '0.25rem 0.5rem', borderRadius: '0.25rem', fontSize: '0.8rem', fontWeight: 600 }}>
                ~/.bashrc / ~/.zshrc via scripts/shell_logger.sh
              </code>
            </div>
          </div>
        </div>
      )
    },
    {
      title: "CLI Command Reference",
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polyline points="4 17 10 11 4 5" />
          <line x1="12" y1="19" x2="20" y2="19" />
        </svg>
      ),
      content: (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {[
            {
              desc: "Re-run full system boot script (Postgres, Redis, Nginx, PHP, Reverb, CF Tunnel, Monitor)",
              cmd: "bash ~/.termux/boot/start_server.sh"
            },
            {
              desc: "Deploy latest Nginx, Shell logger, and Laravel updates from local codebase",
              cmd: "python finish_deploy.py"
            },
            {
              desc: "Deploy latest Monitor server configurations & rebuild React Frontend UI",
              cmd: "python deploy_monitor.py"
            },
            {
              desc: "Manually stop all active public tunnels (Cloudflared)",
              cmd: "pkill -f cloudflared"
            },
            {
              desc: "Start Laravel Reverb WebSocket server manually",
              cmd: "php artisan reverb:start --port=8082"
            }
          ].map((item, index) => (
            <div key={index} style={{ borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem' }}>
              <div style={{ fontSize: '0.85rem', color: '#6b7280', marginBottom: '0.5rem' }}>{item.desc}</div>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: '#1e293b', borderRadius: '0.5rem', padding: '0.5rem 0.75rem', gap: '1rem' }}>
                <code style={{ color: '#38bdf8', fontSize: '0.8rem', wordBreak: 'break-all', fontFamily: 'monospace' }}>
                  {item.cmd}
                </code>
                <button 
                  onClick={() => handleCopy(item.cmd)}
                  style={{ background: '#334155', border: 'none', color: '#fff', fontSize: '0.7rem', padding: '0.25rem 0.5rem', borderRadius: '0.25rem', cursor: 'pointer', fontWeight: 600, minWidth: '60px' }}
                >
                  {copiedText === item.cmd ? 'Copied!' : 'Copy'}
                </button>
              </div>
            </div>
          ))}
        </div>
      )
    }
  ]

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      <div className="card">
        <h1 style={{ margin: '0 0 0.5rem 0', fontSize: '1.5rem', fontWeight: 700, color: '#111827' }}>System Documentation</h1>
        <p style={{ margin: 0, color: '#6b7280', fontSize: '0.9rem' }}>Quick reference guides, configurations, commands and architecture structure.</p>
      </div>

      {sections.map((section, idx) => (
        <div key={idx} className="card" style={{ padding: '1.5rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', borderBottom: '1px solid #f3f4f6', paddingBottom: '0.75rem', marginBottom: '1rem' }}>
            <span style={{ color: '#2563eb' }}>{section.icon}</span>
            <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#111827' }}>{section.title}</h2>
          </div>
          {section.content}
        </div>
      ))}
    </div>
  )
}
