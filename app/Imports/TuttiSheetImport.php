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
    public int $updatedCount   = 0;

    /** @var array Elenco degli ID calciatori (DB) processati in questa sessione */
    private array $processedPlayerIds = [];

    /**
     * Cache per i team già trovati, per ottimizzare le query.
     * @var array
     */
    private array $teamCache = [];

    private RoleNormalizationService $roleNormalizer;

    private int $seasonId;

    public function __construct(int $seasonId)
    {
        $this->seasonId = $seasonId;
        self::$keysLoggedForRosterImport = false;
        $this->rowDataRowCount = 0;
        $this->processedCount  = 0;
        $this->createdCount    = 0;
        $this->updatedCount    = 0;
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
            Log::warning("TuttiSheetImport: Squadra '{$teamName}' non trovata per giocatore ID {$fantaPlatformId}. Skip.");
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
                Log::info("TuttiSheetImport: [CREATO_NUOVO] '{$nome}' (ID Plataforma: {$fantaPlatformId}) -> ID DB: {$player->id}");
            } else {
                // Aggiornamento anagrafica esistente
                $wasMatchedBy = $player->fanta_platform_id == $fantaPlatformId ? "ID ({$fantaPlatformId})" : "Nome/Squadra Similarity";
                Log::info("TuttiSheetImport: [MATCH_TROVATO] '{$nome}' (ID: {$fantaPlatformId}) corrisponde a ID DB: {$player->id} (Metodo: {$wasMatchedBy})");
                
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
                Log::info("TuttiSheetImport: [ROSTER_CREATO] Roster per '{$nome}' (ID DB: {$player->id}) in Stagione {$this->seasonId} creato.");
            } else if ($roster->wasChanged()) {
                $this->updatedCount++;
                Log::info("TuttiSheetImport: [ROSTER_AGGIORNATO] Roster per '{$nome}' (ID DB: {$player->id}) ha ricevuto aggiornamenti dati.");
            } else {
                Log::info("TuttiSheetImport: [ROSTER_INVARIATO] Roster per '{$nome}' già presente e dati identici.");
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
    public function getUpdatedCount(): int   { return $this->updatedCount;   }
    
    public function getProcessedPlayerIds(): array { return $this->processedPlayerIds; }
}