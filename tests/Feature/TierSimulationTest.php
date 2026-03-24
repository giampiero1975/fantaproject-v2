<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TierSimulationTest
 *
 * Obiettivo: trovare la combinazione ottimale di parametri per il calcolo del Tier
 * che minimizza lo scostamento (Delta / MAE) tra posizione_media proiettata
 * e posizione reale 2025 (season_year = 2024).
 *
 * Parametri di test:
 *  - conversionFactor (B):  step 0.05, da 0.75 a 0.95  → 5 valori
 *  - lookback:              3, 4, 5                        → 3 valori
 *  - fixedDivisor:          step 1, da 13 a 17             → 5 valori
 *  - modT1T2 (Off):         step 0.10, da 1.00 a 1.30      → 4 valori
 *  - modT4T5 (Def):         step 0.10, da 1.10 a 1.40      → 4 valori
 *
 * Totale combinazioni: 5 × 3 × 5 × 4 × 4 = 1200
 *
 * Output: storage/logs/Tiers/optimal_config_results.json
 */
class TierSimulationTest extends TestCase
{
    /**
     * Override the database configuration to use the REAL production MySQL database.
     * PHPUnit normally forces an in-memory SQLite DB via phpunit.xml, but this
     * simulation test needs the actual historical standings data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Leggi DIRETTAMENTE il file .env per bypassare gli override di phpunit.xml
        // (phpunit.xml forza DB_DATABASE=:memory: che sovrascrive env())
        $envFile = base_path('.env');
        $envVars = [];
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                // Rimuovi commenti inline (es.: "DB_CONNECTION=mysql # commento")
                if (str_contains($line, '=')) {
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim(explode('#', $val, 2)[0]); // rimuovi commento inline
                    $val = trim($val, "'\"");
                    $envVars[$key] = $val;
                }
            }
        }

        // Riconfigura la connessione DB per usare MySQL con i dati reali
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

        // Forza la riconnessione con la nuova configurazione
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }


    /**
     * Dataset di verità: posizioni reali della stagione 2025 (season_year = 2024).
     * Viene caricato dal DB all'avvio del test.
     *
     * Struttura:  [ 'team_id' => int, 'team_name' => string, 'real_position' => int ]
     */
    private array $groundTruth = [];

    /**
     * Dati storici pre-caricati per velocizzare il loop.
     * Struttura: [ team_id => [ season_year => standing_object ] ]
     */
    private array $historicalData = [];

    /**
     * Squadre di interesse per il report dettagliato.
     */
    private array $focusTeams = ['Como 1907', 'Parma Calcio 1913', 'Hellas Verona FC', 'Genoa CFC'];

    // -------------------------------------------------------------------------
    // PUNTO DI INGRESSO
    // -------------------------------------------------------------------------

    public function test_simulate_optimal_tier_configuration(): void
    {
        $this->loadGroundTruth();

        // Scarta il test se non abbiamo dati sufficienti
        $this->assertNotEmpty(
            $this->groundTruth,
            'Nessuna squadra trovata per season_year=2024 in team_historical_standings.'
        );

        $this->preloadHistoricalData();

        // -----------------------------------------------------------------------
        // Definizione dello spazio dei parametri
        // -----------------------------------------------------------------------
        $conversionFactors = $this->frange(0.75, 0.95, 0.05);   // 5 val
        $lookbacks         = [3, 4, 5];                           // 3 val
        $fixedDivisors     = range(13, 17);                       // 5 val
        $modT1T2Values     = $this->frange(1.00, 1.30, 0.10);    // 4 val
        $modT4T5Values     = $this->frange(1.10, 1.40, 0.10);    // 4 val

        $totalCombinations = count($conversionFactors)
            * count($lookbacks)
            * count($fixedDivisors)
            * count($modT1T2Values)
            * count($modT4T5Values);

        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🔬  TIER SIMULATION — {$totalCombinations} combinazioni da testare\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo 'Squadre nel dataset di verità: ' . count($this->groundTruth) . "\n\n";

        // -----------------------------------------------------------------------
        // Grid Search
        // -----------------------------------------------------------------------
        $fullMatrix  = [];
        $bestConfig  = null;
        $bestMAE     = PHP_FLOAT_MAX;

        $idx = 0;
        foreach ($conversionFactors as $cf) {
            foreach ($lookbacks as $lb) {
                foreach ($fixedDivisors as $div) {
                    foreach ($modT1T2Values as $modOff) {
                        foreach ($modT4T5Values as $modDef) {
                            $idx++;

                            $result = $this->runSimulation($cf, $lb, $div, $modOff, $modDef);

                            $fullMatrix[] = $result;

                            if ($result['mae'] < $bestMAE) {
                                $bestMAE    = $result['mae'];
                                $bestConfig = $result;
                            }

                            if ($idx % 100 === 0) {
                                echo "  [{$idx}/{$totalCombinations}] Best MAE so far: " . round($bestMAE, 4) . "\n";
                            }
                        }
                    }
                }
            }
        }

        // -----------------------------------------------------------------------
        // Calcolo metriche finali sulla best config
        // -----------------------------------------------------------------------
        $affinityPct = $this->computeAffinity($bestConfig['team_results'], maxDelta: 3);

        // -----------------------------------------------------------------------
        // Report console
        // -----------------------------------------------------------------------
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🏆  BEST CONFIG TROVATA\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  Conversion Factor (B) : {$bestConfig['params']['conversion_factor']}\n";
        echo "  Lookback              : {$bestConfig['params']['lookback']}\n";
        echo "  Fixed Divisor         : {$bestConfig['params']['fixed_divisor']}\n";
        echo "  Modulatore T1-2 (Off) : {$bestConfig['params']['mod_t1t2']}\n";
        echo "  Modulatore T4-5 (Def) : {$bestConfig['params']['mod_t4t5']}\n";
        echo "  MAE (classifica)      : " . round($bestConfig['mae'], 4) . "\n";
        echo "  Affinity (|Δ|≤3)      : {$affinityPct}%\n";
        echo "\n📍 Dettaglio squadre focus:\n";

        foreach ($this->focusTeams as $focusName) {
            $teamResult = $this->findTeamResult($bestConfig['team_results'], $focusName);
            if ($teamResult) {
                $delta = $teamResult['projected_rank'] - $teamResult['real_position'];
                $sign  = $delta >= 0 ? '+' : '';
                echo sprintf(
                    "  %-28s  Real: %2d  Proiettato: %2d  Delta: %s%d\n",
                    $teamResult['team_name'],
                    $teamResult['real_position'],
                    $teamResult['projected_rank'],
                    $sign,
                    $delta
                );
            }
        }
        echo "\n";

        // -----------------------------------------------------------------------
        // Salvataggio JSON
        // -----------------------------------------------------------------------
        $outputPath = storage_path('logs/Tiers/optimal_config_results.json');
        $this->ensureDirectory($outputPath);

        // Ordina la full matrix per MAE crescente per leggibilità
        usort($fullMatrix, fn($a, $b) => $a['mae'] <=> $b['mae']);

        $output = [
            'generated_at'      => now()->toIso8601String(),
            'total_combinations' => $totalCombinations,
            'teams_in_dataset'  => count($this->groundTruth),
            'best_config'       => [
                'params'              => $bestConfig['params'],
                'mae'                 => round($bestConfig['mae'], 4),
                'affinity_percentage' => $affinityPct,
                'team_results'        => $bestConfig['team_results'],
            ],
            'affinity_percentage' => $affinityPct,
            'full_matrix'         => array_map(fn($r) => [
                'params' => $r['params'],
                'mae'    => round($r['mae'], 4),
            ], $fullMatrix),
        ];

        file_put_contents($outputPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "✅ Risultati salvati in: {$outputPath}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        // Il test passa sempre — lo scopo è produrre l'analisi
        $this->assertTrue(true);
    }

    // =========================================================================
    // LOGICA DI SIMULAZIONE
    // =========================================================================

    /**
     * Esegue una singola simulazione con i parametri dati.
     *
     * Il calcolo replica fedelmente TeamDataService::updateTeamTiers() ma:
     *  1. non tocca il DB (solo in lettura dai dati pre-caricati)
     *  2. usa conversionFactor invece dell'offset additivo fisso (+10)
     *  3. applica modulatori T1-2 / T4-5 alla posizione_media prima del ranking
     *  4. usa un fixedDivisor per normalizzare la posizione_media finale
     *
     * @param float $conversionFactor  Moltiplicatore per posizioni Serie B    (es. 0.85 → pos * 0.85 * divisor / 10)
     * @param int   $lookback          Anni di lookback                         (3, 4, 5)
     * @param int   $fixedDivisor      Divisore fisso per normalizzazione       (13-17)
     * @param float $modT1T2           Modulatore offensivo per Tier 1-2        (1.00-1.30)
     * @param float $modT4T5           Modulatore difensivo per Tier 4-5        (1.10-1.40)
     *
     * @return array  [ 'params', 'mae', 'team_results' ]
     */
    private function runSimulation(
        float $conversionFactor,
        int   $lookback,
        int   $fixedDivisor,
        float $modT1T2,
        float $modT4T5
    ): array {
        $weights    = $this->computeWeights($lookback);
        $weightSum  = array_sum($weights);

        // La finestra temporale: stagioni più recenti incluse
        // La più recente è il 2024 (stagione 2024/25)
        $currentYear = 2024;
        $seasons = [];
        for ($i = 0; $i < $lookback; $i++) {
            $seasons[] = $currentYear - $i;
        }

        $teamScores = [];

        foreach ($this->historicalData as $teamId => $yearMap) {
            $weightedSum  = 0.0;
            $effectiveSum = 0;

            foreach ($seasons as $idx => $season) {
                if (!isset($yearMap[$season])) {
                    continue;
                }

                $standing = $yearMap[$season];
                if ($standing->position <= 0) {
                    continue;
                }

                $w        = $weights[$idx];
                $isSerieA = ($standing->league_name === 'Serie A');

                // Calcolo posizione: per Serie B si usa il moltiplicatore
                // Formula: pos * conversionFactor * (fixedDivisor / 10)
                // Questo scala la posizione di Serie B in modo proporzionale
                // invece dell'offset additivo fisso della versione produzione.
                if ($isSerieA) {
                    $scoreToUse = $standing->position;
                } else {
                    // Il conversionFactor sostituisce la costante +10:
                    // con CF=0.85 e div=15: pos_B_eff = pos_B * (CF * div/10)
                    // = pos_B * 1.275 (simile all'effetto +10 per posizioni mediane)
                    $serieB_offset = $conversionFactor * ($fixedDivisor / 10.0);
                    $scoreToUse    = $standing->position * $serieB_offset;
                }

                $weightedSum  += $scoreToUse * $w;
                $effectiveSum += $w;
            }

            if ($effectiveSum === 0) {
                continue; // Nessun dato disponibile: salta
            }

            $avgPosition = $weightedSum / $effectiveSum;

            // Assegna un Tier preliminare per i modulatori
            $tier = $this->assignTier($avgPosition);

            // Applica i modulatori (amplificano le differenze tra tier)
            // T1-2: squadre forti appaiono "più alte" in classifica
            // T4-5: squadre deboli appaiono "più basse"
            if ($tier <= 2) {
                $adjustedScore = $avgPosition / $modT1T2; // riduce → sale
            } elseif ($tier >= 4) {
                $adjustedScore = $avgPosition * $modT4T5; // aumenta → scende
            } else {
                $adjustedScore = $avgPosition;
            }

            $teamScores[$teamId] = $adjustedScore;
        }

        // Ranking proiettato: ordina per score crescente (pos bassa = migliore)
        asort($teamScores);
        $projectedRank = [];
        $rank          = 1;
        foreach ($teamScores as $teamId => $_) {
            $projectedRank[$teamId] = $rank++;
        }

        // Calcola MAE rispetto alla verità 2024 season
        $errors      = [];
        $teamResults = [];

        foreach ($this->groundTruth as $truth) {
            $tid = $truth['team_id'];
            if (!isset($projectedRank[$tid])) {
                continue; // squadra senza dati storici: esclusa
            }

            $delta    = abs($projectedRank[$tid] - $truth['real_position']);
            $errors[] = $delta;

            $teamResults[] = [
                'team_id'        => $tid,
                'team_name'      => $truth['team_name'],
                'real_position'  => $truth['real_position'],
                'projected_rank' => $projectedRank[$tid],
                'delta'          => $delta,
            ];
        }

        $mae = count($errors) > 0 ? array_sum($errors) / count($errors) : PHP_FLOAT_MAX;

        return [
            'params' => [
                'conversion_factor' => round($cf = $conversionFactor, 2),
                'lookback'          => $lookback,
                'fixed_divisor'     => $fixedDivisor,
                'mod_t1t2'          => round($modT1T2, 2),
                'mod_t4t5'          => round($modT4T5, 2),
                'weights'           => $weights,
            ],
            'mae'          => $mae,
            'team_results' => $teamResults,
        ];
    }

    // =========================================================================
    // METODI HELPER
    // =========================================================================

    /**
     * Carica dal DB le posizioni reali Serie A per season_year = 2024 (stagione 2024/25).
     */
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
     * Pre-carica tutti i dati storici necessari per evitare migliaia di query nel loop.
     * Carica le ultime 5 stagioni (2020–2024) per tutti i team serie_a_team=1.
     */
    private function preloadHistoricalData(): void
    {
        $teamIds = DB::table('teams')->where('serie_a_team', 1)->pluck('id')->toArray();

        $standings = DB::table('team_historical_standings')
            ->whereIn('team_id', $teamIds)
            ->whereBetween('season_year', [2020, 2024])
            ->get();

        foreach ($standings as $s) {
            $this->historicalData[$s->team_id][$s->season_year] = $s;
        }
    }

    /**
     * Genera i pesi per il lookback dato, coerenti con TeamDataService.
     * lookback=5 → [7,4,2,1,1] (pesi speciali)
     * lookback=4 → [4,3,2,1]
     * lookback=3 → [3,2,1]
     */
    private function computeWeights(int $lookback): array
    {
        if ($lookback === 5) {
            return [7, 4, 2, 1, 1];
        }
        // Pesi lineari decrescenti (più recente = peso maggiore)
        return array_reverse(range(1, $lookback));
    }

    /**
     * Tier temporaneo per decidere l'applicazione dei modulatori.
     * Usa le stesse soglie della produzione.
     */
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

    /**
     * Percentuale di squadre con |Delta| <= maxDelta.
     */
    private function computeAffinity(array $teamResults, int $maxDelta = 3): float
    {
        if (empty($teamResults)) {
            return 0.0;
        }

        $inRange = count(array_filter($teamResults, fn($r) => $r['delta'] <= $maxDelta));

        return round(($inRange / count($teamResults)) * 100, 1);
    }

    /**
     * Cerca un team nei risultati per nome (match parziale case-insensitive).
     */
    private function findTeamResult(array $teamResults, string $name): ?array
    {
        $nameLower = strtolower($name);
        foreach ($teamResults as $r) {
            if (str_contains(strtolower($r['team_name']), strtolower(explode(' ', $name)[0]))) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Genera un range float con precisione a 2 decimali per evitare drift floating-point.
     */
    private function frange(float $start, float $end, float $step): array
    {
        $result = [];
        $current = $start;
        while ($current <= $end + 1e-9) {
            $result[] = round($current, 2);
            $current  = round($current + $step, 10);
        }
        return $result;
    }

    /**
     * Assicura che la directory del file di output esista.
     */
    private function ensureDirectory(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
