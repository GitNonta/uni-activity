@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Face Recognition System Metrics</h1>
        <p class="text-gray-600 dark:text-gray-300">Real-time performance monitoring and system health</p>
    </div>

    <!-- System Health Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- AI Server Status -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">AI Server</h3>
                    <p class="text-xl font-semibold" id="ai-server-status">Loading...</p>
                </div>
            </div>
        </div>

        <!-- Processing Mode -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 dark:bg-green-900 rounded">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Processing Mode</h3>
                    <p class="text-xl font-semibold" id="processing-mode">Hybrid</p>
                </div>
            </div>
        </div>

        <!-- Response Time -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Response Time</h3>
                    <p class="text-xl font-semibold" id="response-time">-</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- AI Server Details -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">AI Server Status</h3>
            <div id="ai-server-details">
                <div class="animate-pulse">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                </div>
            </div>
        </div>

        <!-- System Resources -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">System Resources</h3>
            <div id="system-resources">
                <div class="animate-pulse">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rate Limits Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Rate Limits & Configuration</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mode</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Requests/Minute</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                    </tr>
                </thead>
                <tbody id="rate-limits-table" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <!-- Will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let metricsData = {};

async function loadMetrics() {
    try {
        const response = await fetch('/api/face/metrics', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        if (response.ok) {
            metricsData = await response.json();
            updateUI();
        } else {
            console.error('Failed to load metrics:', response.status);
        }
    } catch (error) {
        console.error('Error loading metrics:', error);
    }
}

function updateUI() {
    // AI Server Status
    const aiServer = metricsData.ai_server || {};
    const statusElement = document.getElementById('ai-server-status');
    if (aiServer.available) {
        statusElement.textContent = 'Online';
        statusElement.className = 'text-xl font-semibold text-green-600 dark:text-green-400';
    } else {
        statusElement.textContent = 'Offline';
        statusElement.className = 'text-xl font-semibold text-red-600 dark:text-red-400';
    }
    
    // Response Time
    const responseTime = aiServer.response_time_ms || '-';
    document.getElementById('response-time').textContent = responseTime !== '-' ? responseTime + 'ms' : '-';
    
    // AI Server Details
    const detailsHtml = `
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                <span class="text-sm font-medium ${aiServer.available ? 'text-green-600' : 'text-red-600'}">${aiServer.status || 'Unknown'}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Pipeline:</span>
                <span class="text-sm font-medium">${aiServer.pipeline || 'Unknown'}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Models:</span>
                <div class="text-right">
                    ${Object.entries(aiServer.models || {}).map(([model, status]) => 
                        `<div class="text-xs ${status ? 'text-green-600' : 'text-red-600'}">${model}: ${status ? '✓' : '✗'}</div>`
                    ).join('')}
                </div>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Last Check:</span>
                <span class="text-sm">${aiServer.checked_at ? new Date(aiServer.checked_at * 1000).toLocaleTimeString() : '-'}</span>
            </div>
        </div>
    `;
    document.getElementById('ai-server-details').innerHTML = detailsHtml;
    
    // System Resources
    const system = metricsData.system || {};
    const resourcesHtml = `
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Memory Usage:</span>
                <span class="text-sm font-medium">${formatBytes(system.memory_usage || 0)}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Peak Memory:</span>
                <span class="text-sm font-medium">${formatBytes(system.peak_memory || 0)}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Fallback Threshold:</span>
                <span class="text-sm font-medium">${metricsData.fallback_threshold || 3} failures</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">Timeout:</span>
                <span class="text-sm font-medium">${metricsData.timeout_seconds || 8}s</span>
            </div>
        </div>
    `;
    document.getElementById('system-resources').innerHTML = resourcesHtml;
    
    // Rate Limits Table
    const rateLimits = metricsData.rate_limits || {};
    const tableHtml = Object.entries(rateLimits).map(([mode, limit]) => {
        const descriptions = {
            python: 'High accuracy, AI server processing',
            js: 'Fast response, client-side processing',
            hybrid: 'Balanced approach with automatic fallback'
        };
        
        return `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white capitalize">${mode}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${limit}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${descriptions[mode] || ''}</td>
            </tr>
        `;
    }).join('');
    document.getElementById('rate-limits-table').innerHTML = tableHtml;
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Load metrics on page load
document.addEventListener('DOMContentLoaded', () => {
    loadMetrics();
    
    // Refresh every 30 seconds
    setInterval(loadMetrics, 30000);
});
</script>
@endsection