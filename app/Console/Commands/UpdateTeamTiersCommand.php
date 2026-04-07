<?php

namespace App\Console\Commands;

use App\Services\TeamDataService;
use Illuminate\Console\Command;

class UpdateTeamTiersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'teams:update-tiers
                            {--lookback= : Numero di stagioni storiche da analizzare (default: da config)}';

    /**
     * The console command description.
     */
    protected $description = 'Ricalcola i Tier (1-5) per tutte le squadre di Serie A usando i parametri Gold Standard.';

    /**
     * Execute the console command.
     */
    public function handle(TeamDataService $service): int
    {
        $lookback = $this->option('lookback') 
            ? (int) $this->option('lookback') 
            : (int) config('projection_settings.tier_calculation.lookback_seasons', 4);

        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🏆  GOLD STANDARD TIER UPDATE');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("⚙️  Modalità : Points Normalizzati (pts/giocate × 3)");
        $this->info("⚙️  Lookback  : {$lookback} stagioni");
        $this->info("⚙️  CF Serie B: " . config('projection_settings.tier_calculation.serie_b_conversion_factor', 0.95));
        $this->info("⚙️  Divisore  : " . config('projection_settings.tier_calculation.fixed_divisor', 17));
        $this->info('');

        $result = $service->updateTeamTiers($lookback);

        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($result['updated'] > 0) {
            $this->info("✅  Aggiornate : {$result['updated']} squadre");
        }

        if ($result['skipped'] > 0) {
            $this->warn("⚠️  Saltate    : {$result['skipped']} squadre (dati storici mancanti)");
        }

        $this->info('📋  Log        : storage/logs/Tiers/TeamsUpdateTiers.log');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        return Command::SUCCESS;
    }
}
