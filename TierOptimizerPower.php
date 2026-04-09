<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Team;

// --- 1. GROUND TRUTH (Target desiderati dallo Screenshot Giornata 31) ---
$targets = [
    'Inter' => 1, 'Napoli' => 1, 'Milan' => 1, 'Como' => 1, 'Juventus' => 1,
    'Roma' => 2, 'Atalanta' => 2, 'Bologna' => 2, 'Lazio' => 2,
    'Sassuolo' => 3, 'Udinese' => 3, 'Torino' => 3, 'Parma' => 3, 'Genoa' => 3,
    'Fiorentina' => 4, 'Cagliari' => 4, 'Cremonese' => 4, 'Lecce' => 4,
    'Verona' => 5, 'Pisa' => 5
];

// Caricamento dati squadre e classifiche storiche (Last 4)
$teamsData = [];
$lastConcluded = 2024;
$lookback = 4;

foreach ($targets as $name => $target) {
    $team = Team::where('name', 'LIKE', "%$name%")->first();
    if (!$team) continue;

    $standings = [];
    for ($i = 0; $i < $lookback; $i++) {
        $year = $lastConcluded - $i;
        $standings[$year] = DB::table('team_historical_standings')
            ->where('team_id', $team->id)
            ->where('season_year', $year)
            ->first();
    }
    $teamsData[] = ['team' => $team, 'target' => $target, 'standings' => $standings];
}

// --- 2. FUNZIONE CALCOLO POWER FACTOR ---
function calculatePowerFactorScore($s) {
    if (!$s) return 20.0; // Penalità assenza dati

    $isB = ($s->league_name === 'Serie B');
    
    // Correttori Serie B
    $pts = $isB ? $s->points * 0.95 : $s->points;
    $gf  = $isB ? $s->goals_for * 0.80 : $s->goals_for;
    $gs  = $isB ? $s->goals_against * 1.10 : $s->goals_against;

    // Normalizzazione (Scala 0-20)
    $ptsComp = (1 - ($pts / 114)) * 20;
    $gfComp  = max(0, (1 - ($gf / 90)) * 20);
    $gsComp  = min(20, ($gs / 75) * 20);

    // Pesi Power Factor: 60% Punti, 25% GF, 15% GS
    return ($ptsComp * 0.60) + ($gfComp * 0.25) + ($gsComp * 0.15);
}

// --- 3. PARAMETERS RANGES (Grid Search) ---
$weights = [12, 4, 2, 1]; // Pesi stagioni (Hyper-Reactive)
$divisor = 19; // Somma pesi
$t1_range = range(6.0, 7.5, 0.2);
$t2_range = range(8.0, 9.5, 0.2);
$t3_range = range(11.5, 12.8, 0.2);
$t4_range = range(13.0, 14.5, 0.2);

$bestAccuracy = 0;
$bestConfig = null;

echo "Avvio simulazione POWER FACTOR su " . count($teamsData) . " squadre...\n";

foreach ($t1_range as $t1) {
    foreach ($t2_range as $t2) {
        if ($t2 <= $t1) continue;
        foreach ($t3_range as $t3) {
            if ($t3 <= $t2) continue;
            foreach ($t4_range as $t4) {
                if ($t4 <= $t3) continue;

                $matches = 0;
                foreach ($teamsData as $data) {
                    $totalScore = 0;
                    $i = 0;
                    foreach ($data['standings'] as $year => $s) {
                        $w = $weights[$i];
                        $totalScore += (calculatePowerFactorScore($s) * $w);
                        $i++;
                    }
                    $finalScore = $totalScore / $divisor;

                    $predTier = 5;
                    if ($finalScore <= $t1) $predTier = 1;
                    elseif ($finalScore <= $t2) $predTier = 2;
                    elseif ($finalScore <= $t3) $predTier = 3;
                    elseif ($finalScore <= $t4) $predTier = 4;

                    if ($predTier === $data['target']) $matches++;
                }

                $acc = ($matches / count($teamsData)) * 100;
                if ($acc >= $bestAccuracy) {
                    $bestAccuracy = $acc;
                    $bestConfig = [$t1, $t2, $t3, $t4];
                }
            }
        }
    }
}

// --- 4. OUTPUT RISULTATI ---
echo "\n✅ RISULTATO OTTIMIZZAZIONE POWER FACTOR\n";
echo str_repeat("-", 40) . "\n";
echo "Accuracy Massima: " . number_format($bestAccuracy, 2) . "% (" . ($bestAccuracy / 100 * count($teamsData)) . "/" . count($teamsData) . ")\n";
echo "Soglie Ottimali: T1: {$bestConfig[0]} | T2: {$bestConfig[1]} | T3: {$bestConfig[2]} | T4: {$bestConfig[3]}\n\n";

// Dettaglio finale con la miglior config
echo sprintf("%-25s | %-10s | %-15s | %s\n", "SQUADRA", "TARGET", "PREVISTO", "SCORE");
echo str_repeat("-", 70) . "\n";
foreach ($teamsData as $data) {
    $totalScore = 0;
    $i = 0;
    foreach ($data['standings'] as $year => $s) {
        $totalScore += (calculatePowerFactorScore($s) * $weights[$i]);
        $i++;
    }
    $finalScore = $totalScore / $divisor;
    
    $predTier = 5;
    if ($finalScore <= $bestConfig[0]) $predTier = 1;
    elseif ($finalScore <= $bestConfig[1]) $predTier = 2;
    elseif ($finalScore <= $bestConfig[2]) $predTier = 3;
    elseif ($finalScore <= $bestConfig[3]) $predTier = 4;

    $matchMark = ($predTier === $data['target']) ? "✅" : "❌";
    echo sprintf("%-25s | T%-9d | T%-14d | %5.2f %s\n", 
        $data['team']->name, $data['target'], $predTier, $finalScore, $matchMark);
}
