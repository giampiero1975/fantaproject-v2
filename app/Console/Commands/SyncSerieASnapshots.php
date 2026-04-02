<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Season;
use App\Models\League;
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

        // Assicuriamoci che la lega Serie A esista (API_ID 2019)
        $league = League::where('api_id', 2019)->first();
        if (!$league) {
            $this->error("Lega Serie A (API_ID 2019) non trovata. Esegui prima l'inizializzazione.");
            return;
        }

        $apiKey = env('FOOTBALL_DATA_API_KEY');
        if (!$apiKey) {
            $this->error("FOOTBALL_DATA_API_KEY missing in .env");
            return;
        }

        // Prepariamo la chiamata discovery per assicurarci di avere l'ID ufficiale
        $discoveryUrl = "https://api.football-data.org/v4/competitions/SA/teams?season={$seasonYear}";
        $discoveryResponse = Http::withHeaders(['X-Auth-Token' => $apiKey])->get($discoveryUrl);

        if ($discoveryResponse->failed()) {
            $this->error("Discovery API fallita per l'anno {$seasonYear}.");
            // Se la stagione esiste già nel DB, procediamo comunque
            $season = Season::where('season_year', $seasonYear)->first();
            if (!$season) return;
        } else {
            $apiData = $discoveryResponse->json();
            $apiSeason = $apiData['season'] ?? null;

            if ($apiSeason && isset($apiSeason['id'])) {
                $season = Season::updateOrCreate(
                    ['api_id' => (int) $apiSeason['id']],
                    [
                        'season_year' => $seasonYear,
                        'start_date' => $apiSeason['startDate'],
                        'end_date' => $apiSeason['endDate'] ?? null,
                        'is_current' => ($seasonYear == date('Y') || ($seasonYear == date('Y') - 1 && date('n') < 8)),
                    ]
                );
                $this->info("✅ Stagione {$seasonYear} allineata via API. Interno ID: {$season->id}, API ID: {$season->api_id}");
            } else {
                $season = Season::where('season_year', $seasonYear)->first();
                if (!$season) {
                    $this->error("Impossibile creare/trovare la stagione {$seasonYear}.");
                    return;
                }
            }
        }

        $baseUrl = 'https://api.football-data.org/v4/competitions/SA/teams';
        $this->info("Recupero squadre per la stagione {$seasonYear} (/teams endpoint)...");
        Log::info("[SyncSerieASnapshots] Avvio recupero squadre per stagione {$seasonYear}...");

        $response = Http::withHeaders([
            'X-Auth-Token' => $apiKey
        ])->get($baseUrl, [
            'season' => $seasonYear
        ]);

        if ($response->failed()) {
            $errorMsg = "Errore chiamata API: " . $response->status() . " - " . $response->reason();
            $this->error($errorMsg);
            $this->logToFile("[SYNC ERROR] Chiamata fallita per season {$seasonYear}: " . $response->body(), 'error');
            
            ImportLog::create([
                'import_type' => 'SYNC_TEAMS',
                'original_file_name' => 'SyncSerieASnapshots',
                'status' => 'FAILED',
                'details' => "API Error {$response->status()}: " . substr($response->body(), 0, 200),
                'rows_processed' => 0,
            ]);

            return self::FAILURE;
        }

        $data = $response->json();
        $teamsData = $data['teams'] ?? [];

        if (empty($teamsData)) {
            $this->warn("Nessuna squadra restituita per la stagione {$seasonYear}.");
            $this->logToFile("[SYNC WARN] Nessuna squadra restituita per season {$seasonYear}.");
            return;
        }

        $countCreated = 0;
        $countUpdated = 0;

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

            if ($team->wasRecentlyCreated) {
                $countCreated++;
            } else {
                $countUpdated++;
            }

            TeamSeason::updateOrCreate(
                [
                    'team_id' => $team->id,
                    'season_id' => $season->id,
                ],
                [
                    'league_id' => $league->id,
                ]
            );
        }

        $summary = "Sincronizzate " . ($countCreated + $countUpdated) . " squadre per la stagione {$seasonYear} (create: {$countCreated}, aggiornate: {$countUpdated})";
        $this->info("✨ {$summary}");
        
        $this->logToFile("[SYNC OK] {$summary}");
        Log::info("[SyncSerieASnapshots] {$summary}");

        ImportLog::create([
            'import_type' => 'SYNC_TEAMS',
            'original_file_name' => 'SyncSerieASnapshots',
            'status' => 'SUCCESS',
            'details' => $summary,
            'rows_created' => $countCreated,
            'rows_updated' => $countUpdated,
        ]);

        return self::SUCCESS;
    }
}
