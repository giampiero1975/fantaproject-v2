<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * TierEvolutionTest
 *
 * Evoluzione di TierSimulationTest: aggiunge 3 nuove variabili contestuali
 * per ridurre l'inerzia storica delle squadre in decadenza.
 *
 * BASE CONFIG (dalla simulazione precedente):
 *   CF=0.95, lookback=5, divisore=17, pesi=[7,4,2,1,1], modT1T2=1.0, modT4T5=1.1
 *
 * NUOVE FEATURE da testare in grid search:
 *
 *  1. TREND PENALTY (Malus Decadenza)
 *     Se le ultime 3 posizioni mostrano un trend peggiorativo (pos_N > pos_N-1 > pos_N-2),
 *     la posizione_media viene moltiplicata per un malus.
 *     Range: 1.00 (disabilitato) → 1.40 (penalità forte), step 0.05
 *
 *  2. POINTS MODE (Punti vs Posizione)
 *     Sostituisce la posizione con un punteggio normalizzato basato sui punti fatti.
 *     Formula: score = (1 - pts/max_pts) * num_squadre_lega
 *     (più punti = score più basso = proiettato più in alto)
 *     Flag: true/false
 *
 *  3. ROSTER PROXY (Forza Rosa Sintetica)
 *     Proxy statistico della forza rosa basato sulla media punti degli ultimi N anni.
 *     Non abbiamo initial_quotation nel DB, quindi usiamo un indicatore derivato:
 *     roster_strength = media(points/played_games) delle ultime 3 stagioni.
 *     Il moltiplicatore agisce sulla posizione_media finale:
 *     score_final = score * max(0.80, 1 - (roster_strength - 1.5) * 0.10)
 *     Flag: true/false
 *
 * OUTPUT: storage/logs/Tiers/evolution_results.json
 *
 * Totale combinazioni: 9 (trend_penalties) × 2 (points_mode) × 2 (roster_proxy) = 36
 */
class TierEvolutionTest extends TestCase
{
    // ── Base config ottimale dalla simulazione precedente ─────────────────────
    private const BASE_CF           = 0.95;
    private const BASE_LOOKBACK     = 5;
    private const BASE_DIVISOR      = 17;
    private const BASE_WEIGHTS      = [7, 4, 2, 1, 1];
    private const BASE_MOD_T1T2     = 1.0;
    private const BASE_MOD_T4T5     = 1.1;

    // ── Parametri di evoluzione ───────────────────────────────────────────────
    // Trend Penalty: range 1.00 (off) → 1.40 (forte malus), step 0.05
    private const TREND_PENALTIES   = [1.00, 1.05, 1.10, 1.15, 1.20, 1.25, 1.30, 1.35, 1.40];

    // ── Dataset e dati pre-caricati ───────────────────────────────────────────
    private array $groundTruth      = [];
    private array $historicalData   = [];
    private array $focusTeams       = ['Hellas Verona FC', 'Udinese Calcio', 'Como 1907', 'Parma Calcio 1913', 'Genoa CFC'];
    private int   $currentYear      = 2024;

    // ── Costanti lega ─────────────────────────────────────────────────────────
    private const SERIE_A_MAX_PTS   = 38 * 3;  // 114 (vittoria ogni partita)
    private const SERIE_A_TEAMS     = 20;

    // =========================================================================
    // SETUP — connessione al DB reale
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $envFile = base_path('.env');
        $envVars = [];
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim(explode('#', $val, 2)[0]);
                    $val = trim($val, "'\"");
                    $envVars[$key] = $val;
                }
            }
        }

        config([
            'database.default'                     => 'mysql',
            'database.connections.mysql.host'      => $envVars['DB_HOST']     ?? '127.0.0.1',
            'database.connections.mysql.port'      => $envVars['DB_PORT']     ?? '3306',
            'database.connections.mysql.database'  => $envVars['DB_DATABASE'] ?? 'fantaproject-v2',
            'database.connections.mysql.username'  => $envVars['DB_USERNAME'] ?? 'root',
            'database.connections.mysql.password'  => $envVars['DB_PASSWORD'] ?? '',
            'database.connections.mysql.charset'   => 'utf8mb4',
            'database.connections.mysql.collation' => 'utf8mb4_unicode_ci',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    // =========================================================================
    // TEST PRINCIPALE
    // =========================================================================

    public function test_evolve_tier_with_contextual_variables(): void
    {
        $this->loadGroundTruth();
        $this->assertNotEmpty($this->groundTruth, 'Nessun dato di verità trovato per season_year=2024.');
        $this->preloadHistoricalData();

        $totalCombos = count(self::TREND_PENALTIES) * 2 * 2;

        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🧬  TIER EVOLUTION — {$totalCombos} combinazioni (Trend×Points×Roster)\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo '  Base: CF=' . self::BASE_CF . ' | LB=' . self::BASE_LOOKBACK;
        echo ' | Div=' . self::BASE_DIVISOR . ' | modT1T2=' . self::BASE_MOD_T1T2;
        echo ' | modT4T5=' . self::BASE_MOD_T4T5 . "\n";
        echo '  Squadre verità: ' . count($this->groundTruth) . "\n\n";

        // ── Baseline (best config precedente, nessuna nuova feature) ──────────
        $baseline = $this->runEvolution(
            trendPenalty: 1.00,
            usePointsMode: false,
            useRosterProxy: false
        );
        echo "  📊 Baseline (nessuna feature evoluta): MAE={$baseline['mae']} | ";
        echo "Affinity={$baseline['affinity_pct']}%\n\n";

        // ── Grid Search ───────────────────────────────────────────────────────
        $fullMatrix = [];
        $bestConfig = $baseline;
        $bestMAE    = $baseline['mae'];
        $idx        = 0;

        foreach (self::TREND_PENALTIES as $tp) {
            foreach ([false, true] as $points) {
                foreach ([false, true] as $roster) {
                    $idx++;
                    $result = $this->runEvolution(
                        trendPenalty:   $tp,
                        usePointsMode:  $points,
                        useRosterProxy: $roster
                    );

                    $fullMatrix[] = $result;

                    if ($result['mae'] < $bestMAE) {
                        $bestMAE    = $result['mae'];
                        $bestConfig = $result;
                    }
                }
            }
        }

        // ── Stampa risultati per squadre focus ────────────────────────────────
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🏆  BEST CONFIG EVOLUTA\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $p = $bestConfig['params'];
        echo "  Trend Penalty       : {$p['trend_penalty']}x" . ($p['trend_penalty'] == 1.0 ? ' (off)' : '') . "\n";
        echo "  Points Mode         : " . ($p['use_points_mode'] ? 'SÌ (pts/giocate)' : 'NO (posizione)') . "\n";
        echo "  Roster Proxy        : " . ($p['use_roster_proxy'] ? 'SÌ (pts-per-game proxy)' : 'NO') . "\n";
        echo "  MAE                 : {$bestConfig['mae']} (baseline: {$baseline['mae']})\n";
        echo "  Affinity |Δ|≤3      : {$bestConfig['affinity_pct']}%\n";
        echo "  Affinity |Δ|≤2      : {$bestConfig['affinity_pct_2']}%\n";
        echo "  Δ rispetto baseline : " . round($bestConfig['mae'] - $baseline['mae'], 4) . "\n\n";

        // ── Focus teams ───────────────────────────────────────────────────────
        echo "📍 Squadre focus (Best Config Evoluta):\n";
        $focusResults = [];
        foreach ($this->focusTeams as $name) {
            $r = $this->findTeamResult($bestConfig['team_results'], $name);
            if ($r) {
                $delta = $r['projected_rank'] - $r['real_position'];
                $sign  = $delta >= 0 ? '+' : '';
                echo sprintf(
                    "  %-28s  Reale:%2d  Proj:%2d  Δ:%s%d\n",
                    $r['team_name'], $r['real_position'], $r['projected_rank'], $sign, $delta
                );
                $focusResults[$name] = $r;
            }
        }
        echo "\n";

        // ── Domande specifiche dell'utente ────────────────────────────────────
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "❓ RISPOSTE ALLE DOMANDE SPECIFICHE\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        // La migliore config per Verona (Δ < 2)
        $bestVerona = $this->findBestForTeam($fullMatrix, 'Hellas Verona', maxDelta: 2);
        if ($bestVerona) {
            $tv = $this->findTeamResult($bestVerona['team_results'], 'Hellas Verona');
            echo "  ✅ Verona Δ≤2: RAGGIUNTO con Trend={$bestVerona['params']['trend_penalty']}x";
            echo " | Points=" . ($bestVerona['params']['use_points_mode'] ? 'SÌ' : 'NO');
            echo " | Roster=" . ($bestVerona['params']['use_roster_proxy'] ? 'SÌ' : 'NO');
            echo " → Δ={$tv['delta']} (Real:{$tv['real_position']} Proj:{$tv['projected_rank']})\n";
        } else {
            $bestVeronaResult = $this->findBestForTeamMinDelta($fullMatrix, 'Hellas Verona');
            echo "  ❌ Verona Δ≤2: NON raggiunto. MAE minimo per Verona = {$bestVeronaResult['min_delta']} pos.\n";
            echo "     Miglior config per Verona: Trend={$bestVeronaResult['params']['trend_penalty']}x";
            echo " | Points=" . ($bestVeronaResult['params']['use_points_mode'] ? 'SÌ' : 'NO') . "\n";
        }

        // La migliore config per Udinese (Δ < 3)
        $bestUdinese = $this->findBestForTeam($fullMatrix, 'Udinese', maxDelta: 3);
        if ($bestUdinese) {
            $tu = $this->findTeamResult($bestUdinese['team_results'], 'Udinese');
            echo "  ✅ Udinese Δ≤3: RAGGIUNTO con Trend={$bestUdinese['params']['trend_penalty']}x";
            echo " | Points=" . ($bestUdinese['params']['use_points_mode'] ? 'SÌ' : 'NO');
            echo " | Roster=" . ($bestUdinese['params']['use_roster_proxy'] ? 'SÌ' : 'NO');
            echo " → Δ={$tu['delta']} (Real:{$tu['real_position']} Proj:{$tu['projected_rank']})\n";
        } else {
            $bestUdineseResult = $this->findBestForTeamMinDelta($fullMatrix, 'Udinese');
            echo "  ❌ Udinese Δ≤3: NON raggiunto. Δ minimo = {$bestUdineseResult['min_delta']}\n";
            echo "     Miglior config: Trend={$bestUdineseResult['params']['trend_penalty']}x";
            echo " | Points=" . ($bestUdineseResult['params']['use_points_mode'] ? 'SÌ' : 'NO') . "\n";
        }

        // Affinity sopra 88%?
        $maxAffinity    = max(array_column($fullMatrix, 'affinity_pct'));
        $bestAffinityR  = array_values(array_filter($fullMatrix, fn($r) => $r['affinity_pct'] >= 88.0));
        if (!empty($bestAffinityR)) {
            $br = $bestAffinityR[0];
            echo "  ✅ Affinity >88%: RAGGIUNTO al {$br['affinity_pct']}% con";
            echo " Trend={$br['params']['trend_penalty']}x";
            echo " | Points=" . ($br['params']['use_points_mode'] ? 'SÌ' : 'NO');
            echo " | Roster=" . ($br['params']['use_roster_proxy'] ? 'SÌ' : 'NO') . "\n";
        } else {
            echo "  ❌ Affinity >88%: NON raggiunta. Massima ottenuta: {$maxAffinity}%\n";
        }

        echo "\n";

        // ── Tabella miglioramenti per trend penalty ────────────────────────────
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📈 IMPATTO DEL TREND PENALTY (Points=OFF, Roster=OFF cheat sheet)\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo sprintf("  %-8s  %-8s  %-10s  %-10s  %-10s  %-10s\n",
            'Penalty', 'MAE', 'Affinity≤3', 'Affinity≤2', 'Verona Δ', 'Udinese Δ');
        foreach ($fullMatrix as $r) {
            if ($r['params']['use_points_mode'] || $r['params']['use_roster_proxy']) {
                continue;
            }
            $vr = $this->findTeamResult($r['team_results'], 'Hellas Verona');
            $ur = $this->findTeamResult($r['team_results'], 'Udinese');
            echo sprintf("  %-8s  %-8s  %-10s  %-10s  %-10s  %-10s\n",
                $r['params']['trend_penalty'] . 'x',
                $r['mae'],
                $r['affinity_pct'] . '%',
                $r['affinity_pct_2'] . '%',
                $vr ? $vr['delta'] : 'n/d',
                $ur ? $ur['delta'] : 'n/d'
            );
        }
        echo "\n";

        // ── Salva JSON ────────────────────────────────────────────────────────
        $outputPath = storage_path('logs/Tiers/evolution_results.json');
        $this->ensureDirectory($outputPath);

        usort($fullMatrix, fn($a, $b) => $a['mae'] <=> $b['mae']);

        $output = [
            'generated_at'       => now()->toIso8601String(),
            'total_combinations' => $totalCombos,
            'baseline'           => [
                'params'         => $baseline['params'],
                'mae'            => $baseline['mae'],
                'affinity_pct'   => $baseline['affinity_pct'],
                'affinity_pct_2' => $baseline['affinity_pct_2'],
                'team_results'   => $baseline['team_results'],
            ],
            'best_config'        => [
                'params'         => $bestConfig['params'],
                'mae'            => $bestConfig['mae'],
                'affinity_pct'   => $bestConfig['affinity_pct'],
                'affinity_pct_2' => $bestConfig['affinity_pct_2'],
                'team_results'   => $bestConfig['team_results'],
            ],
            'target_answers'     => [
                'verona_delta_le_2'    => !empty($bestVerona),
                'udinese_delta_le_3'   => !empty($bestUdinese),
                'affinity_above_88pct' => !empty($bestAffinityR),
                'max_affinity_pct'     => $maxAffinity,
            ],
            'full_matrix'        => array_map(fn($r) => [
                'params'         => $r['params'],
                'mae'            => $r['mae'],
                'affinity_pct'   => $r['affinity_pct'],
                'affinity_pct_2' => $r['affinity_pct_2'],
                'focus_teams'    => array_map(
                    fn($name) => $this->findTeamResult($r['team_results'], explode(' ', $name)[0]),
                    $this->focusTeams
                ),
            ], $fullMatrix),
        ];

        file_put_contents($outputPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "✅ Risultati salvati in: {$outputPath}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $this->assertTrue(true);
    }

    // =========================================================================
    // LOGICA DI SIMULAZIONE EVOLUTA
    // =========================================================================

    /**
     * Esegue una singola simulazione con le 3 nuove feature contestuali.
     *
     * @param float $trendPenalty   Moltiplicatore se trend peggiorativo (1.00 = off)
     * @param bool  $usePointsMode  Se true, usa pts normalizzati invece della posizione
     * @param bool  $useRosterProxy Se true, corregge con proxy forza rosa (pts-per-game)
     */
    private function runEvolution(
        float $trendPenalty,
        bool  $usePointsMode,
        bool  $useRosterProxy
    ): array {
        $weights    = self::BASE_WEIGHTS;
        $lookback   = self::BASE_LOOKBACK;
        $cf         = self::BASE_CF;
        $divisor    = self::BASE_DIVISOR;
        $modT1T2    = self::BASE_MOD_T1T2;
        $modT4T5    = self::BASE_MOD_T4T5;
        $year       = $this->currentYear;

        $seasons = [];
        for ($i = 0; $i < $lookback; $i++) {
            $seasons[] = $year - $i;
        }

        $teamScores = [];

        foreach ($this->historicalData as $teamId => $yearMap) {
            $weightedSum  = 0.0;
            $effectiveSum = 0;
            $posHistory   = []; // ultime posizioni (per trend)
            $ptsHistory   = []; // punti storici (per roster proxy)

            foreach ($seasons as $idx => $season) {
                if (!isset($yearMap[$season])) {
                    continue;
                }
                $s = $yearMap[$season];
                if ($s->position <= 0) {
                    continue;
                }

                $w        = $weights[$idx];
                $isSerieA = ($s->league_name === 'Serie A');

                // ── Calcolo score base ──────────────────────────────────────
                if ($usePointsMode && $s->points > 0 && $s->played_games > 0) {
                    // Score normalizzato: (1 - pts_ratio) * 20
                    // punti massimi teorici per partite giocate: played_games * 3
                    $maxPts     = $s->played_games * 3;
                    $ptsRatio   = min(1.0, $s->points / $maxPts);
                    $baseScore  = (1.0 - $ptsRatio) * self::SERIE_A_TEAMS;

                    // Per le squadre in Serie B: scalatura aggiuntiva
                    if (!$isSerieA) {
                        $serieB_scaler = $cf * ($divisor / 10.0);
                        $baseScore    *= $serieB_scaler;
                    }
                } else {
                    // Modalità posizione (default / fallback)
                    if ($isSerieA) {
                        $baseScore = $s->position;
                    } else {
                        $serieB_offset = $cf * ($divisor / 10.0);
                        $baseScore     = $s->position * $serieB_offset;
                    }
                }

                $weightedSum  += $baseScore * $w;
                $effectiveSum += $w;

                // Raccolta dati per feature aggiuntive
                if ($isSerieA && $s->position > 0) {
                    $posHistory[] = $s->position; // dalla più recente
                }
                if ($s->points > 0 && $s->played_games > 0) {
                    $ptsHistory[] = $s->points / $s->played_games; // pts per game
                }
            }

            if ($effectiveSum === 0) {
                continue;
            }

            $avgScore = $weightedSum / $effectiveSum;

            // ── FEATURE 1: Trend Penalty ────────────────────────────────────
            // Controlla le ultime 3 posizioni Serie A: se peggiorano costantemente,
            // applica il malus (aumenta il punteggio → proiettato più in basso)
            if ($trendPenalty > 1.00 && count($posHistory) >= 3) {
                // posHistory[0] = più recente, posHistory[1] = anno prima, ecc.
                $p0 = $posHistory[0]; // 2024
                $p1 = $posHistory[1]; // 2023
                $p2 = $posHistory[2]; // 2022
                // Trend peggiorativo: ogni anno peggiora rispetto al precedente
                if ($p0 > $p1 && $p1 > $p2) {
                    $avgScore *= $trendPenalty;
                }
            }

            // ── FEATURE 2: Roster Proxy (pts-per-game delle ultime 3 stagioni) ─
            // Una squadra con media alta di pts/game ha una rosa più forte.
            // Riduce il punteggio (→ proiettata più in alto) proporzionalmente.
            if ($useRosterProxy && count($ptsHistory) >= 2) {
                $rosterPPG = array_sum(array_slice($ptsHistory, 0, 3)) / min(count($ptsHistory), 3);
                // PPG tipico: ~1.3-1.8 per squadre medio-alte, 0.7-1.1 per le basse
                // Correzione: squadra con PPG alto è penalizzata meno dall'errore
                // Correzione: score =  score * (2.0 - ppg_normalized)
                // ppg_normalized = min(1.5, max(0.5, ppg)) / 1.0  → range 0.5-1.5
                $ppgNorm  = min(1.5, max(0.5, $rosterPPG));
                // Bonus per le forti (ppgNorm> 1.5), malus per le deboli (ppgNorm<0.7)
                $rosterFactor = 2.0 - $ppgNorm; // 0.5–1.5 → factor 1.5–0.5
                $rosterFactor = max(0.75, min(1.25, $rosterFactor)); // clamp
                $avgScore    *= $rosterFactor;
            }

            // ── Modulatori T1-T2 / T4-T5 (mantenuti dalla base config) ────────
            $tier = $this->assignTier($avgScore);
            if ($tier <= 2) {
                $avgScore /= $modT1T2;
            } elseif ($tier >= 4) {
                $avgScore *= $modT4T5;
            }

            $teamScores[$teamId] = $avgScore;
        }

        // ── Ranking proiettato ────────────────────────────────────────────────
        asort($teamScores);
        $projectedRank = [];
        $rank = 1;
        foreach ($teamScores as $tid => $_) {
            $projectedRank[$tid] = $rank++;
        }

        // ── Calcolo errori ────────────────────────────────────────────────────
        $errors      = [];
        $teamResults = [];

        foreach ($this->groundTruth as $truth) {
            $tid = $truth['team_id'];
            if (!isset($projectedRank[$tid])) {
                continue;
            }

            $delta    = abs($projectedRank[$tid] - $truth['real_position']);
            $errors[] = $delta;

            $teamResults[] = [
                'team_id'        => $tid,
                'team_name'      => $truth['team_name'],
                'real_position'  => $truth['real_position'],
                'projected_rank' => $projectedRank[$tid],
                'delta'          => $delta,
                'signed_delta'   => $projectedRank[$tid] - $truth['real_position'],
            ];
        }

        $mae          = count($errors) > 0 ? round(array_sum($errors) / count($errors), 4) : 999.0;
        $affinityPct  = $this->computeAffinity($errors, 3);
        $affinityPct2 = $this->computeAffinity($errors, 2);

        return [
            'params' => [
                'trend_penalty'    => round($trendPenalty, 2),
                'use_points_mode'  => $usePointsMode,
                'use_roster_proxy' => $useRosterProxy,
                // base params (riferimento)
                'base_cf'          => self::BASE_CF,
                'base_lookback'    => self::BASE_LOOKBACK,
                'base_divisor'     => self::BASE_DIVISOR,
                'base_mod_t1t2'    => self::BASE_MOD_T1T2,
                'base_mod_t4t5'    => self::BASE_MOD_T4T5,
            ],
            'mae'            => $mae,
            'affinity_pct'   => $affinityPct,
            'affinity_pct_2' => $affinityPct2,
            'team_results'   => $teamResults,
        ];
    }

    // =========================================================================
    // METODI HELPER
    // =========================================================================

    private function loadGroundTruth(): void
    {
        $rows = DB::table('team_historical_standings as ths')
            ->join('teams as t', 't.id', '=', 'ths.team_id')
            ->where('ths.season_year', 2024)
            ->where('ths.league_name', 'Serie A')
            ->where('t.serie_a_team', 1)
            ->where('ths.position', '>', 0)
            ->orderBy('ths.position')
            ->get(['ths.team_id', 't.name as team_name', 'ths.position as real_position']);

        foreach ($rows as $row) {
            $this->groundTruth[] = [
                'team_id'       => $row->team_id,
                'team_name'     => $row->team_name,
                'real_position' => $row->real_position,
            ];
        }
    }

    /**
     * Pre-carica i dati storici con anche `points` e `played_games`
     * necessari per Points Mode e Roster Proxy.
     */
    private function preloadHistoricalData(): void
    {
        $teamIds = DB::table('teams')->where('serie_a_team', 1)->pluck('id')->toArray();

        $standings = DB::table('team_historical_standings')
            ->whereIn('team_id', $teamIds)
            ->whereBetween('season_year', [2020, 2024])
            ->get(['team_id', 'season_year', 'league_name', 'position', 'points', 'played_games']);

        foreach ($standings as $s) {
            $this->historicalData[$s->team_id][$s->season_year] = $s;
        }
    }

    private function assignTier(float $avgPosition): int
    {
        return match (true) {
            $avgPosition <= 5.5  => 1,
            $avgPosition <= 9.5  => 2,
            $avgPosition <= 13.5 => 3,
            $avgPosition <= 17.5 => 4,
            default              => 5,
        };
    }

    private function computeAffinity(array $errors, int $maxDelta): float
    {
        if (empty($errors)) {
            return 0.0;
        }
        $inRange = count(array_filter($errors, fn($e) => $e <= $maxDelta));
        return round(($inRange / count($errors)) * 100, 1);
    }

    private function findTeamResult(array $teamResults, string $nameFragment): ?array
    {
        $fragment = strtolower($nameFragment);
        foreach ($teamResults as $r) {
            if (str_contains(strtolower($r['team_name']), $fragment)) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Trova la prima config nella matrice in cui il team ha delta <= maxDelta.
     */
    private function findBestForTeam(array $matrix, string $nameFragment, int $maxDelta): ?array
    {
        foreach ($matrix as $r) {
            $tr = $this->findTeamResult($r['team_results'], $nameFragment);
            if ($tr && $tr['delta'] <= $maxDelta) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Trova la config con delta minimo per un dato team.
     */
    private function findBestForTeamMinDelta(array $matrix, string $nameFragment): array
    {
        $best = ['min_delta' => 999, 'params' => []];
        foreach ($matrix as $r) {
            $tr = $this->findTeamResult($r['team_results'], $nameFragment);
            if ($tr && $tr['delta'] < $best['min_delta']) {
                $best = ['min_delta' => $tr['delta'], 'params' => $r['params']];
            }
        }
        return $best;
    }

    private function ensureDirectory(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
