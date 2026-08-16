import { useState, useEffect } from 'react'
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
  { id: 'status',        label: 'System Status' },
  { id: 'events',        label: 'Deployment Events' },
  { id: 'deploy',        label: 'Deploy Logs' },
  { id: 'inspector',     label: 'Log Inspector' },
  { id: 'speedtest',     label: 'Network Speed Test' },
  { id: 'advanced',      label: 'Advanced Details' },
  { id: 'aiscanner',     label: 'AI Scanner' },
  { id: 'documentation', label: 'Documentation' },
]

export default function App() {
  const { data, connected } = useWebSocket()
  
  // Read initial tab from URL hash (e.g. #events) or default to dashboard
  const getInitialTab = () => {
    const hash = window.location.hash.replace('#', '')
    const baseTab = hash.split('/')[0]
    if (NAV_TABS.find(t => t.id === baseTab)) return baseTab
    return 'dashboard'
  }
  
  const [activeTab, setActiveTab] = useState(getInitialTab)
  const [isSidebarOpen, setIsSidebarOpen] = useState(true)
  const [selectedEvent, setSelectedEvent] = useState(null)

  // Sync state to URL hash
  const handleTabChange = (tabId) => {
    setActiveTab(tabId)
    setSelectedEvent(null) // clear selected event when switching tabs
    window.history.pushState(null, '', `#${tabId}`)
  }

  const handleEventDeployClick = (ev) => {
    setSelectedEvent(ev)
    // Create a long pseudo-URL like Render.com
    const ts = encodeURIComponent(ev.timestamp || new Date().toISOString())
    // Using the user's specific Render IDs
    const deployId = `dep-${ev.hash}${Date.now().toString().slice(-6)}`
    window.history.pushState(null, '', `#events/web/srv-d91sgl3tqb8s739ke9og/deploys/${deployId}?r=${ts}`)
  }

  // Listen to browser back/forward buttons
  useEffect(() => {
    const handlePopState = () => {
      const hash = window.location.hash.replace('#', '')
      const baseTab = hash.split('/')[0]
      
      if (NAV_TABS.find(t => t.id === baseTab)) {
        setActiveTab(baseTab)
      } else {
        setActiveTab('dashboard')
      }
      
      if (!hash.includes('/deploys/dep-')) {
        setSelectedEvent(null)
      }
    }
    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [])


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
                onClick={() => handleTabChange(tab.id)}
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

        {activeTab === 'events' && (
          selectedEvent ? (
            <DeployCard
              deployLog={
                data?.github_deploy_logs?.[selectedEvent.hash] 
                || (selectedEvent?.id?.includes('status') || selectedEvent?.detail?.includes('Live') 
                    ? data?.github_deploy_logs?.latest 
                    : '')
              }
              deployChannels={data?.deploy_channels}
              sshSessions={data?.ssh_sessions}
              sftpSessions={data?.sftp_sessions}
              scpSessions={data?.scp_sessions}
              selectedEvent={selectedEvent}
              onBack={() => {
                setSelectedEvent(null)
                window.history.pushState(null, '', '#events')
              }}
            />
          ) : (
            <EventsCard 
              eventsData={data?.events} 
              publicIp={data?.public_ip} 
              connected={connected} 
              onEventClick={handleEventDeployClick} 
            />
          )
        )}

        {/* Speed Test — dedicated page */}
        {activeTab === 'speedtest' && (
          <SpeedTestPage serverSpeedtest={data?.speedtest} />
        )}

        {activeTab === 'inspector' && <Inspector logs={data?.inspector} />}
        {activeTab === 'status'    && <Status data={data} />}
        {activeTab === 'deploy'    && (
          <DeployCard
            deployLog={data?.deploy_log}
            deployChannels={data?.deploy_channels}
            sshSessions={data?.ssh_sessions}
            sftpSessions={data?.sftp_sessions}
            scpSessions={data?.scp_sessions}
            selectedEvent={selectedEvent}
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
