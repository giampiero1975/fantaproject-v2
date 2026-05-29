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
        @php 
            $th = \App\Helpers\StepHelper::stepTheme($s4_status);
            $s4_status_label = 'VUOTO';
            $s4_badge_color = 'rose';
            if ($s4_status === 'ok') {
                $s4_status_label = 'COMPLETO';
                $s4_badge_color = 'emerald';
            } elseif ($s4_status === 'partial') {
                $s4_status_label = 'PARZIALE';
                $s4_badge_color = 'amber';
            } elseif ($s4_status === 'blocked') {
                $s4_status_label = 'BLOCCATO';
                $s4_badge_color = 'slate';
            }
        @endphp
        <div x-data="{ open: false }" 
             style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }} margin-bottom: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff;">
            
            <div @click="open = !open" 
                 style="cursor:pointer; display:flex; align-items:center; justify-content:space-between; padding:12px 16px; {{ $th['header_style'] }} user-select: none;">
                
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1f2937; font-size:0.875rem; display: flex; align-items: center; gap: 8px;">
                        {{ $th['icon'] }} 4. Calcolo Tier Squadre
                    </span>
                    <!-- Status Badge (Stile Pillola come Step 3) -->
                    <span style="font-size:0.75rem; font-weight:700; color: {{ $s4_badge_color === 'emerald' ? '#047857' : ($s4_badge_color === 'rose' ? '#b91c1c' : ($s4_badge_color === 'slate' ? '#475569' : '#b45309')) }}; background: {{ $s4_badge_color === 'emerald' ? '#ecfdf5' : ($s4_badge_color === 'rose' ? '#fef2f2' : ($s4_badge_color === 'slate' ? '#f1f5f9' : '#fffbeb')) }}; border:1px solid {{ $s4_badge_color === 'emerald' ? '#a7f3d0' : ($s4_badge_color === 'rose' ? '#fecaca' : ($s4_badge_color === 'slate' ? '#cbd5e1' : '#fde68a')) }}; padding:2px 10px; border-radius:12px; text-transform: uppercase;">
                        {{ $s4_status_label }}
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
            
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 style="display: none; padding: 16px; background-color: #ffffff; border-top: 1px solid #f1f5f9;">
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

                            <!-- CARD 3: DISTRIBUZIONE TIER -->
                            <div x-tooltip="'Conteggio esatto delle squadre assegnate a ciascun Tier (dal livello 1 al livello 5).'"
                                 style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                                <div>
                                    <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">DISTRIBUZIONE TIER</p>
                                    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; margin-top: 12px; text-align: center;">
                                        <div>
                                            <p style="font-size: 16px; font-weight: 900; color: #eab308; margin: 0;">{{ $tierDist[1] ?? 0 }}</p>
                                            <p style="font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">T1</p>
                                        </div>
                                        <div>
                                            <p style="font-size: 16px; font-weight: 900; color: #3b82f6; margin: 0;">{{ $tierDist[2] ?? 0 }}</p>
                                            <p style="font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">T2</p>
                                        </div>
                                        <div>
                                            <p style="font-size: 16px; font-weight: 900; color: #64748b; margin: 0;">{{ $tierDist[3] ?? 0 }}</p>
                                            <p style="font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">T3</p>
                                        </div>
                                        <div>
                                            <p style="font-size: 16px; font-weight: 900; color: #f97316; margin: 0;">{{ $tierDist[4] ?? 0 }}</p>
                                            <p style="font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">T4</p>
                                        </div>
                                        <div>
                                            <p style="font-size: 16px; font-weight: 900; color: #ef4444; margin: 0;">{{ $tierDist[5] ?? 0 }}</p>
                                            <p style="font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">T5</p>
                                        </div>
                                    </div>
                                </div>
                                <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                    Spaccato esatto dei Tier assegnati al tabellone corrente.
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
        @php 
            $th = \App\Helpers\StepHelper::stepTheme($s5_status);
            $s5_status_label = 'VUOTO';
            $s5_badge_color = 'rose';
            if ($s5_status === 'ok') {
                $s5_status_label = 'COMPLETO';
                $s5_badge_color = 'emerald';
            } elseif ($s5_status === 'partial') {
                $s5_status_label = 'PARZIALE';
                $s5_badge_color = 'amber';
            } elseif ($s5_status === 'blocked') {
                $s5_status_label = 'BLOCCATO';
                $s5_badge_color = 'slate';
            }
        @endphp
        <div x-data="{ open: false }" 
             style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }} margin-bottom: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff;">
            
            <div @click="open = !open" 
                 style="cursor:pointer; display:flex; align-items:center; justify-content:space-between; padding:12px 16px; {{ $th['header_style'] }} user-select: none;">
                
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1f2937; font-size:0.875rem; display: flex; align-items: center; gap: 8px;">
                        {{ $th['icon'] }} 5. Importazione Listone Quotazioni
                    </span>
                    <!-- Status Badge (Stile Pillola) -->
                    <span style="font-size:0.75rem; font-weight:700; color: {{ $s5_badge_color === 'emerald' ? '#047857' : ($s5_badge_color === 'rose' ? '#b91c1c' : ($s5_badge_color === 'slate' ? '#475569' : '#b45309')) }}; background: {{ $s5_badge_color === 'emerald' ? '#ecfdf5' : ($s5_badge_color === 'rose' ? '#fef2f2' : ($s5_badge_color === 'slate' ? '#f1f5f9' : '#fffbeb')) }}; border:1px solid {{ $s5_badge_color === 'emerald' ? '#a7f3d0' : ($s5_badge_color === 'rose' ? '#fecaca' : ($s5_badge_color === 'slate' ? '#cbd5e1' : '#fde68a')) }}; padding:2px 10px; border-radius:12px; text-transform: uppercase;">
                        {{ $s5_status_label }}
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
                
                @if($s5_status === 'blocked')
                    <div style="padding: 16px; background-color: #f8fafc; border-radius: 8px; text-align: center; color: #64748b; font-size: 14px;">
                        Devi completare lo step precedente (4. Calcolo Tier Squadre) prima di sbloccare questo step.
                    </div>
                @else
                    <!-- Colonne Cards -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        
                        <!-- CARD 1: GIOCATORI A LISTONE -->
                        <div x-tooltip="'Numero totale di calciatori riconosciuti e attivi nell\'attuale Listone ufficiale.'"
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">GIOCATORI A LISTONE</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    @php $fantaPct = $playerFanta >= 400 ? 100 : round(($playerFanta / 400) * 100); @endphp
                                    <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $playerFanta }}</p>
                                    @if($playerFanta >= 400)
                                        <svg style="width:20px; height:20px; color:#10b981;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </div>
                                <div style="margin-top: 8px; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="height: 100%; background: linear-gradient(to right, #3b82f6, #2563eb); width: {{ $fantaPct }}%; border-radius: 4px;"></div>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                Target minimo: 400 giocatori per considerare il roster sufficientemente coperto per l'analisi.
                            </p>
                        </div>

                        <!-- CARD 2: ORFANI -->
                        <div x-tooltip="'Giocatori presenti a Listone ma con associazione squadra fallita (es. team non in Serie A, svincolati o mismatch nome squadra).'"
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">ORFANI (SENZA SQUADRA)</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    <p style="font-size: 24px; font-weight: 900; color: {{ $playerOrphan > 0 ? '#ef4444' : '#10b981' }}; margin: 0;">{{ $playerOrphan }}</p>
                                </div>
                                <div style="margin-top: 8px; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="height: 100%; background: {{ $playerOrphan > 0 ? 'linear-gradient(to right, #ef4444, #b91c1c)' : '#10b981' }}; width: 100%; border-radius: 4px;"></div>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                Se il numero è alto, verifica che i nomi delle squadre nel Listone coincidano con quelli censiti a database.
                            </p>
                        </div>

                        <!-- CARD 3: ULTIMO IMPORT -->
                        <div x-tooltip="'Data e ora dell\'ultimo caricamento andato a buon fine del file Excel Listone.'"
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">ULTIMO CARICAMENTO EXCEL</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    <p style="font-size: 20px; font-weight: 900; color: #1e293b; margin: 0;">
                                        {{ $lastListone ? \Carbon\Carbon::parse($lastListone->created_at)->format('d/m/Y H:i') : 'Mai effettuato' }}
                                    </p>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                Sorgente ufficiale per i calcoli finanziari e ruoli Classic/Mantra (FVM, Quotazioni).
                            </p>
                        </div>

                    </div>

                    <!-- Call to Action -->
                    <div style="display: flex; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                        <a href="{{ route('filament.admin.pages.importa-listone') }}" 
                           style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #3b82f6; color: #ffffff; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2); transition: background-color 0.2s;"
                           onmouseover="this.style.backgroundColor='#2563eb'"
                           onmouseout="this.style.backgroundColor='#3b82f6'">
                            Gestisci Listone →
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 6 — Calciatori                                              --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php 
            $th = \App\Helpers\StepHelper::stepTheme($s6_status);
            $s6_status_label = 'VUOTO';
            $s6_badge_color = 'rose';
            if ($s6_status === 'ok') {
                $s6_status_label = 'COMPLETO';
                $s6_badge_color = 'emerald';
            } elseif ($s6_status === 'partial') {
                $s6_status_label = 'PARZIALE';
                $s6_badge_color = 'amber';
            } elseif ($s6_status === 'blocked') {
                $s6_status_label = 'BLOCCATO';
                $s6_badge_color = 'slate';
            }
        @endphp
        <div x-data="{ open: false }" 
             style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }} margin-bottom: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff;">
            
            <div @click="open = !open" 
                 style="cursor:pointer; display:flex; align-items:center; justify-content:space-between; padding:12px 16px; {{ $th['header_style'] }} user-select: none;">
                
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-weight:700; color:#1f2937; font-size:0.875rem; display: flex; align-items: center; gap: 8px;">
                        {{ $th['icon'] }} 6. Anagrafica Calciatori
                    </span>
                    <!-- Status Badge (Stile Pillola) -->
                    <span style="font-size:0.75rem; font-weight:700; color: {{ $s6_badge_color === 'emerald' ? '#047857' : ($s6_badge_color === 'rose' ? '#b91c1c' : ($s6_badge_color === 'slate' ? '#475569' : '#b45309')) }}; background: {{ $s6_badge_color === 'emerald' ? '#ecfdf5' : ($s6_badge_color === 'rose' ? '#fef2f2' : ($s6_badge_color === 'slate' ? '#f1f5f9' : '#fffbeb')) }}; border:1px solid {{ $s6_badge_color === 'emerald' ? '#a7f3d0' : ($s6_badge_color === 'rose' ? '#fecaca' : ($s6_badge_color === 'slate' ? '#cbd5e1' : '#fde68a')) }}; padding:2px 10px; border-radius:12px; text-transform: uppercase;">
                        {{ $s6_status_label }}
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
                
                @if($s6_status === 'blocked')
                    <div style="padding: 16px; background-color: #f8fafc; border-radius: 8px; text-align: center; color: #64748b; font-size: 14px;">
                        Devi completare lo step precedente (5. Importazione Listone Quotazioni) prima di sbloccare questo step.
                    </div>
                @else
                    <!-- Colonne Cards -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                        
                        <!-- CARD 1: CALCIATORI ATTIVI -->
                        <div x-tooltip="'Numero totale di calciatori attualmente attivi in anagrafica (esclusi quelli in soft-delete/ceduti).'"
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">CALCIATORI IN DATABASE</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    <p style="font-size: 24px; font-weight: 900; color: #1e293b; margin: 0;">{{ $playerTotal }}</p>
                                    @if($playerTotal >= 400)
                                        <svg style="width:20px; height:20px; color:#10b981;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                Tutte le schede giocatore create o mantenute attive dagli import.
                            </p>
                        </div>

                        <!-- CARD 2: MAPPATI FANTAPIATTAFORMA -->
                        <div x-tooltip="'Giocatori agganciati correttamente agli ID del provider fantacalcio (Listone ufficiale).'"
                             style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; text-align: left; cursor: help;">
                            <div>
                                <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.05em;">ID FANTAPIATTAFORMA</p>
                                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                    @php $fantaPct = $playerTotal > 0 ? round(($playerFanta / $playerTotal) * 100) : 0; @endphp
                                    <p style="font-size: 24px; font-weight: 900; color: {{ $fantaPct < 90 ? '#ef4444' : '#10b981' }}; margin: 0;">{{ $playerFanta }}</p>
                                    <span style="font-size: 12px; font-weight: 700; color: #64748b;">({{ $fantaPct }}%)</span>
                                </div>
                                <div style="margin-top: 8px; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                    <div style="height: 100%; background: {{ $fantaPct < 90 ? 'linear-gradient(to right, #ef4444, #b91c1c)' : 'linear-gradient(to right, #10b981, #059669)' }}; width: {{ $fantaPct }}%; border-radius: 4px;"></div>
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.4;">
                                La percentuale dovrebbe tendere al 100%. Gli orfani di ID non riceveranno voti/quote.
                            </p>
                        </div>

                    </div>

                    <!-- Call to Action -->
                    <div style="display: flex; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                        <a href="{{ route('filament.admin.resources.players.index') }}" 
                           style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #3b82f6; color: #ffffff; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2); transition: background-color 0.2s;"
                           onmouseover="this.style.backgroundColor='#2563eb'"
                           onmouseout="this.style.backgroundColor='#3b82f6'">
                            Gestisci Anagrafiche →
                        </a>
                    </div>
                @endif
            </div>
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
