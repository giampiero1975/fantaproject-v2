<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Season;
use App\Models\TeamSeason;
use App\Models\ImportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class SyncSerieASnapshots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'football:sync-serie-a {season_year : Anno di inizio della stagione (es. 2025)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizzazione dinamica Teams per una stagione specifica via Official API (/teams)';

    protected function logToFile(string $message, string $level = 'info')
    {
        $logPath = storage_path('logs/GestioneStagioni');
        if (!File::exists($logPath)) {
            File::makeDirectory($logPath, 0755, true);
        }
        $logger = Log::build([
            'driver' => 'single',
            'path' => $logPath . '/stagioni.log',
        ]);
        $logger->$level($message);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $seasonYear = (int) $this->argument('season_year');
        $this->info("🚀 Football Hub v8.2: Team Sync (Serie A {$seasonYear})");

        $apiKey = env('FOOTBALL_DATA_API_KEY');
        if (!$apiKey) {
            $this->error("FOOTBALL_DATA_API_KEY missing in .env");
            return;
        }

        $season = Season::where('season_year', $seasonYear)->first();
        if (!$season) {
            $this->error("Stagione {$seasonYear} non trovata nel DB. Il ManageSeason.php dovrebbe crearla (o riutilizzarla) durante l'inizializzazione.");
            return;
        }

        $baseUrl = 'https://api.football-data.org/v4/competitions/SA/teams';
        $this->info("Recupero squadre per la stagione {$seasonYear} (/teams endpoint)...");

        $response = Http::withHeaders([
            'X-Auth-Token' => $apiKey
        ])->get($baseUrl, [
            'season' => $seasonYear
        ]);

        if ($response->failed()) {
            $this->error("Errore chiamata API: " . $response->status());
            $this->logToFile("[SYNC ERROR] Chiamata fallita per season {$seasonYear}: " . $response->body(), 'error');
            return;
        }

        $data = $response->json();
        $teamsData = $data['teams'] ?? [];

        if (empty($teamsData)) {
            $this->warn("Nessuna squadra restituita per la stagione {$seasonYear}.");
            $this->logToFile("[SYNC WARN] Nessuna squadra restituita per season {$seasonYear}.");
            return;
        }

        $countSynced = 0;

        foreach ($teamsData as $teamData) {
            $apiId = $teamData['id'] ?? null;
            if (!$apiId) continue;

            $team = Team::updateOrCreate(
                ['api_id' => $apiId],
                [
                    'name' => $teamData['name'] ?? null,
                    'short_name' => $teamData['shortName'] ?? null,
                    'tla' => $teamData['tla'] ?? null,
                    'logo_url' => $teamData['crest'] ?? null,
                ]
            );

            TeamSeason::firstOrCreate([
                'team_id' => $team->id,
                'season_id' => $season->id,
            ]);

            $countSynced++;
        }

        $this->info("✨ Sincronizzazione completata: {$countSynced} squadre elaborate.");
        
        $this->logToFile("[SYNC OK] Sincronizzate {$countSynced} squadre per la stagione {$seasonYear}");

        ImportLog::create([
            'import_type' => 'SYNC_TEAMS',
            'original_file_name' => 'SyncSerieASnapshots',
            'status' => 'SUCCESS',
            'details' => "Sincronizzate {$countSynced} squadre per la stagione {$seasonYear}"
        ]);
    }
}
