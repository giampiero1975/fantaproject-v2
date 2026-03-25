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

    /**
     * Cache per i team già trovati, per ottimizzare le query.
     * @var array
     */
    private array $teamCache = [];

    private RoleNormalizationService $roleNormalizer;

    public function __construct()
    {
        self::$keysLoggedForRosterImport = false;
        $this->rowDataRowCount = 0;
        $this->processedCount  = 0;
        $this->createdCount    = 0;
        $this->updatedCount    = 0;
        $this->roleNormalizer  = new RoleNormalizationService();
    }

    public function headingRow(): int
    {
        return 2;
    }

    /**
     * Chiamato da maatwebsite/excel per ogni riga.
     *
     * NOTA CRITICA su ToModel:
     *   - Restituire una nuova istanza Player (non salvata) → excel esegue INSERT
     *   - Restituire null → riga saltata (nessun INSERT)
     *   - NON chiamare Player::create() / save() qui: excel lo fa da solo
     *     e wrappa ogni operazione in una transaction. Se salviamo prima,
     *     il double-save causerebbe una unique-key violation → rollback.
     *
     * Per i record ESISTENTI (trovati via withTrashed), gestiamo il
     * save/restore manualmente e restituiamo null per saltare il salvataggio
     * automatico di excel.
     */
    public function model(array $row): ?Player
    {
        $this->rowDataRowCount++;

        if (!self::$keysLoggedForRosterImport && !empty($row)) {
            Log::info('TuttiSheetImport@model: CHIAVI RICEVUTE: ' . json_encode(array_keys($row)));
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

        $playerData = [
            'name'              => $nome,
            'team_id'           => $teamId,
            'team_name'         => $teamName,
            'role'              => $normalizedRoles['role_main'],
            'detailed_position' => $normalizedRoles['detailed_position'],
            'initial_quotation' => ($qti !== null && is_numeric($qti)) ? (int)$qti : null,
            'current_quotation' => ($qta !== null && is_numeric($qta)) ? (int)$qta : null,
            'fvm'               => $fvm,
            'fanta_platform_id' => (int)$fantaPlatformId,
        ];

        try {
            // ── FASE 1: Cerca record esistente (inclusi soft-deleted) ─────────
            $player = Player::withTrashed()
                ->where('fanta_platform_id', (int)$fantaPlatformId)
                ->first();

            // ── FASE 2: Fallback per nome+squadra ────────────────────────────
            if (!$player) {
                $teamModel = Team::find($teamId);
                if ($teamModel) {
                    $player = $this->findPlayer(['name' => $nome], $teamModel, $normalizedRoles['role_main']);
                }
            }

            // ── Record ESISTENTE: gestione completa manuale, poi return null ──
            if ($player) {
                // Logica di merge per il nome (mantieni il più lungo)
                if (strlen($player->name) > strlen($playerData['name'])) {
                    $playerData['name'] = $player->name;
                }

                // Logica di merge per detailed_position
                $existing = $player->detailed_position;
                if (!empty($existing) && is_array($existing)) {
                    $merged = array_unique(array_merge($existing, $playerData['detailed_position']));
                    sort($merged);
                    $playerData['detailed_position'] = $merged;
                }

                // Salva i dati aggiornati
                $player->fill($playerData)->save();

                if ($player->wasChanged()) {
                    $this->updatedCount++;
                }

                // Ripristina se era soft-deleted
                if ($player->trashed()) {
                    $player->restore();
                    Log::info("TuttiSheetImport: Ripristinato '{$nome}' (ID DB: {$player->id}).");
                }

                // IMPORTANTE: restituiamo null per evitare che excel chiami
                // save() di nuovo e generi una duplicate-key exception.
                return null;
            }

            // ── Record NUOVO: restituiamo l'istanza non salvata ───────────────
            // maatwebsite/excel chiamerà save() nella sua transaction.
            $this->createdCount++;
            return new Player($playerData);

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
}