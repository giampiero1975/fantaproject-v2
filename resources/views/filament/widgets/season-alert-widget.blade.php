<x-filament-widgets::widget>
    <div x-data="{ open: false }" 
         style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); border-left: 4px solid {{ $this->metrics['header_color'] }}; margin-bottom: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff;">
        
        <!-- Header Bar Clickabile (Accordion Trigger) -->
        <div @click="open = !open" 
             style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; cursor: pointer; user-select: none;">
            
            <div style="display:flex; align-items:center; gap:16px;">
                <span style="font-weight:700; color:#1e293b; font-size:0.875rem; display: flex; align-items: center; gap: 8px;">
                    📅 1. Gestione Stagioni
                </span>
                <div style="display:flex; gap:8px; align-items:center;">
                    <!-- Status Badge (Season State) -->
                    @php
                        $scColor = $this->metrics['season_state_color'] ?? 'gray';
                        $scHex   = $scColor === 'success' ? '#047857' : ($scColor === 'warning' ? '#b45309' : ($scColor === 'danger' ? '#b91c1c' : '#475569'));
                        $scBg    = $scColor === 'success' ? '#ecfdf5' : ($scColor === 'warning' ? '#fffbeb' : ($scColor === 'danger' ? '#fef2f2' : '#f8fafc'));
                        $scBord  = $scColor === 'success' ? '#a7f3d0' : ($scColor === 'warning' ? '#fde68a' : ($scColor === 'danger' ? '#fecaca' : '#e2e8f0'));
                    @endphp
                    <span style="font-size:0.75rem; font-weight:700; color: {{ $scHex }}; background: {{ $scBg }}; border:1px solid {{ $scBord }}; padding:2px 10px; border-radius:12px; text-transform: uppercase;">
                        {{ $this->metrics['season_state_label'] }}
                    </span>

                    <!-- Status Badge (System Health) -->
                    <span style="font-size:0.75rem; font-weight:700; color: {{ $this->metrics['color'] === 'emerald' ? '#047857' : ($this->metrics['color'] === 'rose' ? '#b91c1c' : '#b45309') }}; background: {{ $this->metrics['color'] === 'emerald' ? '#ecfdf5' : ($this->metrics['color'] === 'rose' ? '#fef2f2' : '#fffbeb') }}; border:1px solid {{ $this->metrics['color'] === 'emerald' ? '#a7f3d0' : ($this->metrics['color'] === 'rose' ? '#fecaca' : '#fde68a') }}; padding:2px 10px; border-radius:12px; text-transform: uppercase;">
                        {{ $this->metrics['status'] }}
                    </span>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Dettagli</span>
                <!-- Chevron Rotante Alpine.js -->
                <span :style="open ? 'transform: rotate(180deg);' : 'transform: rotate(0deg);'" 
                      style="display: inline-block; transition: transform 0.2s ease; font-size: 0.75rem; color: #64748b; font-weight: bold;">
                    ▼
                </span>
            </div>
        </div>

        <!-- Accordion Content (Visibile SOLO quando x-show="open" è vero) -->
        <div x-show="open" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             style="display: none; padding: 16px; background-color: #ffffff; border-top: 1px solid #f1f5f9;">
            
            <!-- BI Info Panel (4 colonne pulite con CSS Grid nativo) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                
                <!-- CARD 1: LOOKBACK HISTORY -->
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">LOOKBACK HISTORY</p>
                        <p style="font-size: 18px; font-weight: 900; color: #1e293b; margin: 0;">{{ $this->metrics['lookback'] }} Anni (+1 Corrente)</p>
                    </div>
                    <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.4;">
                        Anni storici definiti nel sistema per il calcolo delle medie e delle statistiche.
                    </p>
                </div>

                <!-- CARD 2: COVERAGE SEASONS -->
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">COVERAGE SEASONS</p>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                            <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $this->metrics['progress'] }}%</p>
                            <div style="flex-grow: 1; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                <div style="height: 100%; background: linear-gradient(to right, #34d399, #0d9488); width: {{ $this->metrics['progress'] }}%; border-radius: 4px;"></div>
                            </div>
                        </div>
                    </div>
                    <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.4;">
                        Percentuale di stagioni fisicamente presenti nel database rispetto al lookback impostato.
                    </p>
                </div>

                <!-- CARD 3: TIMELINE GAPS -->
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">TIMELINE GAPS</p>
                        <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px;">
                            @if(count($this->metrics['missing']) > 0)
                                @foreach($this->metrics['missing'] as $year)
                                    <span style="padding: 2px 6px; background-color: #0f172a; color: #ffffff; font-size: 10px; font-weight: 700; border-radius: 4px;">{{ $year }}</span>
                                @endforeach
                            @elseif($this->metrics['progress'] == 100)
                                <p style="font-size: 16px; font-weight: 900; color: #10b981; margin: 0; display: flex; align-items: center; gap: 4px;">
                                    🛡️ SECURED
                                </p>
                            @else
                                <span style="font-size: 11px; font-weight: 700; color: #f59e0b; text-transform: uppercase;">INCOMPLETO</span>
                            @endif
                        </div>
                    </div>
                    <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.4;">
                        Identifica gli anni cronologici che mancano nel database per completare il perimetro di lavoro.
                    </p>
                </div>

                <!-- CARD 4: STATO CONNESSIONE API -->
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">STATO CONNESSIONE API</p>
                        @if($this->metrics['api_active'])
                            <p style="font-size: 18px; font-weight: 900; color: #10b981; margin: 4px 0 0 0; display: flex; align-items: center; gap: 6px;">
                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: #10b981; box-shadow: 0 0 8px #10b981;"></span>
                                ACTIVE
                            </p>
                        @else
                            <p style="font-size: 18px; font-weight: 900; color: #ef4444; margin: 4px 0 0 0; display: flex; align-items: center; gap: 6px;">
                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: #ef4444; box-shadow: 0 0 8px #ef4444;"></span>
                                DOWN
                            </p>
                        @endif
                    </div>
                    <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.4;">
                        Verifica la validità della API Key e la raggiungibilità del server Football-Data.org.
                    </p>
                </div>

            </div>


        </div>

    </div>
</x-filament-widgets::widget>
