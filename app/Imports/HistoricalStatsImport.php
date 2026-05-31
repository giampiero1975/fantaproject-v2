<?php

namespace App\Imports;

use App\Models\HistoricalPlayerStat;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

class HistoricalStatsImport implements WithMultipleSheets
{
    protected $seasonYear;
    protected $seasonId;
    protected $logger;
    protected $sheets = [];

    public function __construct(int $seasonId, $logger = null)
    {
        $this->seasonId = $seasonId;
        $this->logger   = $logger ?? Log::channel('single');

        // Lookup dell'anno solo per scopi di logging e reportistica
        $season = \App\Models\Season::find($this->seasonId);
        if (!$season) {
            throw new \Exception("Stagione con ID {$this->seasonId} non trovata in database. Caricamento interrotto.");
        }
        $this->seasonYear = $season->season_year;
    }

    public function sheets(): array
    {
        $this->logger->info("🔍  Avvio lettura foglio Master 'Tutti' per stagione [ID: {$this->seasonId}] Anno: {$this->seasonYear}");
        
        $this->sheets = [
            'Tutti' => new HistoricalStatsSheetImport($this->seasonId, $this->logger),
        ];

        return $this->sheets;
    }

    public function getExcelRowCount(): int {
        return collect($this->sheets)->sum(fn($s) => $s->excelRowCount);
    }

    public function getMatchSuccessCount(): int {
        return collect($this->sheets)->sum(fn($s) => $s->matchSuccess);
    }

    public function getMatchFailedCount(): int {
        return collect($this->sheets)->sum(fn($s) => $s->matchFailed);
    }
}

class HistoricalStatsSheetImport implements ToCollection, WithStartRow
{
    use \App\Traits\FindsTeam;

    protected $seasonId;
    protected $logger;
    
    public $excelRowCount = 0;
    public $matchSuccess = 0;
    public $matchFailed = 0;

    public function __construct(int $seasonId, $logger = null)
    {
        $this->seasonId = $seasonId;
        $this->logger     = $logger ?? Log::channel('single');
    }

    public function startRow(): int
    {
        return 3;
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            return;
        }

        $this->logger->info("--- AVVIO IMPORTAZIONE STORICO STAGIONE ID: {$this->seasonId} ---");
        $this->excelRowCount = $rows->count();

        // [ERP-FAST] Caricamento massivo in memoria per abbattere le query (N+1)
        $this->logger->info("Caricamento anagrafica, roster e squadre in memoria...");
        
        $playersMap = \App\Models\Player::withTrashed()
            ->get(['id', 'fanta_platform_id'])
            ->keyBy('fanta_platform_id');

        $rosterMap = \App\Models\PlayerSeasonRoster::where('season_id', $this->seasonId)
            ->get(['player_id', 'team_id'])
            ->keyBy('player_id');

        $teamsCollection = \App\Models\Team::all(['id', 'name', 'short_name']);

        $this->logger->info("Anagrafica caricata: " . $playersMap->count() . " record.");
        $this->logger->info("Roster caricato: " . $rosterMap->count() . " record.");
        $this->logger->info("Squadre caricate: " . $teamsCollection->count() . " record.");

        foreach ($rows as $index => $row) {
            // Gestione flessibile indici (Excel può avere nomi colonne o indici numerici)
            $fantaId = (int)($row['id'] ?? $row[0] ?? 0);
            $playerName = $row['nome'] ?? $row[3] ?? 'N/D';
            $playerRole = $row['r'] ?? $row[1] ?? null;
            $teamName = $row['squadra'] ?? $row[4] ?? null;
            $realRowIndex = $index + 3; // +3 perché startRow è 3

            if ($fantaId === 0) {
                $this->logger->debug("  [RIGA {$realRowIndex}] ID Invalido -> SALTATO");
                continue;
            }

            // 1. Lookup Player dalla Collection (In-Memory)
            $player = $playersMap->get($fantaId);

            // [CREAZIONE NUDA] Se non esiste in anagrafica, creiamolo!
            if (!$player) {
                $this->logger->info("   [RIGA {$realRowIndex}] 🆕 [CREAZIONE NUDA] Giocatore non trovato: {$playerName} (ID: {$fantaId}). Avvio creazione...");
                
                $teamId = $this->findTeamIdInCollection($teamsCollection, $teamName);
                if (!$teamId) {
                    $this->logger->warning("   [RIGA {$realRowIndex}] ❌ [CREAZIONE FALLITA] Squadra '{$teamName}' non trovata a sistema per {$playerName}.");
                    $this->matchFailed++;
                    continue;
                }

                try {
                    $currentSeasonId = \App\Helpers\SeasonHelper::getCurrentSeasonId();
                    $deletedAt = ($this->seasonId < $currentSeasonId) ? now() : null;

                    $player = \App\Models\Player::create([
                        'fanta_platform_id' => $fantaId,
                        'name'              => $playerName,
                        'role'              => $playerRole,
                        'parent_team_id'    => $teamId,
                        'creation_source'   => 'S',
                        'deleted_at'        => $deletedAt,
                    ]);
                    
                    // Aggiungiamolo in memoria per eventuali lookup successivi
                    $playersMap->put($fantaId, $player);
                    $this->logger->info("   [RIGA {$realRowIndex}] ✅ [CREAZIONE NUDA] Giocatore {$playerName} creato con successo.");
                } catch (\Exception $e) {
                    $this->logger->error("   [RIGA {$realRowIndex}] ❌ [ERRORE CREAZIONE] Impossibile creare {$playerName}: " . $e->getMessage());
                    $this->matchFailed++;
                    continue;
                }
            }

            // 2. Lookup Team ID dal Roster della stagione (In-Memory)
            $rosterRecord = $rosterMap->get($player->id);
            $teamId = $rosterRecord?->team_id;

            // [CREAZIONE ROSTER] Se non esiste il roster, creiamolo al volo
            if (!$teamId) {
                $teamId = $this->findTeamIdInCollection($teamsCollection, $teamName);
                if (!$teamId) {
                    $this->logger->warning("   [RIGA {$realRowIndex}] ❌ [ROSTER FALLITO] ID: {$fantaId} | {$playerName} | Squadra '{$teamName}' non trovata per creare Roster Stagione.");
                    $this->matchFailed++;
                    continue;
                }

                try {
                    $rosterRecord = \App\Models\PlayerSeasonRoster::create([
                        'player_id' => $player->id,
                        'season_id' => $this->seasonId,
                        'team_id' => $teamId,
                        'role' => $playerRole
                    ]);
                    $rosterMap->put($player->id, $rosterRecord);
                    $this->logger->info("   [RIGA {$realRowIndex}] ✅ [CREAZIONE ROSTER] Roster creato per {$playerName} nella squadra {$teamName}.");
                } catch (\Exception $e) {
                    $this->logger->error("   [RIGA {$realRowIndex}] ❌ [ERRORE ROSTER] Impossibile creare roster per {$playerName}: " . $e->getMessage());
                    $this->matchFailed++;
                    continue;
                }
            }

            // 3. Salvataggio Statistiche (Unica query di scrittura necessaria)
            try {
                \App\Models\HistoricalPlayerStat::updateOrCreate(
                    [
                        'player_id' => $player->id,
                        'season_id' => $this->seasonId,
                        'team_id'   => $teamId,
                    ],
                    [
                        'player_fanta_platform_id' => $fantaId,
                        'games_played'    => (int)($row['pv'] ?? $row[5] ?? 0),
                        'average_rating'  => $this->parseFloat($row['mv'] ?? $row[6] ?? 0),
                        'fanta_average'   => $this->parseFloat($row['fm'] ?? $row[7] ?? 0),
                        'goals'           => $this->parseFloat($row['gf'] ?? $row[8] ?? 0),
                        'goals_conceded'  => $this->parseFloat($row['gs'] ?? $row[9] ?? 0),
                        'penalties_saved' => $this->parseFloat($row['rp'] ?? $row[10] ?? 0),
                        'penalties_taken' => $this->parseFloat($row['rc'] ?? $row[11] ?? 0),
                        'penalties_scored'=> $this->parseFloat($row['r+'] ?? $row[12] ?? 0),
                        'penalties_missed'=> $this->parseFloat($row['r-'] ?? $row[13] ?? 0),
                        'assists'         => $this->parseFloat($row['as'] ?? $row[14] ?? 0),
                        'yellow_cards'    => $this->parseFloat($row['amm'] ?? $row[15] ?? 0),
                        'red_cards'       => $this->parseFloat($row['esp'] ?? $row[16] ?? 0),
                        'own_goals'       => $this->parseFloat($row['au'] ?? $row[17] ?? 0),
                    ]
                );

                $this->matchSuccess++;
            } catch (\Exception $e) {
                $this->logger->error("   [RIGA {$realRowIndex}] ID: {$fantaId} | Errore Salvataggio: " . $e->getMessage());
                $this->matchFailed++;
            }
        }

        $this->logger->info("--- IMPORTAZIONE COMPLETATA: {$this->matchSuccess} SUCCESSI, {$this->matchFailed} FALLITI ---");
    }
    protected function parseFloat($value): float
    {
        if ($value === null || $value === '') return 0.00;
        if (is_numeric($value)) return (float) $value;
        $cleaned = str_replace(',', '.', (string)$value);
        return is_numeric($cleaned) ? (float) $cleaned : 0.00;
    }
}
