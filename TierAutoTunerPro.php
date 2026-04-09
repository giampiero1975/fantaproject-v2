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

// --- RANGES ---
$w_pts_range = range(0.48, 0.72, 0.04);
$w_gf_range  = range(0.20, 0.30, 0.02);
$cf_pts_range = range(0.76, 1.14, 0.08); // 5 step
$cf_gf_range  = range(0.64, 0.96, 0.08); // 5 step
$cf_gs_range  = range(0.88, 1.32, 0.11); // 5 step
$divs = range(16, 20, 1.0);
$weights_decay = [12, 4, 2, 1];

$topResults = [];
$maxTop = 5;

echo "Avvio GRID SEARCH MASSIVA (+/- 20%) con gestione memoria ottimizzata...\n";

foreach ($w_pts_range as $wp) {
    foreach ($w_gf_range as $wg) {
        $ws = 1.0 - $wp - $wg;
        if ($ws < 0.10 || $ws > 0.20) continue; 

        foreach ($cf_pts_range as $cfp) {
            foreach ($cf_gf_range as $cfgf) {
                foreach ($cf_gs_range as $cfgs) {
                    
                    $teamFinalScoresRaw = [];
                    foreach ($teamsData as $data) {
                        $teamWeightedScore = 0;
                        $i = 0;
                        foreach ($data['history'] as $s) {
                            if (!$s) {
                                $seasonScore = 20.0;
                            } else {
                                $isB = ($s->league_name === 'Serie B');
                                $pts_val = $isB ? ($s->points * $cfp) : $s->points;
                                $gf_val  = $isB ? ($s->goals_for * $cfgf) : $s->goals_for;
                                $gs_val  = $isB ? ($s->goals_against * $cfgs) : $s->goals_against;

                                $ptsComp = (1 - ($pts_val / 114)) * 20;
                                $gfComp  = max(0, (1 - ($gf_val / 90)) * 20);
                                $gsComp  = min(20, ($gs_val / 75) * 20);

                                $seasonScore = ($ptsComp * $wp) + ($gfComp * $wg) + ($gsComp * $ws);
                            }
                            $teamWeightedScore += ($seasonScore * $weights_decay[$i]);
                            $i++;
                        }
                        $teamFinalScoresRaw[] = ['name' => $data['name'], 'target' => $data['target'], 'raw_score' => $teamWeightedScore];
                    }

                    foreach ($divs as $div) {
                        $pScores = [];
                        foreach ($teamFinalScoresRaw as $tfs) {
                            $pScores[] = ['name' => $tfs['name'], 'target' => $tfs['target'], 'score' => $tfs['raw_score'] / $div];
                        }

                        $t1s = [6.5, 7.0, 7.5];
                        $t2s = [8.5, 9.0, 9.5];
                        $t3s = [11.5, 12.0, 12.5];
                        $t4s = [13.5, 14.0, 14.5];

                        foreach ($t1s as $t1) {
                            foreach ($t2s as $t2) {
                                foreach ($t3s as $t3) {
                                    foreach ($t4s as $t4) {
                                        $matches = 0;
                                        foreach ($pScores as $ps) {
                                            $pred = 5;
                                            if ($ps['score'] <= $t1) $pred = 1;
                                            elseif ($ps['score'] <= $t2) $pred = 2;
                                            elseif ($ps['score'] <= $t3) $pred = 3;
                                            elseif ($ps['score'] <= $t4) $pred = 4;
                                            if ($pred === $ps['target']) $matches++;
                                        }

                                        $acc = ($matches / count($targets)) * 100;
                                        
                                        // Gestione TOP 5 senza saturare memoria
                                        if (count($topResults) < $maxTop || $acc > $topResults[$maxTop-1]['acc']) {
                                            $topResults[] = [
                                                'acc' => $acc,
                                                'params' => [
                                                    'w_pts' => $wp, 'w_gf' => $wg, 'w_gs' => $ws,
                                                    'cf_pts' => $cfp, 'cf_gf' => $cfgf, 'cf_gs' => $cfgs,
                                                    'div' => $div, 't' => [$t1, $t2, $t3, $t4]
                                                ],
                                                'scores' => $pScores
                                            ];
                                            usort($topResults, function($a, $b) { return $b['acc'] <=> $a['acc']; });
                                            if (count($topResults) > $maxTop) array_pop($topResults);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

echo "\n🏆 TOP 5 CONFIGURAZIONI TROVATE (Fattore Potenza)\n";
echo str_repeat("=", 50) . "\n\n";

foreach ($topResults as $idx => $res) {
    echo "--- CONFIGURAZIONE #" . ($idx + 1) . " (Acc: " . $res['acc'] . "%) ---\n";
    echo "PESI : Pts: {$res['params']['w_pts']} | GF: {$res['params']['w_gf']} | GS: {$res['params']['w_gs']}\n";
    echo "CF B : Pts: {$res['params']['cf_pts']} | GF: {$res['params']['cf_gf']} | GS: {$res['params']['cf_gs']}\n";
    echo "DIV  : {$res['params']['div']} | SOGLIE: T1:{$res['params']['t'][0]} T2:{$res['params']['t'][1]} T3:{$res['params']['t'][2]} T4:{$res['params']['t'][3]}\n";
    
    echo sprintf("\n%-20s | %-6s | %-6s | %s\n", "SQUADRA", "TARGET", "PRED", "SCORE");
    echo str_repeat("-", 45) . "\n";
    foreach ($res['scores'] as $s) {
        if (in_array($s['name'], ['Como 1907', 'ACF Fiorentina', 'Hellas Verona FC', 'AC Pisa 1909'])) {
             $p = 5;
             if ($s['score'] <= $res['params']['t'][0]) $p = 1;
             elseif ($s['score'] <= $res['params']['t'][1]) $p = 2;
             elseif ($s['score'] <= $res['params']['t'][2]) $p = 3;
             elseif ($s['score'] <= $res['params']['t'][3]) $p = 4;
             $mark = ($p === $s['target']) ? "✅" : "❌";
             echo sprintf("%-20s | T%-5d | T%-5d | %5.2f %s\n", $s['name'], $s['target'], $p, $s['score'], $mark);
        }
    }
    echo "\n";
}
