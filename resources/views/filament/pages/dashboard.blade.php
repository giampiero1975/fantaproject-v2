<x-filament-panels::page>
    <div class="space-y-3">
        @php
            $currentYear  = (int) date('Y');
            $targetSeason = $currentYear - 1;
            $seasonLabel  = $targetSeason . '/' . substr((string)$currentYear, 2);

            // ── Stato step (Ricalibrato su Sidebar) ─────────────────────────
            // Step 1: Stagione
            $s1_ok = $currentSeasonModel && $lookbackStatus['is_ready'];
            $s1_status = $s1_ok ? 'ok' : (($currentSeasonModel || $lookbackStatus['ready_count'] > 0) ? 'partial' : 'missing');

            // Step 2: Squadre (ex s1)
            $s2_ok = ($teamTotal >= 20 && $teamWithApi >= 20 && !$fbrefIncomplete);
            $s2_status = !$currentSeasonModel ? 'blocked' : ($s2_ok ? 'ok' : ($teamTotal > 0 ? 'partial' : 'missing'));

            // Step 3: Storico (ex s2)
            $s3_ok = $standingCount >= $standingTarget;
            $s3_status = !$s2_ok ? 'blocked' : ($s3_ok ? 'ok' : ($standingCount > 0 ? 'partial' : 'missing'));

            // Step 4: Tier (ex s3)
            $s4_ok = $teamWithTier >= 20;
            $s4_status = !$s3_ok ? 'blocked' : ($s4_ok ? 'ok' : ($teamWithTier > 0 ? 'partial' : 'missing'));

            // Step 5: Listone (ex s4)
            $s5_ok = $playerFanta >= 400;
            $s5_status = !$s4_ok ? 'blocked' : ($s5_ok ? 'ok' : ($playerFanta > 0 ? 'partial' : 'missing'));

            // Step 6: Calciatori (Sidebar 6)
            $s6_ok = $playerFanta >= 400; // O un altro criterio di salute
            $s6_status = !$s5_ok ? 'blocked' : ($s6_ok ? 'ok' : 'partial');

            // Step 7: Sync (Sidebar 7, ex Step 6)
            $s7_ok = $pct >= 90;
            $s7_status = !$s6_ok ? 'blocked' : ($s7_ok ? 'ok' : ($pct > 0 ? 'partial' : 'missing'));

            $tierSummary = collect($tierDist)->map(fn($c,$t) => "T{$t}: {$c}")->implode(' · ');
        @endphp


        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 1 — GESTIONE STAGIONI                                       --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @livewire('season-alert-widget')

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 2 — Squadre (Anagrafica & API)                               --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php 
            $th = \App\Helpers\StepHelper::stepTheme($s2_status);
            $s2_status_label = 'VUOTO';
            $s2_badge_color = 'rose';
            if ($s2_status === 'ok') {
                $s2_status_label = 'COMPLETO';
                $s2_badge_color = 'emerald';
            } elseif ($s2_status === 'partial') {
                $s2_status_label = 'PARZIALE';
                $s2_badge_color = 'amber';
            }
        @endphp
        <div x-data="{ open: false }" 
             style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }} margin-bottom: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff;">
            
            <!-- Header Bar Clickabile (Accordion Trigger) -->
            <div @click="open = !open" 
                 style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; {{ $th['header_style'] }} cursor: pointer; user-select: none;">
                
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1f2937; font-size:0.875rem; display: flex; align-items: center; gap: 8px;">
                        {{ $th['icon'] }} 2. Squadre
                    </span>
                    <!-- Status Badge (Point 1) -->
                    <span style="font-size:0.75rem; font-weight:700; color: {{ $s2_badge_color === 'emerald' ? '#047857' : ($s2_badge_color === 'rose' ? '#b91c1c' : '#b45309') }}; background: {{ $s2_badge_color === 'emerald' ? '#ecfdf5' : ($s2_badge_color === 'rose' ? '#fef2f2' : '#fffbeb') }}; border:1px solid {{ $s2_badge_color === 'emerald' ? '#a7f3d0' : ($s2_badge_color === 'rose' ? '#fecaca' : '#fde68a') }}; padding:2px 10px; border-radius:12px; text-transform: uppercase;">
                        {{ $s2_status_label }}
                    </span>
                </div>

                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Dettagli</span>
                    <span :style="open ? 'transform: rotate(180deg);' : 'transform: rotate(0deg);'" 
                          style="display: inline-block; transition: transform 0.2s ease; font-size: 0.75rem; color: #64748b; font-weight: bold;">
                        ▼
                    </span>
                </div>
            </div>

            <!-- Accordion Content -->
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 style="display: none; padding: 16px; background-color: #ffffff; border-top: 1px solid #f1f5f9;">
                
                <!-- 3 Colonne Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                    
                    <!-- CARD 1: SQUADRE PARTECIPANTI -->
                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left;">
                        <div>
                            <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">SQUADRE PARTECIPANTI</p>
                            <p style="font-size: 18px; font-weight: 900; color: #1e293b; margin: 0;">{{ $teamTotal }} / 20 Squadre</p>
                        </div>
                        <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.4;">
                            Club di Serie A registrati a database per la stagione calcistica corrente.
                        </p>
                    </div>

                    <!-- CARD 2: MAPPATURA API -->
                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left;">
                        <div>
                            <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">MAPPATURA API</p>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                @php $apiPct = $teamTotal > 0 ? round(($teamWithApi / $teamTotal) * 100) : 0; @endphp
                                <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $apiPct }}%</p>
                                <div style="flex-grow: 1; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="height: 100%; background: linear-gradient(to right, #3b82f6, #2563eb); width: {{ $apiPct }}%; border-radius: 4px;"></div>
                                </div>
                            </div>
                        </div>
                        <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.4;">
                            Squadre correttamente allineate all'ID univoco di API-Football per il caricamento delle rose.
                        </p>
                    </div>

                    <!-- CARD 3: ALLINEAMENTO FBREF (Unificato con progress bar) -->
                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left;">
                        <div>
                            <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">ALLINEAMENTO FBREF</p>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $fbrefPct }}%</p>
                                <div style="flex-grow: 1; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="height: 100%; background: linear-gradient(to right, #10b981, #059669); width: {{ $fbrefPct }}%; border-radius: 4px;"></div>
                                </div>
                            </div>
                        </div>
                        <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.4;">
                            Mappatura dell'ID e dello slug FBref delle squadre per lo scraping delle statistiche di rendimento.
                        </p>
                    </div>

                </div>

                <!-- Call to Action -->
                <div style="display: flex; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                    <a href="{{ route('filament.admin.resources.teams.index') }}" 
                       style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #3b82f6; color: #ffffff; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2); transition: background-color 0.2s;"
                       onmouseover="this.style.backgroundColor='#2563eb'"
                       onmouseout="this.style.backgroundColor='#3b82f6'">
                        Gestisci Squadre →
                    </a>
                </div>

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 3 — Storico Classifiche                                     --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php 
            $th = \App\Helpers\StepHelper::stepTheme($s3_status);
            $s3_status_label = 'VUOTO';
            $s3_badge_color = 'rose';
            if ($s3_status === 'ok') {
                $s3_status_label = 'COMPLETO';
                $s3_badge_color = 'emerald';
            } elseif ($s3_status === 'partial') {
                $s3_status_label = 'PARZIALE';
                $s3_badge_color = 'amber';
            } elseif ($s3_status === 'blocked') {
                $s3_status_label = 'BLOCCATO';
                $s3_badge_color = 'slate';
            }
        @endphp
        <div x-data="{ open: false }" 
             style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }} margin-bottom: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff;">
            
            <!-- Header Bar Clickabile (Accordion Trigger) -->
            <div @click="open = !open" 
                 style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; {{ $th['header_style'] }} cursor: pointer; user-select: none;">
                
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1f2937; font-size:0.875rem; display: flex; align-items: center; gap: 8px;">
                        {{ $th['icon'] }} 3. Storico Classifiche
                    </span>
                    <!-- Status Badge -->
                    <span style="font-size:0.75rem; font-weight:700; color: {{ $s3_badge_color === 'emerald' ? '#047857' : ($s3_badge_color === 'rose' ? '#b91c1c' : ($s3_badge_color === 'slate' ? '#475569' : '#b45309')) }}; background: {{ $s3_badge_color === 'emerald' ? '#ecfdf5' : ($s3_badge_color === 'rose' ? '#fef2f2' : ($s3_badge_color === 'slate' ? '#f1f5f9' : '#fffbeb')) }}; border:1px solid {{ $s3_badge_color === 'emerald' ? '#a7f3d0' : ($s3_badge_color === 'rose' ? '#fecaca' : ($s3_badge_color === 'slate' ? '#cbd5e1' : '#fde68a')) }}; padding:2px 10px; border-radius:12px; text-transform: uppercase;">
                        {{ $s3_status_label }}
                    </span>
                </div>

                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Dettagli</span>
                    <span :style="open ? 'transform: rotate(180deg);' : 'transform: rotate(0deg);'" 
                          style="display: inline-block; transition: transform 0.2s ease; font-size: 0.75rem; color: #64748b; font-weight: bold;">
                        ▼
                    </span>
                </div>
            </div>

            <!-- Accordion Content -->
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 style="display: none; padding: 16px; background-color: #ffffff; border-top: 1px solid #f1f5f9;">
                
                @if($s3_status === 'blocked')
                    <div style="padding: 16px; background-color: #f8fafc; border-radius: 8px; text-align: center; color: #64748b; font-size: 14px;">
                        Devi completare lo step precedente (2. Squadre) prima di sbloccare questo step.
                    </div>
                @else
                    <!-- Colonne Cards -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        
                        <!-- CARD 1: COPERTURA STORICA -->
                        <div x-tooltip="'Rapporto crudo tra i piazzamenti storici effettivamente salvati a database e il target ideale calcolato per tutte le squadre in base agli anni di lookback.'"
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">COPERTURA STORICA</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    @php $standingPct = $standingTarget > 0 ? min(100, round(($standingCount / $standingTarget) * 100)) : 0; @endphp
                                    <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $standingCount }} / {{ $standingTarget }}</p>
                                </div>
                                <div style="margin-top: 8px; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="height: 100%; background: linear-gradient(to right, #f59e0b, #d97706); width: {{ $standingPct }}%; border-radius: 4px;"></div>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                Piazzamenti in classifica scaricati per coprire le ultime 4 stagioni storiche delle squadre partecipanti (obbligatorio per il calcolo dei tier).
                            </p>
                        </div>

                        <!-- CARD 2: SQUADRE PERFETTE -->
                        <div x-tooltip="'Numero di squadre dell\'attuale Serie A che possiedono uno storico perfetto e ininterrotto per tutti gli anni richiesti.'" 
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">SQUADRE PERFETTE</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    @php 
                                        $activeCount = $teamTotal > 0 ? $teamTotal : 20;
                                        $perfPct = $activeCount > 0 ? min(100, round(($perfectTeams / $activeCount) * 100)) : 0; 
                                    @endphp
                                    <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $perfectTeams }} / {{ $activeCount }}</p>
                                </div>
                                <div style="margin-top: 8px; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="height: 100%; background: linear-gradient(to right, #10b981, #059669); width: {{ $perfPct }}%; border-radius: 4px;"></div>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                Squadre attive con lo storico completo al 100%. Se inferiore al totale, verifica la matrice per le neopromosse.
                            </p>
                        </div>

                        <!-- CARD 3: ULTIMO AGGIORNAMENTO -->
                        <div x-tooltip="'Data e ora esatta dell\'ultimo salvataggio di un record storico proveniente dallo scraper FBref.'" 
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">ULTIMO AGGIORNAMENTO</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    <p style="font-size: 20px; font-weight: 900; color: #1e293b; margin: 0;">
                                        {{ $s3LastUpdate ? \Carbon\Carbon::parse($s3LastUpdate)->format('d/m/Y H:i') : 'Mai effettuato' }}
                                    </p>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                Indica l'ultima sincronizzazione dati storici completata con successo a database.
                            </p>
                        </div>

                    </div>

                    <!-- Call to Action -->
                    <div style="display: flex; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                        <a href="{{ route('filament.admin.resources.team-historical-standings.coverage') }}" 
                           style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #3b82f6; color: #ffffff; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2); transition: background-color 0.2s;"
                           onmouseover="this.style.backgroundColor='#2563eb'"
                           onmouseout="this.style.backgroundColor='#3b82f6'">
                            Gestisci Copertura →
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 4 — Calcolo Tier Squadre                                    --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = \App\Helpers\StepHelper::stepTheme($s4_status) @endphp
        <div x-data="{ open: false }" style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div @click="open = !open" style="cursor:pointer; display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 4. Calcolo Tier Squadre
                </span>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                    <svg x-bind:style="open ? 'transform: rotate(180deg);' : ''" style="transition: transform 0.2s; width:20px; height:20px; color:#6b7280;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
            
            <div x-show="open" x-collapse>
                @if($s4_status === 'blocked')
                    <div style="padding:16px; font-size:0.875rem; color:#6b7280; background-color:#f9fafb;">
                        Devi completare lo step precedente (3. Storico Classifiche) prima di sbloccare questo step.
                    </div>
                @else
                    <div style="padding: 16px; background-color: #ffffff; border-top: 1px solid #f1f5f9;">
                        <!-- Colonne Cards -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                            
                            <!-- CARD 1: TIER ASSEGNATI -->
                            <div x-tooltip="'Indica il numero di squadre attive a cui il motore ha assegnato correttamente un Tier 1-5.'"
                                 style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                                <div>
                                    <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">TIER ASSEGNATI</p>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                        @php 
                                            $activeCount = $teamTotal > 0 ? $teamTotal : 20;
                                            $tierPct = $activeCount > 0 ? min(100, round(($teamWithTier / $activeCount) * 100)) : 0; 
                                        @endphp
                                        <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $teamWithTier }} / {{ $activeCount }}</p>
                                    </div>
                                    <div style="margin-top: 8px; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                        <div style="height: 100%; background: linear-gradient(to right, #6366f1, #4f46e5); width: {{ $tierPct }}%; border-radius: 4px;"></div>
                                    </div>
                                </div>
                                <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                    Tutte le squadre devono avere un Tier assegnato prima di poter estrarre proiezioni.
                                </p>
                            </div>

                            <!-- CARD 2: BILANCIAMENTO LEGA -->
                            <div x-tooltip="'Suddivisione della lega per macro-aree: Top (Tier 1-2), Mid (Tier 3), Flop (Tier 4-5).'"
                                 style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                                <div>
                                    <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">BILANCIAMENTO LEGA</p>
                                    @php
                                        $topCount = ($tierDist[1] ?? 0) + ($tierDist[2] ?? 0);
                                        $midCount = ($tierDist[3] ?? 0);
                                        $flopCount = ($tierDist[4] ?? 0) + ($tierDist[5] ?? 0);
                                    @endphp
                                    <div style="display: flex; gap: 4px; margin-top: 8px;">
                                        <div style="flex: {{ $topCount > 0 ? $topCount : 1 }}; background-color: #3b82f6; height: 6px; border-radius: 3px;"></div>
                                        <div style="flex: {{ $midCount > 0 ? $midCount : 1 }}; background-color: #94a3b8; height: 6px; border-radius: 3px;"></div>
                                        <div style="flex: {{ $flopCount > 0 ? $flopCount : 1 }}; background-color: #f97316; height: 6px; border-radius: 3px;"></div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 12px; font-weight: 700;">
                                        <span style="color: #3b82f6;">{{ $topCount }} Top</span>
                                        <span style="color: #94a3b8;">{{ $midCount }} Mid</span>
                                        <span style="color: #f97316;">{{ $flopCount }} Flop</span>
                                    </div>
                                </div>
                                <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                    Ripartizione competitiva del campionato attuale in base alla forza delle squadre.
                                </p>
                            </div>

                            <!-- CARD 3: SQUADRE D'ELITE -->
                            <div x-tooltip="'Numero di squadre assegnate in assoluto alla fascia più alta di punteggio (Tier 1).'"
                                 style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                                <div>
                                    <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">SQUADRE D'ELITE</p>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                        <p style="font-size: 24px; font-weight: 900; color: #eab308; margin: 0;">{{ $tierDist[1] ?? 0 }}</p>
                                        <svg style="width:24px; height:24px; color:#eab308;" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                    </div>
                                </div>
                                <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                    I top club schiacciasassi. I loro giocatori riceveranno i bonus moltiplicatori massimi nelle proiezioni.
                                </p>
                            </div>

                        </div>

                        <!-- Call to Action -->
                        <div style="display: flex; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                            <a href="{{ \App\Filament\Pages\TierSquadre::getUrl() }}" 
                               style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #3b82f6; color: #ffffff; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2); transition: background-color 0.2s;"
                               onmouseover="this.style.backgroundColor='#2563eb'"
                               onmouseout="this.style.backgroundColor='#3b82f6'">
                                Dashboard Tier Squadre →
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 5 — Importazione Listone Quotazioni                         --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = \App\Helpers\StepHelper::stepTheme($s5_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 5. Importazione Listone Quotazioni
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.pages.importa-listone') }}" class="text-xs font-bold text-blue-600 hover:underline">Vai a Import</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 6 — Calciatori                                              --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = \App\Helpers\StepHelper::stepTheme($s6_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 6. Calciatori
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.resources.players.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Vedi Calciatori</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
            @if($s6_status !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Anagrafica</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Totale records: <strong>{{ number_format($playerTotal) }}</strong>
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 7 — Sync API-Football                                       --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = \App\Helpers\StepHelper::stepTheme($s7_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 7. Sync API-Football
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="{{ route('filament.admin.pages.sync-api-football') }}" class="text-xs font-bold text-blue-600 hover:underline">Vai a Sync</a>
                    <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
