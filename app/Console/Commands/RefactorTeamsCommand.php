<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Season;
use App\Models\League;
use App\Models\TeamSeason;
use App\Models\Player;
use App\Models\TeamHistoricalStanding;
use App\Models\PlayerFbrefStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefactorTeamsCommand extends Command
{
    protected $signature = 'football:refactor-v8';
    protected $description = 'Refactoring di Normalizzazione v8.0 delle squadre';

    public function handle()
    {
        $this->info('Inizio Refactoring Normalizzazione v8.0...');

        DB::transaction(function () {
            // 1. Crea la lega Serie A
            $league = League::firstOrCreate(
                ['name' => 'Serie A'],
                ['country_code' => 'ITA']
            );

            // 2. Recupera tutti i record attuali (60 record snapshot)
            $oldTeams = DB::table('teams')->get();
            $uniqueNames = $oldTeams->pluck('name')->unique();

            $this->info("Trovate " . $uniqueNames->count() . " squadre uniche su " . $oldTeams->count() . " record.");

            $mapping = []; // [Old ID => New Master ID]

            // 3. Crea i Master Teams
            foreach ($uniqueNames as $name) {
                // Prendiamo l'ultimo record per quella squadra per avere i dati più recenti (logo, etc.)
                $source = $oldTeams->where('name', $name)->sortByDesc('season')->first();

                $masterTeam = Team::create([
                    'name'          => $source->name,
                    'official_name' => $source->name, // Per ora usiamo il nome comune
                    'short_name'    => $source->short_name,
                    'tla'           => $source->tla,
                    'logo_url'      => $source->logo_url,
                    'api_id'        => $source->api_id,
                    'fbref_id'      => $source->fbref_id,
                    'fbref_url'     => $source->fbref_url,
                    'posizione_media_storica' => $source->posizione_media_storica,
                    'tier_globale'  => $source->tier_globale,
                ]);

                // Mappiamo tutti gli ID vecchi di questa squadra al nuovo Master ID
                foreach ($oldTeams->where('name', $name) as $old) {
                    $mapping[$old->id] = $masterTeam->id;
                    
                    // 4. Crea lo snapshot in team_season
                    $seasonModel = Season::where('season_year', $old->season)->first();
                    
                    TeamSeason::create([
                        'team_id'         => $masterTeam->id,
                        'season_id'       => $seasonModel ? $seasonModel->id : 0,
                        'league_id'       => $league->id,
                        'tier_stagionale' => $old->tier_globale,
                        'posizione_finale' => null, // Da popolare se disponibile
                        'is_active'       => (bool)($old->serie_a_team ?? true),
                        'punti'           => null,
                    ]);
                }
            }

            // 5. Aggiorna Chiavi Esterne
            $this->info("Aggiornamento chiavi esterne in corso...");

            foreach ($mapping as $oldId => $newId) {
                Player::where('team_id', $oldId)->update(['team_id' => $newId]);
                TeamHistoricalStanding::where('team_id', $oldId)->update(['team_id' => $newId]);
                PlayerFbrefStat::where('team_id', $oldId)->update(['team_id' => $newId]);
            }

            // 6. Rimuovi i vecchi record Snapshot dalla tabella teams
            // IMPORTANTE: Dobbiamo rimuovere solo i record originali, non i nuovi Master appena creati.
            // Poiché abbiamo usato i nuovi ID che sono > degli ID vecchi (normalmente), 
            // ma per sicurezza cancelliamo solo gli ID che sono nelle chiavi del mapping.
            DB::table('teams')->whereIn('id', array_keys($mapping))->delete();
        });

        $this->info('Refactoring completato con successo!');
        $this->info('Le 60 snapshot sono ora nella tabella team_season.');
        $this->info('La tabella teams contiene solo i Master record unici.');
    }
}
