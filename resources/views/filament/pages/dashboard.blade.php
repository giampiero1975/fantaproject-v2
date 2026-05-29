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
        @php $th = \App\Helpers\StepHelper::stepTheme($s3_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 3. Storico Classifiche
                </span>
                <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
            </div>
            @if($s3_status !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Dati</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Piazzamenti:
                            <strong style="{{ $standingCount >= $standingTarget ? 'color:#198754' : ($standingCount > 0 ? 'color:#d97706' : 'color:#dc2626') }}">
                                {{ $standingCount }} / {{ $standingTarget }}
                            </strong>
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STEP 4 — Calcolo Tier Squadre                                    --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $th = \App\Helpers\StepHelper::stepTheme($s4_status) @endphp
        <div style="width:100%; border-radius:8px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); {{ $th['border_style'] }}">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; {{ $th['header_style'] }}">
                <span style="font-weight:700; color:#1f2937; font-size:0.875rem;">
                    {{ $th['icon'] }} 4. Calcolo Tier Squadre
                </span>
                <span style="{{ $th['badge_style'] }}">{{ $th['badge_label'] }}</span>
            </div>
            @if($s4_status !== 'blocked')
                <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-white dark:bg-gray-900">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Dati</p>
                        <p class="text-gray-700 dark:text-gray-200">
                            Tier calcolati:
                            <strong style="{{ $teamWithTier >= 20 ? 'color:#198754' : 'color:#dc2626' }}">
                                {{ $teamWithTier }} / 20
                            </strong>
                        </p>
                    </div>
                </div>
            @endif
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
