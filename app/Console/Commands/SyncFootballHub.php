<?php

namespace App\Console\Commands;

use App\Services\FootballHubService;
use Illuminate\Console\Command;

class SyncFootballHub extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'football:reset-sync {--no-reset : Salta il reset delle tabelle}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Esegue il Reset totale e la Sincronizzazione Ibrida (API + FBref) del Football Hub v7.0';

    /**
     * Execute the console command.
     */
    public function handle(FootballHubService $service)
    {
        $this->info("🚀 Football Hub v7.0: Master Reset & Sync");

        if (!$this->option('no-reset')) {
            if ($this->confirm('Stai per cancellare TUTTI i team e lo storico. Sei sicuro?')) {
                $service->resetAll();
                $this->warn("Tabelle svuotate.");
            } else {
                $this->info("Reset annullato.");
                return;
            }
        }

        $this->info("Inizio sincronizzazione (API SA + FBref A/B 2021-2025)...");
        
        $service->synchronize();

        $this->info("✨ Sincronizzazione completata!");
    }
}
