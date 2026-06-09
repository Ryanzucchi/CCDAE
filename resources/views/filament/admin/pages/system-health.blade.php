<x-filament-panels::page>
    <div wire:poll.5s class="space-y-6">
        <!-- Control actions inside standard Filament sections -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Queue Worker Controls Section -->
            <x-filament::section>
                <x-slot name="heading">Status & Controles do Queue Worker</x-slot>
                
                <div class="flex items-center space-x-3 mt-2">
                    <span class="flex h-3 w-3 relative">
                        @if ($stats['is_worker_running'])
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        @else
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                        @endif
                    </span>
                    
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ $stats['worker_status_text'] }}
                    </span>
                </div>
                
                <p class="text-xs text-gray-500 mt-4 leading-relaxed">
                    O queue worker processa as tarefas em background (como a coleta de clima). 
                    Gerencie o ciclo de vida do worker usando os botões nativos abaixo:
                </p>

                <div class="flex flex-wrap gap-2 mt-5">
                    @if (!$stats['is_worker_running'])
                        <x-filament::button 
                            color="success" 
                            icon="heroicon-o-play" 
                            wire:click="startQueueWorker"
                            size="sm"
                        >
                            Iniciar Worker
                        </x-filament::button>
                    @else
                        <x-filament::button 
                            color="danger" 
                            icon="heroicon-o-stop" 
                            wire:click="stopQueueWorker"
                            requires-confirmation
                            size="sm"
                        >
                            Parar Worker
                        </x-filament::button>
                    @endif

                    <x-filament::button 
                        color="warning" 
                        icon="heroicon-o-trash" 
                        wire:click="clearQueue"
                        requires-confirmation
                        size="sm"
                    >
                        Limpar Fila
                    </x-filament::button>
                </div>
            </x-filament::section>

            <!-- Climate Collector Control & Last Log Section -->
            <x-filament::section>
                <x-slot name="heading">Coletor Automático de Clima</x-slot>
                
                <div class="space-y-4 mt-2">
                    @if ($stats['last_job'])
                        <div>
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-semibold">Último Processamento</span>
                            <span class="text-xs font-medium text-gray-800 dark:text-gray-200 flex items-center gap-1 mt-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                {{ $stats['last_job']->description }}
                                <span class="text-gray-400">({{ \Carbon\Carbon::parse($stats['last_job']->created_at)->diffForHumans() }})</span>
                            </span>
                        </div>
                    @else
                        <p class="text-xs text-gray-500">Nenhum job de coleta processado recentemente.</p>
                    @endif
                    
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Inicie uma coleta manual imediata disparando o job de clima diretamente para a fila:
                    </p>

                    <div class="pt-1">
                        <x-filament::button 
                            color="primary" 
                            icon="heroicon-o-arrow-path" 
                            wire:click="runCollector"
                            size="sm"
                        >
                            Rodar Coletor Agora
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <!-- System & Database Resource Stats Section -->
        <x-filament::section>
            <x-slot name="heading">Recursos do Servidor e Configurações</x-slot>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
                <div class="space-y-2 p-4 bg-gray-50 dark:bg-gray-850 rounded-xl border border-gray-100 dark:border-gray-800">
                    <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-semibold">Uso de Memória PHP</span>
                    <div class="flex justify-between text-xs font-semibold text-gray-700 dark:text-gray-300">
                        <span>{{ $stats['php_memory'] }} / {{ $stats['php_limit'] }}</span>
                        <span>{{ $stats['php_memory_percent'] }}%</span>
                    </div>
                    <progress class="progress progress-primary w-full" value="{{ $stats['php_memory_percent'] }}" max="100"></progress>
                </div>

                <div class="space-y-2 p-4 bg-gray-50 dark:bg-gray-850 rounded-xl border border-gray-100 dark:border-gray-800 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-semibold">Carga da CPU (Load)</span>
                        <div class="text-sm font-mono font-bold text-gray-800 dark:text-gray-200 mt-1">
                            {{ $stats['system_load'] }}
                        </div>
                    </div>
                    <span class="text-[9px] text-gray-400">Média (1, 5, 15 min)</span>
                </div>

                <div class="space-y-2 p-4 bg-gray-50 dark:bg-gray-850 rounded-xl border border-gray-100 dark:border-gray-800 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-semibold">Último Registro</span>
                        <div class="text-xs font-semibold text-gray-800 dark:text-gray-200 mt-1">
                            {{ $stats['last_climate_record'] }}
                        </div>
                    </div>
                    <span class="text-[9px] text-gray-400">TimescaleDB Hypertable</span>
                </div>
            </div>
        </x-filament::section>

        <!-- DaisyUI Console Mockup for Logs -->
        <x-filament::section>
            <x-slot name="heading">Saída do Console do Queue Worker (logs/queue-worker.log)</x-slot>
            
            <div class="mockup-code text-xs border border-gray-200 dark:border-gray-800 shadow-md bg-zinc-950 text-emerald-400 w-full mt-2 select-all leading-relaxed">
                @if (empty($stats['worker_logs']) || $stats['worker_logs'] == 'Nenhum log de execução encontrado.')
                    <pre data-prefix="$"><code>php artisan queue:work --verbose</code></pre>
                    <pre data-prefix=">" class="text-warning"><code>[System] Aguardando sinal de execução do worker...</code></pre>
                @else
                    @foreach (explode("\n", trim($stats['worker_logs'])) as $line)
                        <pre data-prefix=">"><code>{{ $line }}</code></pre>
                    @endforeach
                @endif
            </div>
            <span class="text-[10px] text-gray-400 mt-2 block">Atualizado automaticamente a cada 5 segundos pelo Livewire.</span>
        </x-filament::section>

        <!-- Native Filament Table for Activity Logs -->
        <x-filament::section>
            <x-slot name="heading">Histórico de Atividade Recente (Spatie Log)</x-slot>
            
            <div class="mt-4">
                {{ $this->table }}
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
