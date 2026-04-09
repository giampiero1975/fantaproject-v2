<?php

namespace App\Console\Commands\Extraction;

use Illuminate\Console\Command;
use App\Services\FbrefScrapingService;
use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class UpdatePlayerFbrefUrls extends Command
{
    protected $signature = 'fbref:update-player-fbref-urls 
                            {--player_id= : Singolo giocatore} 
                            {--team_id=   : Giocatori di una squadra} 
                            {--all        : Tutti i mancanti}
                            {--season=    : Stagione di riferimento per il team_id (default current)}';
    
    protected $description = 'Cerca e aggiorna gli URL FBref dei giocatori mancanti.';
    protected $scrapingService;
    
    public function __construct(FbrefScrapingService $scrapingService)
    {
        parent::__construct();
        $this->scrapingService = $scrapingService;
    }
    
    public function handle()
    {
        $playerId = $this->option('player_id');
        $teamId = $this->option('team_id');
        $all = $this->option('all');
        $seasonYear = $this->option('season');
        
        if (!$playerId && !$teamId && !$all) {
            $this->error('Devi specificare --player_id, --team_id o --all.');
            return Command::FAILURE;
        }
        
        $query = Player::query()->whereNull('fbref_url');
        
        if ($playerId) {
            $query->where('id', $playerId);
        } elseif ($teamId) {
            // Per il team_id, cerchiamo i player che sono nel roster di quella squadra per la stagione indicata
            $season = $seasonYear 
                ? Season::where('season_year', $seasonYear)->first()
                : Season::where('is_current', true)->first();

            if (!$season) {
                $this->error("Stagione non trovata.");
                return Command::FAILURE;
            }

            $playerIds = PlayerSeasonRoster::where('team_id', $teamId)
                ->where('season_id', $season->id)
                ->pluck('player_id');
            
            $query->whereIn('id', $playerIds);
        }
        
        $players = $query->get();
        
        if ($players->isEmpty()) {
            $this->info('Nessun giocatore da aggiornare.');
            return Command::SUCCESS;
        }
        
        $this->info("🔄 Avvio ricerca per " . $players->count() . " giocatori...");
        $bar = $this->output->createProgressBar($players->count());
        $bar->start();

        foreach ($players as $player) {
            // Ottieni il nome squadra attuale (dal registro o dal roster più recente)
            $teamName = $player->team_name; 
            if (!$teamName) {
                $roster = $player->latestRoster;
                $teamName = $roster?->team?->short_name;
            }

            $url = $this->scrapingService->searchPlayerFbrefUrlByName($player->name, $teamName);
            
            if ($url) {
                $attrs = ['fbref_url' => $url];
                // Estrai ID
                if (preg_match('/players\/([a-f0-9]+)/', $url, $matches)) {
                    $attrs['fbref_id'] = $matches[1];
                }
                $player->update($attrs);
            }
            
            $bar->advance();
            // Rispetta rate limit se necessario
            usleep(500000); 
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("✅ Completato.");
        
        return Command::SUCCESS;
    }
}
