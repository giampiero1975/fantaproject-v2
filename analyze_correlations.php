<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// --- 1. RECUPERO DATI (Last 4 Seasons) ---
$records = DB::table('team_historical_standings')
    ->where('season_year', '>=', 2021)
    ->get(['won', 'draw', 'lost', 'goals_for', 'goals_against', 'position']);

if ($records->isEmpty()) {
    die("Nessun dato trovato per l'analisi.\n");
}

$stats = ['WON', 'DRAW', 'LOST', 'GF', 'GS'];
$data = [
    'WON' => $records->pluck('won')->toArray(),
    'DRAW' => $records->pluck('draw')->toArray(),
    'LOST' => $records->pluck('lost')->toArray(),
    'GF' => $records->pluck('goals_for')->toArray(),
    'GS' => $records->pluck('goals_against')->toArray(),
    'POS' => $records->pluck('position')->toArray(),
];

// --- 2. FUNZIONE CORRELAZIONE DI PEARSON ---
function correlation($x, $y) {
    if (count($x) !== count($y)) return 0;
    $n = count($x);
    if ($n === 0) return 0;
    $meanX = array_sum($x) / $n;
    $meanY = array_sum($y) / $n;
    
    $num = 0; $den1 = 0; $den2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $dx = $x[$i] - $meanX;
        $dy = $y[$i] - $meanY;
        $num += ($dx * $dy);
        $den1 += ($dx * $dx);
        $den2 += ($dy * $dy);
    }
    return ($den1 * $den2) == 0 ? 0 : $num / sqrt($den1 * $den2);
}

// --- 3. CALCOLO MATRICE ---
$matrix = [];
foreach ($stats as $s) {
    $matrix[$s] = correlation($data[$s], $data['POS']);
}

// --- 4. OUTPUT HEATMAP TESTUALE ---
echo "\nMATRICE DI CORRELAZIONE vs POSIZIONE FINALE (2021-2024)\n";
echo "Nota: Posizione BASSA (1,2,3) = Squadra forte | Posizione ALTA (18,19,20) = Squadra debole\n";
echo str_repeat("-", 60) . "\n";
echo sprintf("%-10s | %-10s | %s\n", "PARAM", "CORR", "PESO VISIVO (Intensità)");
echo str_repeat("-", 60) . "\n";

foreach ($matrix as $param => $val) {
    $absVal = abs($val);
    $barLen = (int)($absVal * 30);
    // Se corr è negativa (W, GF), all'aumentare del parametro DIMINUISCE la posizione (migliora).
    $symbol = ($val < 0) ? "█" : "▓"; 
    
    $intensity = "";
    if ($absVal > 0.8) $intensity = "[MOLTO ALTO]";
    elseif ($absVal > 0.6) $intensity = "[ALTO]";
    elseif ($absVal > 0.4) $intensity = "[MEDIO]";
    else $intensity = "[BASSO]";

    echo sprintf("%-10s | %7.4f    | %-30s %s\n", 
        $param, 
        $val, 
        str_repeat($symbol, $barLen),
        $intensity
    );
}
echo str_repeat("-", 60) . "\n";
echo "Interpretazione:\n";
echo "- WON/GF (█): Correlazione negativa molto forte (Più vinci/segni, più la posizione scende verso 1).\n";
echo "- LOST/GS (▓): Correlazione positiva molto forte (Più perdi/subisci, più la posizione sale verso 20).\n";
