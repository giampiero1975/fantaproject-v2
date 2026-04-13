<?php

namespace App\Imports;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Throwable;
use App\Services\RoleNormalizationService;
use App\Traits\FindsTeam;
use App\Traits\FindsPlayerByName;

class TuttiSheetImport implements ToModel, WithHeadingRow, SkipsOnError
{
    use FindsTeam;
    use FindsPlayerByName;

    private static bool $keysLoggedForRosterImport = false;
    private int $rowDataRowCount = 0;

    public int $processedCount = 0;
    public int $createdCount   = 0;
    public int $transferCount  = 0;
    public int $confirmedCount = 0;

    /** @var array Elenco degli ID calciatori (DB) processati in questa sessione */
    private array $processedPlayerIds = [];

    /**
     * Cache per i team già trovati, per ottimizzare le query.
     * @var array
     */
    private array $teamCache = [];

    private RoleNormalizationService $roleNormalizer;

    private int $seasonYear;

    public function __construct(int $seasonId)
    {
        $this->seasonId   = $seasonId;
        $this->seasonYear = \App\Models\Season::find($seasonId)?->season_year ?? 0;
        self::$keysLoggedForRosterImport = false;
        $this->rowDataRowCount = 0;
        $this->processedCount  = 0;
        $this->createdCount    = 0;
        $this->transferCount   = 0;
        $this->confirmedCount  = 0;
        $this->processedPlayerIds = [];
        $this->roleNormalizer  = new RoleNormalizationService();
    }

    public function headingRow(): int
    {
        return 2;
    }

    /**
     * Chiamato da maatwebsite/excel per ogni riga.
     * Gestiamo manualmente il salvataggio sia dell'anagrafica che del roster stagionale.
     */
    public function model(array $row): ?Player
    {
        $this->rowDataRowCount++;

        if (!self::$keysLoggedForRosterImport && !empty($row)) {
            Log::info('TuttiSheetImport: [DIAG] CHIAVI RICEVUTE: ' . json_encode(array_keys($row)));
            self::$keysLoggedForRosterImport = true;
        }

        // ── Normalizzazione chiavi ──────────────────────────────────────────────
        $normalizedRow = [];
        foreach ($row as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $normalizedKey = preg_replace('/[\s.]+/', '_', $normalizedKey);
            $normalizedRow[$normalizedKey] = $value;
        }

        $fantaPlatformId = $normalizedRow['id']   ?? null;
        $nome            = $normalizedRow['nome']  ?? null;

        if ($fantaPlatformId === null || $nome === null || trim((string)$nome) === '') {
            return null; // riga di intestazione extra o vuota
        }

        $this->processedCount++;
        $fantaPlatformId = trim((string)$fantaPlatformId);
        $nome            = trim((string)$nome);

        // ── Ricerca squadra ─────────────────────────────────────────────────────
        $teamName = isset($normalizedRow['squadra']) ? trim((string)$normalizedRow['squadra']) : null;
        $teamId   = $this->findTeamIdByName($teamName);

        if (!$teamId) {
            Log::warning("⚠️  [RIGA_SALTATA] Calciatore '{$nome}' (ID: {$fantaPlatformId}) ignorato: Squadra '{$teamName}' non trovata nel database (Verifica mapping o nomi squadre).");
            return null;
        }

        // ── Quotazioni e ruoli ──────────────────────────────────────────────────
        $qti = $normalizedRow['qti'] ?? $normalizedRow['qt_i'] ?? null;
        $qta = $normalizedRow['qta'] ?? $normalizedRow['qt_a'] ?? null;
        $fvm = isset($normalizedRow['fvm']) && is_numeric($normalizedRow['fvm']) ? (int)$normalizedRow['fvm'] : null;

        $normalizedRoles = $this->roleNormalizer->normalize([
            'r'  => $normalizedRow['r']  ?? null,
            'rm' => $normalizedRow['rm'] ?? null,
        ], 'roster_xlsx');

        if (is_null($normalizedRoles['role_main'])) {
            Log::error("TuttiSheetImport: Ruolo non valido per ID {$fantaPlatformId}. Skip.");
            return null;
        }

        // Dati Anagrafici (Tabella players)
        $bioData = [
            'name'              => $nome,
            'fanta_platform_id' => (int)$fantaPlatformId,
        ];

        // Dati Roster Stagionale (Tabella player_season_roster)
        $rosterData = [
            'team_id'           => $teamId,
            'role'              => $normalizedRoles['role_main'],
            'detailed_position' => $normalizedRoles['detailed_position'],
            'initial_quotation' => ($qti !== null && is_numeric($qti)) ? (int)$qti : null,
            'current_quotation' => ($qta !== null && is_numeric($qta)) ? (int)$qta : null,
            'fvm'               => $fvm,
        ];

        try {
            // ── 1. GESTIONE ANAGRAFICA (Player) ───────────────────────────────────
            $player = Player::withTrashed()
                ->where('fanta_platform_id', (int)$fantaPlatformId)
                ->first();

            if (!$player) {
                // Se non trovato per ID, fallback per nome+squadra (stessa logica di prima)
                // Usiamo il teamId per aiutare il matching ma in players non lo salviamo
                $teamModel = Team::find($teamId);
                if ($teamModel) {
                    $matchedByName = $this->findPlayer(['name' => $nome], $teamModel, $normalizedRoles['role_main']);
                    
                    // ── LOGICA DI PROTEZIONE ID ──────────────────────────────────
                    // Se troviamo un calciatore per nome, ma questo ha già un ID DIVERSO 
                    // nel database, allora NON è lo stesso calciatore (es. i due Milinkovic-Savic).
                    if ($matchedByName && $matchedByName->fanta_platform_id && $matchedByName->fanta_platform_id != (int)$fantaPlatformId) {
                        Log::info("TuttiSheetImport: [MATCH_SIMILARITY_RIFIUTATO] '{$nome}' ignorato match con ID DB {$matchedByName->fanta_platform_id} perché l'ID del file è {$fantaPlatformId}");
                        $matchedByName = null;
                    }
                    
                    $player = $matchedByName;
                }
            }

            if (!$player) {
                // Nuovo calciatore
                $player = Player::create(array_merge($bioData, [
                    'role'              => $rosterData['role'],
                    'detailed_position' => $rosterData['detailed_position'],
                ]));
                $this->createdCount++;
                Log::info("✨  [CREATO_NUOVO] Calciatore '{$nome}' (ID Fanta: {$fantaPlatformId}) aggiunto come nuovo record (ID DB: {$player->id}).");
            } else {
                // Aggiornamento anagrafica esistente
                $wasMatchedBy = $player->fanta_platform_id == $fantaPlatformId ? "ID FANTA ({$fantaPlatformId})" : "SIMILARITÀ NOME/SQUADRA";
                Log::info("🔗  [RIUTILIZZO_ANAGRAFICA] '{$nome}' associato al record esistente ID DB: {$player->id} (Metodo: {$wasMatchedBy}).");

                // ── LOGICA TRASFERIMENTO ───────────────────────────────────
                // Cerchiamo l'ultimo roster basandoci sull'anno della stagione precedente
                $lastRoster = \App\Models\PlayerSeasonRoster::where('player_id', $player->id)
                    ->join('seasons', 'player_season_roster.season_id', '=', 'seasons.id')
                    ->where('seasons.season_year', '<', $this->seasonYear)
                    ->orderByDesc('seasons.season_year')
                    ->select('player_season_roster.*')
                    ->first();

                if ($lastRoster && $lastRoster->team_id != $teamId) {
                    $oldTeamName = Team::find($lastRoster->team_id)?->name ?? "Sconosciuta";
                    $newTeamName = Team::find($teamId)?->name ?? "Sconosciuta";
                    Log::info("🔄  [TRASFERIMENTO] '{$nome}' (ID: {$fantaPlatformId}) spostato da '{$oldTeamName}' a '{$newTeamName}'.");
                    $this->transferCount++;
                } else {
                    $this->confirmedCount++;
                }
                
                $updateData = [];
                if (strlen($player->name) < strlen($bioData['name'])) {
                    $updateData['name'] = $bioData['name'];
                }

                // Aggiorniamo sempre il ruolo e le posizioni dettagliate all'ultimo caricamento
                $updateData['role'] = $rosterData['role'];
                
                $existing = $player->detailed_position;
                if (!empty($existing) && is_array($existing)) {
                    $merged = array_unique(array_merge($existing, $rosterData['detailed_position']));
                    sort($merged);
                    $updateData['detailed_position'] = $merged;
                } else {
                    $updateData['detailed_position'] = $rosterData['detailed_position'];
                }

                if (!empty($updateData)) {
                    $player->update($updateData);
                }
                
                if ($player->trashed()) {
                    $player->restore();
                }
            }

            // ── 2. GESTIONE ROSTER STAGIONALE (PlayerSeasonRoster) ────────────────
            $roster = \App\Models\PlayerSeasonRoster::updateOrCreate(
                [
                    'player_id' => $player->id,
                    'season_id' => $this->seasonId,
                ],
                $rosterData
            );

            if ($roster->wasRecentlyCreated) {
                Log::info("   [ROSTER_LINK] '{$nome}' collegato alla stagione {$this->seasonId} (Nuovo record roster).");
            } else if ($roster->wasChanged()) {
                Log::info("   [ROSTER_MOD] '{$nome}' (ID DB: {$player->id}) ha ricevuto aggiornamenti nel roster per la stagione corrente.");
            } else {
                Log::info("   [ROSTER_OK] '{$nome}' già presente per questa stagione.");
            }

            $this->processedPlayerIds[] = $player->id;

            // Restituiamo null a Laravel-Excel perché abbiamo già salvato tutto manualmente.
            return null;

        } catch (Throwable $exception) {
            Log::error('TuttiSheetImport: EXCEPTION per ' . $nome, [
                'msg'  => $exception->getMessage(),
                'data' => array_slice($row, 0, 5), // log solo i primi 5 campi
            ]);
            throw $exception; // propagato a SkipsOnError::onError()
        }
    }

    public function onError(Throwable $e): void
    {
        Log::error('TuttiSheetImport@onError: ' . $e->getMessage());
    }

    public function getProcessedCount(): int { return $this->processedCount; }
    public function getCreatedCount(): int   { return $this->createdCount;   }
    public function getTransferCount(): int  { return $this->transferCount;  }
    public function getConfirmedCount(): int { return $this->confirmedCount; }
    public function getUpdatedCount(): int   { return $this->transferCount; } // Alias per compatibilità con ImportaListone
    
    public function getProcessedPlayerIds(): array { return $this->processedPlayerIds; }
}