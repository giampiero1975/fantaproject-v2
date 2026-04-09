<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Team;

// --- DATASET GROUND TRUTH ---
$targets = [
    'Inter' => 1, 'Napoli' => 1, 'Milan' => 1, 'Como' => 1, 'Juventus' => 1,
    'Roma' => 2, 'Atalanta' => 2, 'Bologna' => 2, 'Lazio' => 2,
    'Sassuolo' => 3, 'Udinese' => 3, 'Torino' => 3, 'Parma' => 3, 'Genoa' => 3,
    'Fiorentina' => 4, 'Cagliari' => 4, 'Cremonese' => 4, 'Lecce' => 4,
    'Verona' => 5, 'Pisa' => 5
];

$teamsData = [];
$lookback = 4;
$lastConcluded = 2024;
foreach ($targets as $name => $target) {
    $team = Team::where('name', 'LIKE', "%$name%")->first();
    if (!$team) continue;
    $history = [];
    for ($i = 0; $i < $lookback; $i++) {
        $year = $lastConcluded - $i;
        $history[$year] = DB::table('team_historical_standings')
            ->where('team_id', $team->id)
            ->where('season_year', $year)
            ->first();
    }
    $teamsData[] = ['name' => $team->name, 'target' => $target, 'history' => $history];
}

// --- FUNZIONI DI CALCOLO ---
function calculateSeasonScore($s, $cfp = 1.0, $cfgf = 1.0, $cfgs = 1.0) {
    if (!$s) return 20.0;
    
    $isB = ($s->league_name === 'Serie B');
    $pts = $isB ? ($s->points * $cfp) : $s->points;
    $gf  = $isB ? ($s->goals_for * $cfgf) : $s->goals_for;
    $gs  = $isB ? ($s->goals_against * $cfgs) : $s->goals_against;

    $ptsComp = (1 - ($pts / 114)) * 20;
    $gfComp  = max(0, (1 - ($gf / 90)) * 20);
    $gsComp  = min(20, ($gs / 75) * 20);

    return ($ptsComp * 0.60) + ($gfComp * 0.25) + ($gsComp * 0.15);
}

// --- GRID SEARCH IBRIDA ---
$results = []; // MANTENIAMO SOLO TOP 5
$maxTop = 5;

// Range di oscillazione sulle soglie finali
$t1s = range(6.5, 8.5, 0.5);
$t2s = range(8.5, 10.5, 0.5);
$t3s = range(11.0, 13.0, 0.5);
$t4s = range(13.0, 15.0, 0.5);

echo "Avvio SIMULAZIONE IBRIDA (70/30)...\n";

foreach ($t1s as $t1) {
    foreach ($t2s as $t2) {
        foreach ($t3s as $t3) {
            foreach ($t4s as $t4) {
                if ($t2 <= $t1 || $t3 <= $t2 || $t4 <= $t3) continue;

                $matches = 0;
                $currentScores = [];

                foreach ($teamsData as $data) {
                    // 1. CALCOLO HISTORICO (4 ANNI - RIGIDO)
                    $hScoreRaw = 0;
                    $hWeights = [12, 4, 2, 1];
                    $j = 0;
                    foreach ($data['history'] as $s) {
                        // Malus Serie B Gold Standard
                        $hScoreRaw += (calculateSeasonScore($s, 0.95, 0.80, 1.10) * $hWeights[$j]);
                        $j++;
                    }
                    $s_hist = $hScoreRaw / 19;

                    // 2. CALCOLO MOMENTUM (2 ANNI - FLUIDO)
                    $mScoreRaw = 0;
                    $mWeights = [10, 4];
                    $j = 0;
                    foreach (array_slice($data['history'], 0, 2) as $s) {
                        // NO Malus Serie B (CF = 1.0)
                        $mScoreRaw += (calculateSeasonScore($s, 1.0, 1.0, 1.0) * $mWeights[$j]);
                        $j++;
                    }
                    $s_mom = $mScoreRaw / 14;

                    // 3. FUSIONE IBRIDA
                    $finalScore = ($s_hist * 0.7) + ($s_mom * 0.3);

                    $pred = 5;
                    if ($finalScore <= $t1) $pred = 1;
                    elseif ($finalScore <= $t2) $pred = 2;
                    elseif ($finalScore <= $t3) $pred = 3;
                    elseif ($finalScore <= $t4) $pred = 4;

                    if ($pred === $data['target']) $matches++;
                    $currentScores[] = ['name' => $data['name'], 'target' => $data['target'], 'score' => $finalScore, 'pred' => $pred];
                }

                $acc = ($matches / count($targets)) * 100;

                if (count($results) < $maxTop || $acc > $results[$maxTop-1]['acc']) {
                    $results[] = [
                        'acc' => $acc,
                        't' => [$t1, $t2, $t3, $t4],
                        'data' => $currentScores
                    ];
                    usort($results, function($a, $b) { return $b['acc'] <=> $a['acc']; });
                    if (count($results) > $maxTop) array_pop($results);
                }
            }
        }
    }
}

// REPORT TOP 3
echo "\n🏆 TOP 3 CONFIGURAZIONI IBRIDE TROVATE\n";
echo str_repeat("=", 50) . "\n\n";

for ($i = 0; $i < min(3, count($results)); $i++) {
    $r = $results[$i];
    echo "--- CONFIGURAZIONE #" . ($i + 1) . " (Acc: " . $r['acc'] . "%) ---\n";
    echo "SOGLIE: T1:{$r['t'][0]} T2:{$r['t'][1]} T3:{$r['t'][2]} T4:{$r['t'][3]}\n";
    echo sprintf("\n%-20s | %-6s | %-6s | %s\n", "SQUADRA", "TARGET", "PRED", "SCORE");
    echo str_repeat("-", 45) . "\n";
    foreach ($r['data'] as $s) {
        if (in_array($s['name'], ['Como 1907', 'ACF Fiorentina', 'Hellas Verona FC', 'AC Pisa 1909'])) {
             $mark = ($s['pred'] === $s['target']) ? "✅" : "❌";
             echo sprintf("%-20s | T%-5d | T%-5d | %5.2f %s\n", $s['name'], $s['target'], $s['pred'], $s['score'], $mark);
        }
    }
    echo "\n";
}
