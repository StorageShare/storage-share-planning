<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Syslog Viewer') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    <!-- Controls -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div class="md:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Zoeken in logs</label>
                            <input type="text" 
                                   id="search" 
                                   value="{{ $search }}" 
                                   placeholder="Zoek in log entries..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <!-- Lines -->
                        <div>
                            <label for="lines" class="block text-sm font-medium text-gray-700 mb-1">Aantal regels</label>
                            <select id="lines" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="50" {{ $lines == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $lines == 100 ? 'selected' : '' }}>100</option>
                                <option value="200" {{ $lines == 200 ? 'selected' : '' }}>200</option>
                                <option value="500" {{ $lines == 500 ? 'selected' : '' }}>500</option>
                            </select>
                        </div>
                        
                        <!-- Controls -->
                        <div class="flex flex-col space-y-2">
                            <button id="refresh-btn" 
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors">
                                🔄 Refresh
                            </button>
                            <button id="auto-refresh-btn" 
                                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition-colors">
                                ⏱️ Auto (5s)
                            </button>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-4 flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <span id="log-count">{{ count($logs) }}</span> entries gevonden
                            <span id="last-updated" class="ml-4">Laatste update: {{ now()->format('H:i:s') }}</span>
                        </div>
                        <div id="auto-refresh-status" class="text-sm text-gray-500 hidden">
                            Auto-refresh: <span class="text-green-600">ON</span>
                        </div>
                    </div>
                    
                    <!-- Log Display -->
                    <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto" 
                         style="max-height: 600px; overflow-y: auto;" 
                         id="log-container">
                        
                        <div id="log-content">
                            @forelse($logs as $log)
                                <div class="mb-1 hover:bg-gray-800 px-2 py-1 rounded">
                                    {{ $log }}
                                </div>
                            @empty
                                <div class="text-yellow-400">
                                    Geen syslog entries gevonden voor {{ config('app.name') }}-{{ config('app.env') }}
                                    <br><br>
                                    <div class="text-gray-400">
                                        Mogelijke oorzaken:
                                        <ul class="mt-2 ml-4">
                                            <li>• Syslog is nog niet actief (zet LOG_CHANNEL=syslog in .env)</li>
                                            <li>• Er zijn nog geen log entries gegenereerd</li>
                                            <li>• Geen toegang tot syslog bestanden</li>
                                        </ul>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                        
                        <!-- Loading indicator -->
                        <div id="loading" class="hidden text-center py-4">
                            <div class="text-blue-400">⏳ Logs laden...</div>
                        </div>
                    </div>
                    
                    <!-- Info Panel -->
                    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-blue-900 mb-2">ℹ️ Syslog Informatie</h3>
                        <div class="text-sm text-blue-800 space-y-1">
                            <p><strong>App Identifier:</strong> {{ config('app.name') }}-{{ config('app.env') }}</p>
                            <p><strong>Log Level:</strong> {{ config('logging.level', 'debug') }}</p>
                            <p><strong>Huidige Channel:</strong> {{ config('logging.default') }}</p>
                        </div>
                        
                        <div class="mt-3 text-xs text-blue-600">
                            <p><strong>Tips:</strong></p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Gebruik de zoekfunctie om specifieke entries te vinden</li>
                                <li>Auto-refresh houdt de logs automatisch up-to-date</li>
                                <li>Klik op log entries voor meer details</li>
                                <li>Gebruik <code>php artisan logs:syslog</code> via CLI voor real-time monitoring</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for real-time updates -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let autoRefreshInterval = null;
        let isAutoRefreshActive = false;
        
        const searchInput = document.getElementById('search');
        const linesSelect = document.getElementById('lines');
        const refreshBtn = document.getElementById('refresh-btn');
        const autoRefreshBtn = document.getElementById('auto-refresh-btn');
        const logContent = document.getElementById('log-content');
        const loading = document.getElementById('loading');
        const logCount = document.getElementById('log-count');
        const lastUpdated = document.getElementById('last-updated');
        const autoRefreshStatus = document.getElementById('auto-refresh-status');
        
        // Refresh logs function
        function refreshLogs() {
            loading.classList.remove('hidden');
            refreshBtn.disabled = true;
            refreshBtn.textContent = '⏳ Loading...';
            
            const params = new URLSearchParams({
                lines: linesSelect.value,
                search: searchInput.value
            });
            
            fetch(`{{ route('admin.logs.syslog.api') }}?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.logs) {
                        logContent.innerHTML = '';
                        
                        if (data.logs.length === 0) {
                            logContent.innerHTML = '<div class="text-yellow-400">Geen entries gevonden met de huidige filters</div>';
                        } else {
                            data.logs.forEach(log => {
                                const div = document.createElement('div');
                                div.className = 'mb-1 hover:bg-gray-800 px-2 py-1 rounded cursor-pointer';
                                div.textContent = log;
                                
                                // Add click handler for detailed view
                                div.addEventListener('click', function() {
                                    alert('Log Details:\n\n' + log);
                                });
                                
                                logContent.appendChild(div);
                            });
                        }
                        
                        logCount.textContent = data.count;
                        lastUpdated.textContent = 'Laatste update: ' + new Date().toLocaleTimeString();
                    }
                })
                .catch(error => {
                    console.error('Error fetching logs:', error);
                    logContent.innerHTML = '<div class="text-red-400">❌ Fout bij het laden van logs: ' + error.message + '</div>';
                })
                .finally(() => {
                    loading.classList.add('hidden');
                    refreshBtn.disabled = false;
                    refreshBtn.textContent = '🔄 Refresh';
                });
        }
        
        // Auto refresh toggle
        function toggleAutoRefresh() {
            if (isAutoRefreshActive) {
                // Stop auto refresh
                clearInterval(autoRefreshInterval);
                isAutoRefreshActive = false;
                autoRefreshBtn.textContent = '⏱️ Auto (5s)';
                autoRefreshBtn.className = 'bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition-colors';
                autoRefreshStatus.classList.add('hidden');
            } else {
                // Start auto refresh
                isAutoRefreshActive = true;
                autoRefreshBtn.textContent = '⏹️ Stop Auto';
                autoRefreshBtn.className = 'bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition-colors';
                autoRefreshStatus.classList.remove('hidden');
                
                autoRefreshInterval = setInterval(refreshLogs, 5000);
                refreshLogs(); // Immediate refresh
            }
        }
        
        // Event listeners
        refreshBtn.addEventListener('click', refreshLogs);
        autoRefreshBtn.addEventListener('click', toggleAutoRefresh);
        
        // Search on Enter or after typing pause
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(refreshLogs, 1000);
        });
        
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(searchTimeout);
                refreshLogs();
            }
        });
        
        linesSelect.addEventListener('change', refreshLogs);
        
        // Auto-scroll to bottom
        const logContainer = document.getElementById('log-container');
        function scrollToBottom() {
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // Scroll to bottom on new content
        const observer = new MutationObserver(scrollToBottom);
        observer.observe(logContent, { childList: true });
    });
    </script>
</x-app-layout> 