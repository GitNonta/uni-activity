import { useState } from 'react'
import { useWebSocket } from './hooks/useWebSocket'
import { ConnectionCard } from './components/ConnectionCard'
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
import './App.css'

export default function App() {
  const { data, connected } = useWebSocket()
  const [activeTab, setActiveTab] = useState('dashboard')

  return (
    <div className="layout">
      <Header connected={connected} />
      
      <div style={{ padding: '1rem 2rem 0', display: 'flex', gap: '1rem', borderBottom: '1px solid #e5e7eb', background: '#fff' }}>
        <button 
          onClick={() => setActiveTab('dashboard')}
          style={{ padding: '0.75rem 1rem', background: 'none', border: 'none', borderBottom: activeTab === 'dashboard' ? '2px solid #2563eb' : '2px solid transparent', color: activeTab === 'dashboard' ? '#2563eb' : '#6b7280', fontWeight: activeTab === 'dashboard' ? 600 : 400, cursor: 'pointer', fontSize: '1rem' }}
        >
          Dashboard
        </button>
        <button 
          onClick={() => setActiveTab('inspector')}
          style={{ padding: '0.75rem 1rem', background: 'none', border: 'none', borderBottom: activeTab === 'inspector' ? '2px solid #2563eb' : '2px solid transparent', color: activeTab === 'inspector' ? '#2563eb' : '#6b7280', fontWeight: activeTab === 'inspector' ? 600 : 400, cursor: 'pointer', fontSize: '1rem' }}
        >
          Inspector
        </button>
        <button 
          onClick={() => setActiveTab('status')}
          style={{ padding: '0.75rem 1rem', background: 'none', border: 'none', borderBottom: activeTab === 'status' ? '2px solid #2563eb' : '2px solid transparent', color: activeTab === 'status' ? '#2563eb' : '#6b7280', fontWeight: activeTab === 'status' ? 600 : 400, cursor: 'pointer', fontSize: '1rem' }}
        >
          Status
        </button>
        <button 
          onClick={() => setActiveTab('deploy')}
          style={{ padding: '0.75rem 1rem', background: 'none', border: 'none', borderBottom: activeTab === 'deploy' ? '2px solid #2563eb' : '2px solid transparent', color: activeTab === 'deploy' ? '#2563eb' : '#6b7280', fontWeight: activeTab === 'deploy' ? 600 : 400, cursor: 'pointer', fontSize: '1rem' }}
        >
          Deploy Logs
        </button>
        <button 
          onClick={() => setActiveTab('documentation')}
          style={{ padding: '0.75rem 1rem', background: 'none', border: 'none', borderBottom: activeTab === 'documentation' ? '2px solid #2563eb' : '2px solid transparent', color: activeTab === 'documentation' ? '#2563eb' : '#6b7280', fontWeight: activeTab === 'documentation' ? 600 : 400, cursor: 'pointer', fontSize: '1rem' }}
        >
          Documentation
        </button>
        <button 
          onClick={() => setActiveTab('advanced')}
          style={{ padding: '0.75rem 1rem', background: 'none', border: 'none', borderBottom: activeTab === 'advanced' ? '2px solid #2563eb' : '2px solid transparent', color: activeTab === 'advanced' ? '#2563eb' : '#6b7280', fontWeight: activeTab === 'advanced' ? 600 : 400, cursor: 'pointer', fontSize: '1rem' }}
        >
          Advanced Status
        </button>
        <button 
          onClick={() => setActiveTab('aiscanner')}
          style={{ padding: '0.75rem 1rem', background: 'none', border: 'none', borderBottom: activeTab === 'aiscanner' ? '2px solid #2563eb' : '2px solid transparent', color: activeTab === 'aiscanner' ? '#2563eb' : '#6b7280', fontWeight: activeTab === 'aiscanner' ? 600 : 400, cursor: 'pointer', fontSize: '1rem' }}
        >
          AI Scanner
        </button>
      </div>

      <main className="container">
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
        {activeTab === 'inspector' && (
          <Inspector logs={data?.inspector} />
        )}
        {activeTab === 'status' && (
          <Status data={data} />
        )}
        {activeTab === 'deploy' && (
          <DeployCard 
            deployLog={data?.deploy_log} 
            sshSessions={data?.ssh_sessions} 
            sftpSessions={data?.sftp_sessions} 
          />
        )}
        {activeTab === 'documentation' && (
          <Documentation />
        )}
        {activeTab === 'advanced' && (
          <AdvancedStatus data={data} />
        )}
        {activeTab === 'aiscanner' && (
          <AiScanner 
            aiLog={data?.ai_log} 
            serviceStatus={data?.services?.["AI Scan Service"]} 
          />
        )}
      </main>
    </div>
  )
}
