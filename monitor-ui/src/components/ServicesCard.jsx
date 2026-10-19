import { useState } from "react";

const SERVICE_TARGET = {
  "Nginx (Edge Proxy)": "nginx",
  "PostgreSQL Database": "postgres",
  "Datastore (Valkey)": "valkey",
  "Queue Store (Valkey)": "valkey",
  "Laravel Reverb (WebSocket)": "reverb",
  "Web Workers (artisan serve)": "web_workers",
  "Queue Worker": "queue_worker",
  "PHP-FPM": "php_fpm",
};

export function ServicesCard({ services, listeningPorts }) {
  const srv = services ?? {}
  const ports = Array.isArray(listeningPorts) ? listeningPorts : []
  const [busy, setBusy] = useState(null)
  const [msg, setMsg] = useState(null)

  const doAction = async (target, action) => {
    setBusy(target + ":" + action)
    try {
      const res = await fetch("/api/control", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ target, action }),
      })
      const j = await res.json()
      setMsg(target + " " + action + " → " + (j.message || j.status))
    } catch (e) {
      setMsg(target + " " + action + " → failed: " + e.message)
    }
    setTimeout(() => setBusy(null), 2500)
  }

  return (
    <div className="card">
      <div className="card-header">
        <svg className="card-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
          <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
          <line x1="6" y1="6" x2="6.01" y2="6"/>
          <line x1="6" y1="18" x2="6.01" y2="18"/>
        </svg>
        <h2 className="card-title">Services Status</h2>
        <button
          onClick={() => doAction("all", "restart")}
          disabled={busy !== null}
          title="Restart every controllable service"
          style={{
            marginLeft: "auto",
            fontSize: "0.72rem",
            fontWeight: 600,
            padding: "0.3rem 0.6rem",
            borderRadius: "0.4rem",
            border: "1px solid #fca5a5",
            background: busy ? "#fee2e2" : "#fef2f2",
            color: "#dc2626",
            cursor: busy ? "wait" : "pointer",
          }}
        >
          🔄 Restart All
        </button>
      </div>

      <div style={{ display: "flex", flexDirection: "column", gap: "0.75rem" }}>
        {Object.keys(srv).length === 0 && (
          <div style={{ color: "var(--muted)", fontSize: "0.85rem" }}>Waiting for data...</div>
        )}

        {Object.entries(srv).map(([name, status]) => {
          const isRunning = typeof status === "string" && (status.startsWith("Running") || status.startsWith("Active") || status.startsWith("Online"));
          const target = SERVICE_TARGET[name]
          const isBusy = busy === (target || name) + ":restart"
          return (
            <div key={name} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", borderBottom: "1px solid #f3f4f6", paddingBottom: "0.5rem", gap: "0.5rem" }}>
              <span style={{ fontSize: "0.85rem", fontWeight: 600, color: "#374151", flex: 1 }}>{name}</span>
              <span style={{ display: "flex", alignItems: "center", gap: "0.4rem", fontSize: "0.75rem", fontWeight: 600, color: isRunning ? "#059669" : "#dc2626" }}>
                <span style={{ width: 8, height: 8, borderRadius: "50%", background: isRunning ? "#10b981" : "#ef4444", boxShadow: "0 0 4px " + (isRunning ? "#10b981" : "#ef4444") }}></span>
                {status}
              </span>
              {target && (
                <button
                  onClick={() => doAction(target, "restart")}
                  disabled={busy !== null}
                  title={"Restart " + name}
                  style={{
                    fontSize: "0.7rem",
                    fontWeight: 600,
                    padding: "0.15rem 0.45rem",
                    borderRadius: "0.3rem",
                    border: "1px solid #d1d5db",
                    background: isBusy ? "#e5e7eb" : "#f9fafb",
                    color: "#374151",
                    cursor: busy ? "wait" : "pointer",
                  }}
                >
                  {isBusy ? "…" : "↻"}
                </button>
              )}
            </div>
          );
        })}
      </div>

      {msg && (
        <div style={{ marginTop: "0.75rem", fontSize: "0.75rem", color: "#2563eb", background: "#eff6ff", borderRadius: "0.375rem", padding: "0.4rem 0.6rem", border: "1px solid #bfdbfe" }}>
          {msg}
        </div>
      )}

      {ports.length > 0 && (
        <div style={{ marginTop: "1.25rem", paddingTop: "1rem", borderTop: "2px dashed #e5e7eb" }}>
          <h3 style={{ margin: "0 0 0.75rem 0", fontSize: "0.8rem", color: "#6b7280", textTransform: "uppercase", letterSpacing: "0.05em", fontWeight: 600 }}>
            Active Ports ({ports.length})
          </h3>
          <div style={{ display: "flex", flexWrap: "wrap", gap: "0.4rem" }}>
            {ports.map(port => {
              const portLabels = {
                1080: "SOCKS5 Proxy (Go)",
                8000: "Web Worker 1 (artisan serve)",
                8002: "Web Worker 2 (artisan serve)",
                8003: "Web Worker 3 (artisan serve)",
                8080: "Nginx Edge (Web)",
                8088: "Nginx Load Balancer",
                8082: "Laravel Reverb (WS)",
                9090: "Prometheus",
                9999: "Monitor API / UI",
                9998: "Telemetry (UDP)",
                8022: "SSH / SFTP",
                5432: "PostgreSQL",
                6379: "Valkey Datastore",
                6380: "Valkey Queue Store"
              };
              const label = portLabels[port] || "Port " + port;
              return (
                <span
                  key={port}
                  title={label}
                  style={{
                    fontSize: "0.72rem",
                    background: "#eff6ff",
                    color: "#2563eb",
                    padding: "0.2rem 0.5rem",
                    borderRadius: "0.375rem",
                    fontWeight: 600,
                    border: "1px solid #bfdbfe"
                  }}
                >
                  {port} {portLabels[port] ? "(" + portLabels[port].split(" ")[0] + ")" : ""}
                </span>
              );
            })}
          </div>
        </div>
      )}
    </div>
  )
}
