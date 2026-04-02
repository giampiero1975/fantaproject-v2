<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Season;
use App\Models\TeamHistoricalStanding;
use Illuminate\Support\Facades\DB;

echo "🔨 Avvio Iniezione Manuale Dati Stagione 2021/22...\n";

$seasonYear = 2021;
$season = Season::where('season_year', $seasonYear)->first();

if (!$season) {
    echo "⚠️ Stagione $seasonYear non trovata, la creo...\n";
    $season = Season::create(['season_year' => $seasonYear]);
}

$standings = [
    ['rank' => 1, 'name' => 'Milan', 'pts' => 86, 'w' => 26, 'd' => 8, 'l' => 4],
    ['rank' => 2, 'name' => 'Inter', 'pts' => 84, 'w' => 25, 'd' => 9, 'l' => 4],
    ['rank' => 3, 'name' => 'Napoli', 'pts' => 79, 'w' => 24, 'd' => 7, 'l' => 7],
    ['rank' => 4, 'name' => 'Juventus', 'pts' => 70, 'w' => 20, 'd' => 10, 'l' => 8],
    ['rank' => 5, 'name' => 'Lazio', 'pts' => 64, 'w' => 18, 'd' => 10, 'l' => 10],
    ['rank' => 6, 'name' => 'Roma', 'pts' => 63, 'w' => 18, 'd' => 9, 'l' => 11],
    ['rank' => 7, 'name' => 'Fiorentina', 'pts' => 62, 'w' => 19, 'd' => 5, 'l' => 14],
    ['rank' => 8, 'name' => 'Atalanta', 'pts' => 59, 'w' => 16, 'd' => 11, 'l' => 11],
    ['rank' => 9, 'name' => 'Hellas Verona', 'pts' => 53, 'w' => 14, 'd' => 11, 'l' => 13],
    ['rank' => 10, 'name' => 'Torino', 'pts' => 50, 'w' => 13, 'd' => 11, 'l' => 14],
    ['rank' => 11, 'name' => 'Sassuolo', 'pts' => 50, 'w' => 13, 'd' => 11, 'l' => 14],
    ['rank' => 12, 'name' => 'Udinese', 'pts' => 47, 'w' => 11, 'd' => 14, 'l' => 13],
    ['rank' => 13, 'name' => 'Bologna', 'pts' => 46, 'w' => 12, 'd' => 10, 'l' => 16],
    ['rank' => 14, 'name' => 'Empoli', 'pts' => 41, 'w' => 10, 'd' => 11, 'l' => 17],
    ['rank' => 15, 'name' => 'Sampdoria', 'pts' => 36, 'w' => 10, 'd' => 6, 'l' => 22],
    ['rank' => 16, 'name' => 'Spezia', 'pts' => 36, 'w' => 10, 'd' => 6, 'l' => 22],
    ['rank' => 17, 'name' => 'Salernitana', 'pts' => 31, 'w' => 7, 'd' => 10, 'l' => 21],
    ['rank' => 18, 'name' => 'Cagliari', 'pts' => 30, 'w' => 6, 'd' => 12, 'l' => 20],
    ['rank' => 19, 'name' => 'Genoa', 'pts' => 28, 'w' => 4, 'd' => 16, 'l' => 18],
    ['rank' => 20, 'name' => 'Venezia', 'pts' => 27, 'w' => 6, 'd' => 9, 'l' => 23],
];

foreach ($standings as $data) {
    // Cerchiamo la squadra per nome (case insensitive)
    $team = Team::where('name', 'LIKE', $data['name'])->first();
    
    if (!$team) {
        echo "🆕 Creazione nuovo Team: {$data['name']}\n";
        $team = Team::create([
            'name' => $data['name'],
            'short_name' => substr($data['name'], 0, 3) // Fallback semplice
        ]);
    }

    // Associazione alla stagione (pivot TeamSeason)
    $team->teamSeasons()->updateOrCreate([
        'season_id' => $season->id,
    ]);

    // Salvataggio Posizione Storica
    TeamHistoricalStanding::updateOrCreate(
        [
            'team_id' => $team->id,
            'season_year' => $seasonYear,
        ],
        [
            'league_name' => 'Serie A',
            'position' => $data['rank'],
            'played_games' => 38,
            'points' => $data['pts'],
            'won' => $data['w'],
            'draw' => $data['d'],
            'lost' => $data['l'],
            'data_source' => 'Manual/Screenshot'
        ]
    );
    
    echo "✅ Sincronizzato: {$data['name']} (Pos: {$data['rank']}, Pts: {$data['pts']})\n";
}

echo "\n🚀 OPERAZIONE COMPLETATA! La stagione 2021/22 è ora popolata.\n";
