import { useState } from 'react'
import { useWebSocket } from './hooks/useWebSocket'
import { ConnectionCard } from './components/ConnectionCard'
import { SpeedTestPage } from './components/SpeedTestCard'
import { SystemCard } from './components/SystemCard'
import { NetworkCard } from './components/NetworkCard'
import { ServicesCard } from './components/ServicesCard'
import { TrafficTable } from './components/TrafficTable'
import { Inspector } from './components/Inspector'
import { Status } from './components/Status'
import { Header } from './components/Header'
import { AlertsBanner } from './components/AlertsBanner'
import { AlertsHistory } from './components/AlertsHistory'
import { DeployCard } from './components/DeployCard'
import { Documentation } from './components/Documentation'
import { AdvancedStatus } from './components/AdvancedStatus'
import { AiScanner } from './components/AiScanner'
import { EventsCard } from './components/EventsCard'
import './App.css'

const NAV_TABS = [
  { id: 'dashboard',     label: 'Dashboard' },
  { id: 'events',        label: 'Events' },
  { id: 'speedtest',     label: 'Speed Test' },
  { id: 'inspector',     label: 'Inspector' },
  { id: 'status',        label: 'Status' },
  { id: 'deploy',        label: 'Deploy Logs' },
  { id: 'documentation', label: 'Documentation' },
  { id: 'advanced',      label: 'Advanced Status' },
  { id: 'aiscanner',     label: 'AI Scanner' },
]

export default function App() {
  const { data, connected } = useWebSocket()
  const [activeTab, setActiveTab] = useState('dashboard')
  const [isSidebarOpen, setIsSidebarOpen] = useState(true)

  return (
    <div className="layout">
      <Header connected={connected} toggleSidebar={() => setIsSidebarOpen(!isSidebarOpen)} />

      <div className="main-wrapper">
        {/* ── Sidebar Navigation ─────────────────────────────── */}
        <aside className={`sidebar ${isSidebarOpen ? 'open' : 'closed'}`}>
          <div className="sidebar-nav">
            {NAV_TABS.map(tab => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`nav-btn ${activeTab === tab.id ? 'active' : ''}`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </aside>

        {/* ── Main Content Area ─────────────────────────────── */}
        <div className="content-area">
          <main className="container">
            {/* Dashboard — no SpeedTestCard here anymore */}
        {activeTab === 'dashboard' && (
          <>
            <AlertsBanner alerts={data?.alerts} />
            <ConnectionCard url={data?.cf_url} status={data?.cf_status} lineStatus={data?.line_status} />
            <div className="grid-3">
              <SystemCard memory={data?.memory} load={data?.load} temp={data?.temp} disk={data?.disk} battery={data?.battery} />
              <NetworkCard network={data?.network} networkInfo={data?.network_info} />
              <ServicesCard services={data?.services} listeningPorts={data?.listening_ports} />
            </div>
            <TrafficTable logs={data?.logs} />
            <AlertsHistory history={data?.alerts_history} />
          </>
        )}

        {activeTab === 'events' && <EventsCard eventsData={data?.events} connected={connected} />}

        {/* Speed Test — dedicated page */}
        {activeTab === 'speedtest' && (
          <SpeedTestPage serverSpeedtest={data?.speedtest} />
        )}

        {activeTab === 'inspector' && <Inspector logs={data?.inspector} />}
        {activeTab === 'status'    && <Status data={data} />}
        {activeTab === 'deploy'    && (
          <DeployCard
            deployLog={data?.deploy_log}
            sshSessions={data?.ssh_sessions}
            sftpSessions={data?.sftp_sessions}
          />
        )}
        {activeTab === 'documentation' && <Documentation />}
        {activeTab === 'advanced'      && <AdvancedStatus data={data} />}
        {activeTab === 'aiscanner'     && (
          <AiScanner
            aiLog={data?.ai_log}
            serviceStatus={data?.services?.['AI Scan Service']}
          />
        )}
          </main>
        </div>
      </div>
    </div>
  )
}
