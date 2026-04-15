<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HistoricalStatsImport;
use App\Helpers\SeasonHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ImportHistoricalStats extends Command
{
    /**
     * Il nome e la firma del comando.
     */
    protected $signature = 'app:import-historical-stats {path} {--season-id= : ID relazionale della stagione (es. 5)} {--season= : Anno di inizio della stagione (es. 2023 - Fallback)}';

    /**
     * La descrizione del comando.
     */
    protected $description = 'Importa le statistiche storiche dei calciatori (Logica Roster-Driven)';

    /**
     * Esecuzione del comando.
     */
    public function handle()
    {
        $filePath = $this->argument('path');
        $seasonId = $this->option('season-id');

        // Fallback per anno se non passato ID diretto
        if (!$seasonId) {
            $seasonYear = $this->option('season') ?? SeasonHelper::getCurrentSeason();
            $season     = \App\Models\Season::where('season_year', $seasonYear)->first();
            $seasonId   = $season?->id;
            
            if (!$seasonId) {
                $this->error("ERRORE: Stagione per l'anno {$seasonYear} non trovata.");
                return Command::FAILURE;
            }
        }

        $season = \App\Models\Season::find($seasonId);

        // --- Configurazione Logging Dedicato ---
        $logDir  = storage_path('logs/HistoricalStats');
        $logPath = $logDir . '/Import.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logger = Log::build(['driver' => 'single', 'path' => $logPath]);
        // -----------------------------------------------------

        if (!File::exists($filePath)) {
            $errorMsg = "ERRORE: Il file non esiste nel percorso: {$filePath}";
            $this->error($errorMsg);
            $logger->error($errorMsg);
            return Command::FAILURE;
        }

        $this->info("=== AVVIO IMPORTAZIONE STORICO STAGIONE ID: {$seasonId} (" . ($season?->season_year ?? 'N/D') . ") ===");
        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $logger->info('📥  AVVIO IMPORTAZIONE STORICO (ROSTER-DRIVEN)');
        $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $logger->info("📄  File    : " . basename($filePath));
        $logger->info("📅  Stagione: " . ($season?->season_year ?? 'ID ' . $seasonId));
        $logger->info("🕐  Ora     : " . now()->format('Y-m-d H:i:s'));

        try {
            $importer = new HistoricalStatsImport((int)$seasonId, $logger);
            Excel::import($importer, $filePath);
            
            $totalRows = $importer->getExcelRowCount();
            $success   = $importer->getMatchSuccessCount();
            $failed    = $importer->getMatchFailedCount();

            $logger->info("✅  COMPLETATO — Elaborate: {$totalRows} | Riuscite: {$success} | Fallite: {$failed}");
            $logger->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $this->table(
                ['Categoria', 'Totale'],
                [
                    ['Righe Elaborate (Excel)', $totalRows],
                    ['Match Riusciti (ID + Roster)', $success],
                    ['Match Falliti (ID non trovato o no Roster)', $failed],
                ]
            );

            if ($success == 0) {
                $this->warn("ATTENZIONE: Nessun match relazionale riuscito. Verifica l'anagrafica o il roster stagionale.");
            } else {
                $this->info("Importazione completata con successo!");
            }
            
        } catch (\Exception $e) {
            $this->error("ERRORE CRITICO: " . $e->getMessage());
            $logger->error("❌  ERRORE CRITICO: " . $e->getMessage());
            $logger->error($e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
