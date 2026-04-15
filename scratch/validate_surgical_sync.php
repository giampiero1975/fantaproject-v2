<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Models\Season;
use App\Helpers\SeasonHelper;
use Illuminate\Support\Str;

echo "🧪 AVVIO VALIDAZIONE MATCHING CHIRURGICO FBREF\n";
echo "------------------------------------------------\n";

$teamName = $argv[1] ?? 'Genoa';
$seasonYear = isset($argv[2]) ? (int)$argv[2] : 2024;

$team = Team::where('name', 'LIKE', "%$teamName%")->first();

if (!$team) {
    echo "❌ Squadra '$teamName' non trovata.\n";
    exit(1);
}

$season = Season::where('season_year', $seasonYear)->first();
if (!$season) {
    echo "❌ Stagione $seasonYear non trovata.\n";
    exit(1);
}

echo "📂 Analisi Team: {$team->name} (ID: {$team->id})\n";
echo "📅 Stagione: " . SeasonHelper::formatYear($seasonYear) . " (ID: {$season->id})\n";
echo "🔗 URL FBref: " . ($team->fbref_url ?? 'MANCANTE') . "\n\n";

// Esempio di dati mockati (simulando lo scraping della tabella standard_stats)
$mockScrapedPlayers = [
    ['Player' => 'Milan Badelj', 'id' => 'abc1'],
    ['Player' => 'M. Badelj', 'id' => 'abc1'],
    ['Player' => 'A. Gudmundsson', 'id' => 'abc2'],
    ['Player' => 'Albert Gudmundsson', 'id' => 'abc2'],
    ['Player' => 'Morten Frendrup', 'id' => 'abc3'],
    ['Player' => 'Vitinha', 'id' => 'abc4'],
];

$localPlayers = Player::whereHas('rosters', function ($q) use ($team, $season) {
    $q->where('team_id', $team->id)->where('season_id', $season->id);
})->get();

echo "🛒 Giocatori locali nel roster: " . $localPlayers->count() . "\n";
echo "------------------------------------------------\n";

foreach ($mockScrapedPlayers as $sPlayer) {
    $sName = $sPlayer['Player'];
    
    $bestMatch = null;
    $bestScore = 0;

    foreach ($localPlayers as $lPlayer) {
        $sNorm = strtolower(Str::ascii($sName));
        $sNorm = preg_replace('/[^a-z]/', '', $sNorm);
        
        $lNorm = strtolower(Str::ascii($lPlayer->name));
        $lNorm = preg_replace('/[^a-z]/', '', $lNorm);

        similar_text($sNorm, $lNorm, $score);
        
        // Fallback inclusione
        if ($score < 85) {
            if (str_contains($lNorm, $sNorm) || str_contains($sNorm, $lNorm)) {
                $score = 86;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $lPlayer;
        }
    }

    $status = ($bestScore >= 85) ? "✅ OK" : "❌ NO MATCH";
    echo sprintf("[%s] FBref: %-20s -> DB: %-20s (Score: %5.1f%%)\n", 
        $status, 
        $sName, 
        $bestMatch ? $bestMatch->name : 'Nessuno', 
        $bestScore
    );
}

echo "\n🎯 Validazione logica completata.\n";
