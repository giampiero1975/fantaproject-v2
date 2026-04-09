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
    protected $description = 'Ricalcola i Tier (1-5) usando il modello Fattore Potenza (Punti/GF/GS).';

    /**
     * Execute the console command.
     */
    public function handle(TeamDataService $service): int
    {
        $cfg = config('projection_settings.tiers', []);
        
        $lookback = $this->option('lookback') 
            ? (int) $this->option('lookback') 
            : (int) ($cfg['lookback_seasons'] ?? 4);

        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🏆  GOLD STANDARD TIER UPDATE (Power Factor)');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("⚙️  Motore    : Power Factor (60% Pts, 28% GF, 12% GS)");
        $this->info("⚙️  Lookback   : {$lookback} stagioni");
        $this->info("⚙️  Divisore   : " . ($cfg['fixed_divisor'] ?? 20));
        $this->info("⚙️  Soglie T   : T1:{$cfg['thresholds']['t1']} | T2:{$cfg['thresholds']['t2']} | T3:{$cfg['thresholds']['t3']} | T4:{$cfg['thresholds']['t4']}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        try {
            $result = $service->updateTeamTiers($lookback);
            
            if ($result['updated'] > 0) {
                $this->info("✅  Aggiornate : {$result['updated']} squadre");
            }

            if ($result['skipped'] > 0) {
                $this->warn("⚠️  Saltate    : {$result['skipped']} squadre (dati storici mancanti)");
            }
        } catch (\Exception $e) {
            $this->error("❌  Errore fatale: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info('');
        $this->info('📋  Log        : storage/logs/Tiers/TeamsUpdateTiers.log');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        return Command::SUCCESS;
    }
}
