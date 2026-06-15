<x-filament-panels::page>
    <style>
        .health-card {
            background: linear-gradient(145deg, rgba(255,255,255,1) 0%, rgba(249,250,251,1) 100%);
            border: 1px solid rgba(229,231,235,0.5);
            border-radius: 1.25rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03), 0 4px 6px -4px rgba(0,0,0,0.02);
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .dark .health-card {
            background: linear-gradient(145deg, rgba(24,24,27,1) 0%, rgba(9,9,11,1) 100%);
            border: 1px solid rgba(63,63,70,0.5);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2), 0 4px 6px -4px rgba(0,0,0,0.1);
        }
        .health-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01);
        }
        .dark .health-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3), 0 8px 10px -6px rgba(0,0,0,0.2);
            border-color: rgba(63,63,70,0.8);
        }
        .health-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        }
        
        .progress-track {
            height: 0.6rem;
            width: 100%;
            background-color: #f3f4f6;
            border-radius: 999px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }
        .dark .progress-track {
            background-color: #27272a;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0.1) 100%);
            animation: shimmer 2s infinite linear;
            background-size: 200% 100%;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .metric-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            border-radius: 1rem;
            background: rgba(249,250,251,0.5);
            border: 1px solid rgba(229,231,235,0.3);
            text-align: center;
        }
        .dark .metric-box {
            background: rgba(24,24,27,0.5);
            border: 1px solid rgba(63,63,70,0.3);
        }
        
        .terminal-box {
            background-color: #09090b;
            border: 1px solid #27272a;
            border-radius: 1rem;
            padding: 1rem;
            font-family: 'Fira Code', 'Courier New', Courier, monospace;
            font-size: 0.75rem;
            color: #d4d4d8;
            overflow-x: auto;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
        }
        .terminal-line { margin-bottom: 0.25rem; line-height: 1.4; }
        .terminal-success { color: #10b981; text-shadow: 0 0 5px rgba(16,185,129,0.3); }
        .terminal-error { color: #f43f5e; text-shadow: 0 0 5px rgba(244,63,94,0.3); }
        .terminal-running { color: #3b82f6; text-shadow: 0 0 5px rgba(59,130,246,0.3); }

        .pulse-dot {
            height: 12px; width: 12px;
            border-radius: 50%;
            display: inline-block;
            position: relative;
        }
        .pulse-dot::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            border-radius: 50%;
            animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        .pulse-green { background-color: #10b981; box-shadow: 0 0 8px #10b981; }
        .pulse-green::before { border: 2px solid #10b981; }
        .pulse-red { background-color: #f43f5e; box-shadow: 0 0 8px #f43f5e; }
        .pulse-red::before { border: 2px solid #f43f5e; animation: none; }
        
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 0.5; }
            80%, 100% { opacity: 0; transform: scale(2.5); }
        }
        
        .alert-card {
            display: flex; align-items: flex-start;
            padding: 1.25rem; border-radius: 1rem; margin-bottom: 1rem;
            border-left: 5px solid;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            animation: slideIn 0.3s ease-out forwards;
        }
        .alert-danger { background-color: rgba(255,241,242,0.8); border-color: #f43f5e; }
        .dark .alert-danger { background-color: rgba(136,19,55,0.2); border-color: #f43f5e; }
        .alert-warning { background-color: rgba(255,251,235,0.8); border-color: #f59e0b; }
        .dark .alert-warning { background-color: rgba(120,53,15,0.2); border-color: #f59e0b; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .btn-custom {
            display: flex; align-items: center; justify-content: center;
            width: 100%; padding: 0.6rem 1rem; border-radius: 0.75rem;
            font-weight: 600; font-size: 0.875rem; transition: all 0.2s;
            cursor: pointer; gap: 0.5rem;
            border: none; color: white;
        }
        .btn-success { background: linear-gradient(to right, #10b981, #059669); box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.39); }
        .btn-success:hover { background: linear-gradient(to right, #059669, #047857); transform: translateY(-1px); }
        .btn-danger { background: linear-gradient(to right, #f43f5e, #e11d48); box-shadow: 0 4px 14px 0 rgba(244, 63, 94, 0.39); }
        .btn-danger:hover { background: linear-gradient(to right, #e11d48, #be123c); transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); }
        .dark .btn-outline { border-color: #4b5563; color: #d1d5db; }
        .btn-outline:hover { background: rgba(243,244,246,0.5); }
        .dark .btn-outline:hover { background: rgba(55,65,81,0.5); }
    </style>

    <div wire:poll.5s style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- Alertas de Gargalos / Bottlenecks -->
        @if(count($stats['bottlenecks']) > 0)
            <div>
                @foreach($stats['bottlenecks'] as $bottleneck)
                    <div class="alert-card {{ $bottleneck['type'] === 'danger' ? 'alert-danger' : 'alert-warning' }}">
                        <div style="margin-top: 2px;">
                            @if($bottleneck['type'] === 'danger')
                                <x-filament::icon icon="heroicon-o-x-circle" style="width: 1.5rem; height: 1.5rem; color: #f43f5e;" />
                            @else
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1.5rem; height: 1.5rem; color: #f59e0b;" />
                            @endif
                        </div>
                        <div style="margin-left: 1rem;">
                            <h3 style="font-size: 0.875rem; font-weight: 700; color: {{ $bottleneck['type'] === 'danger' ? '#be123c' : '#b45309' }}; margin: 0;">
                                Alerta Crítico do Sistema
                            </h3>
                            <div style="margin-top: 0.25rem; font-size: 0.875rem; color: {{ $bottleneck['type'] === 'danger' ? '#9f1239' : '#92400e' }};">
                                {{ $bottleneck['msg'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            
            <!-- RECURSOS DO SERVIDOR -->
            <div style="grid-column: span 2;">
                <x-filament::section>
                    <x-slot name="heading">Monitoramento de Recursos (Hardware & Banco de Dados)</x-slot>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                        
                        <!-- RAM PHP -->
                        <div class="health-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; color: #6b7280;">
                                    <x-filament::icon icon="heroicon-o-cpu-chip" style="width: 1.25rem; height: 1.25rem;" />
                                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Consumo RAM (PHP)</span>
                                </div>
                                <span style="font-size: 1rem; font-weight: 800; color: {{ $stats['php_memory_percent'] > 80 ? '#f43f5e' : '#10b981' }}">
                                    {{ $stats['php_memory_percent'] }}%
                                </span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: {{ $stats['php_memory_percent'] }}%; background-color: {{ $stats['php_memory_percent'] > 80 ? '#f43f5e' : '#10b981' }};"></div>
                            </div>
                            <div style="margin-top: 0.75rem; font-size: 0.75rem; font-weight: 500; color: #9ca3af; display: flex; justify-content: space-between;">
                                <span>Utilizado: <b>{{ $stats['php_memory'] }}</b></span>
                                <span>Limite: <b>{{ $stats['php_limit'] }}</b></span>
                            </div>
                        </div>

                        <!-- Disco -->
                        <div class="health-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; color: #6b7280;">
                                    <x-filament::icon icon="heroicon-o-server" style="width: 1.25rem; height: 1.25rem;" />
                                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Armazenamento Físico</span>
                                </div>
                                <span style="font-size: 1rem; font-weight: 800; color: {{ $stats['disk_used_percent'] > 85 ? '#f43f5e' : '#6366f1' }}">
                                    {{ $stats['disk_used_percent'] }}%
                                </span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: {{ $stats['disk_used_percent'] }}%; background-color: {{ $stats['disk_used_percent'] > 85 ? '#f43f5e' : '#6366f1' }};"></div>
                            </div>
                            <div style="margin-top: 0.75rem; font-size: 0.75rem; font-weight: 500; color: #9ca3af; display: flex; justify-content: space-between;">
                                <span>Livre: <b>{{ $stats['disk_free'] }}</b></span>
                                <span>Total: <b>{{ $stats['disk_total'] }}</b></span>
                            </div>
                        </div>

                    </div>

                    <!-- Métricas Estáticas BD e Sistema -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1.5rem;">
                        <div class="metric-box">
                            <span style="font-size: 0.65rem; color: #9ca3af; font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Carga da CPU (Load)</span>
                            <span style="font-size: 1.125rem; font-family: monospace; font-weight: 700; color: inherit;">{{ $stats['system_load'] }}</span>
                        </div>
                        <div class="metric-box">
                            <span style="font-size: 0.65rem; color: #9ca3af; font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Tamanho TimescaleDB</span>
                            <span style="font-size: 1.125rem; font-weight: 700; color: inherit;">{{ $stats['db_size'] }}</span>
                        </div>
                        <div class="metric-box" style="border-color: {{ $stats['db_latency'] > 150 ? 'rgba(245,158,11,0.5)' : 'rgba(16,185,129,0.3)' }};">
                            <span style="font-size: 0.65rem; color: #9ca3af; font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Latência do Banco</span>
                            <span style="font-size: 1.125rem; font-weight: 800; color: {{ $stats['db_latency'] > 150 ? '#f59e0b' : '#10b981' }};">{{ $stats['db_latency'] }} <span style="font-size:0.75rem;">ms</span></span>
                        </div>
                        <div class="metric-box">
                            <span style="font-size: 0.65rem; color: #9ca3af; font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Versão PostgreSQL</span>
                            <span style="font-size: 0.875rem; font-weight: 600; color: inherit; margin-top: 0.25rem;">{{ explode(' ', $stats['db_version'])[0] ?? 'N/A' }}</span>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <!-- CONTROLES WORKER E COLETOR -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <x-filament::section>
                    <x-slot name="heading">Gerenciamento de Fila Background</x-slot>
                    
                    <div class="health-card" style="margin-top: 1rem; margin-bottom: 1.5rem; padding: 1rem; display: flex; align-items: center; justify-content: center; gap: 1rem; background: {{ $stats['is_worker_running'] ? 'rgba(16,185,129,0.05)' : 'rgba(244,63,94,0.05)' }}; border-color: {{ $stats['is_worker_running'] ? 'rgba(16,185,129,0.3)' : 'rgba(244,63,94,0.3)' }};">
                        <span class="pulse-dot {{ $stats['is_worker_running'] ? 'pulse-green' : 'pulse-red' }}"></span>
                        <span style="font-size: 0.875rem; font-weight: 700; color: {{ $stats['is_worker_running'] ? '#10b981' : '#f43f5e' }};">
                            Worker: {{ $stats['worker_status_text'] }}
                        </span>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @if (!$stats['is_worker_running'])
                            <button type="button" wire:click="startQueueWorker" class="btn-custom btn-success">
                                <x-filament::icon icon="heroicon-o-play" style="width: 1.25rem; height: 1.25rem; color: white;" />
                                Ligar Motor de Coleta
                            </button>
                        @else
                            <button type="button" wire:click="stopQueueWorker" wire:confirm="Tem certeza que deseja forçar a parada do Worker de Coleta?" class="btn-custom btn-danger">
                                <x-filament::icon icon="heroicon-o-stop" style="width: 1.25rem; height: 1.25rem; color: white;" />
                                Desligar Motor de Coleta
                            </button>
                        @endif

                        <button type="button" wire:click="clearQueue" wire:confirm="Isso vai apagar todos os jobs na fila. Continuar?" class="btn-custom btn-outline">
                            <x-filament::icon icon="heroicon-o-trash" style="width: 1.25rem; height: 1.25rem;" />
                            Esvaziar Fila Completa
                        </button>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Sincronização Manual</x-slot>
                    <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 1rem; margin-top: 0.5rem; line-height: 1.5;">
                        Force a criação de um Job na fila agora mesmo.<br>
                        Último registro gravado no DB:<br>
                        <strong style="color: inherit; font-size: 0.875rem;">{{ $stats['last_climate_record'] }}</strong>
                    </p>
                    <button type="button" wire:click="runCollector" class="btn-custom" style="background: linear-gradient(to right, #3b82f6, #2563eb); box-shadow: 0 4px 14px 0 rgba(59,130,246,0.39);">
                        <x-filament::icon icon="heroicon-o-arrow-path" style="width: 1.25rem; height: 1.25rem; color: white;" />
                        Sincronizar Dados Agora
                    </button>
                </x-filament::section>
            </div>
        </div>

        <!-- TERMINAL / EVENTOS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <x-filament::section>
                <x-slot name="heading">Terminal Live do Queue Worker</x-slot>
                
                <div class="terminal-box" style="margin-top: 1rem;">
                    @if (empty($stats['worker_logs']) || str_contains($stats['worker_logs'], 'Nenhum log'))
                        <div class="terminal-line" style="display:flex; gap:0.5rem;">
                            <span style="color:#10b981;">➜</span>
                            <span>~/ccdae</span>
                            <span style="color:#60a5fa;">php artisan queue:work --verbose</span>
                        </div>
                        <div class="terminal-line" style="color:#fbbf24; margin-top:0.5rem;">[System] Worker ocioso ou aguardando inicialização...</div>
                    @else
                        @foreach (explode("\n", trim($stats['worker_logs'])) as $line)
                            @if (str_contains($line, 'FAIL'))
                                <div class="terminal-line terminal-error">{{ $line }}</div>
                            @elseif (str_contains($line, 'DONE'))
                                <div class="terminal-line terminal-success">{{ $line }}</div>
                            @elseif (str_contains($line, 'RUNNING'))
                                <div class="terminal-line terminal-running">{{ $line }}</div>
                            @else
                                <div class="terminal-line">{{ $line }}</div>
                            @endif
                        @endforeach
                    @endif
                </div>
                <p style="font-size: 0.65rem; color: #6b7280; text-align: right; margin-top: 0.5rem;">Caminho Real: storage/logs/queue-worker.log</p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Eventos Recentes (Spatie ActivityLog)</x-slot>
                <div style="margin-top: 0.5rem;">
                    {{ $this->table }}
                </div>
            </x-filament::section>
        </div>

    </div>
</x-filament-panels::page>
