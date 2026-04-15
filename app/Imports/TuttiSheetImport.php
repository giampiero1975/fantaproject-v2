<?php

namespace App\Imports;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Throwable;
use App\Services\RoleNormalizationService;
use App\Traits\FindsTeam;
use App\Traits\FindsPlayerByName;

class TuttiSheetImport implements ToCollection, WithHeadingRow, SkipsOnError
{
    use FindsTeam;
    use FindsPlayerByName;

    private static bool $keysLoggedForRosterImport = false;

    public int $processedCount = 0;
    public int $createdCount   = 0;
    public int $transferCount  = 0;
    public int $confirmedCount = 0;

    private array $processedPlayerIds = [];
    private RoleNormalizationService $roleNormalizer;
    private int $seasonId;
    private int $seasonYear;

    public function __construct(int $seasonId)
    {
        $this->seasonId   = $seasonId;
        $this->seasonYear = \App\Models\Season::find($seasonId)?->season_year ?? 0;
        $this->roleNormalizer  = new RoleNormalizationService();
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) return;

        Log::info("--- [ERP-FAST] AVVIO IMPORTAZIONE LISTONE STAGIONE: {$this->seasonYear} ---");

        // 1. CARICAMENTO MASSIVO IN RAM (Pilastri ERP)
        $playersCollection = Player::withTrashed()->get();
        $teamsCollection   = Team::all();
        
        // Roster attuale per evitare duplicati
        $currentRosterMap = \App\Models\PlayerSeasonRoster::where('season_id', $this->seasonId)
            ->get()
            ->keyBy('player_id');

        // Roster anno precedente per logica trasferimenti (senza query nel loop)
        $previousSeason = \App\Models\Season::where('season_year', $this->seasonYear - 1)->first();
        $prevRosterMap = $previousSeason 
            ? \App\Models\PlayerSeasonRoster::where('season_id', $previousSeason->id)->get()->keyBy('player_id')
            : collect();

        Log::info("RAM: Anagrafica: {$playersCollection->count()} | Team: {$teamsCollection->count()} | Roster Prev: {$prevRosterMap->count()}");

        foreach ($rows as $index => $row) {
            // Normalizzazione chiavi (logica originale mantenuta)
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $normalizedKey = strtolower(preg_replace('/[\s.]+/', '_', (string)$key));
                $normalizedRow[$normalizedKey] = $value;
            }

            $fantaPlatformId = $normalizedRow['id'] ?? null;
            $nome = $normalizedRow['nome'] ?? null;

            if ($fantaPlatformId === null || empty(trim((string)$nome))) continue;

            $this->processedCount++;
            $fantaPlatformId = (int)$fantaPlatformId;
            $nome = trim((string)$nome);

            // A. Ricerca Team (In-Memory)
            $teamName = $normalizedRow['squadra'] ?? null;
            $teamId = $this->findTeamIdInCollection($teamsCollection, (string)$teamName);

            if (!$teamId) {
                Log::warning("⚠️ [SALTATO] '{$nome}' (ID: {$fantaPlatformId}): Squadra '{$teamName}' non trovata.");
                continue;
            }

            // B. Normalizzazione Ruoli
            $normalizedRoles = $this->roleNormalizer->normalize([
                'r'  => $normalizedRow['r']  ?? null,
                'rm' => $normalizedRow['rm'] ?? null,
            ], 'roster_xlsx');

            // C. Ricerca Player (In-Memory - Surgical Logic)
            $player = $playersCollection->firstWhere('fanta_platform_id', $fantaPlatformId);

            if (!$player) {
                // Fallback similarità nomi (In-Memory)
                $player = $this->findPlayerInCollection($playersCollection, ['name' => $nome], $teamId, $normalizedRoles['role_main']);
            }

            // D. Salvataggio o Aggiornamento (Unica scrittura DB)
            if (!$player) {
                $player = Player::create([
                    'name' => $nome,
                    'fanta_platform_id' => $fantaPlatformId,
                    'role' => $normalizedRoles['role_main'],
                    'detailed_position' => $normalizedRoles['detailed_position'],
                ]);
                $this->createdCount++;
                // Aggiorniamo la collection in RAM per i prossimi cicli
                $playersCollection->push($player);
            } else {
                // Check Trasferimento (In-Memory)
                $lastRosterTeamId = $prevRosterMap->get($player->id)?->team_id;
                if ($lastRosterTeamId && $lastRosterTeamId != $teamId) {
                    $this->transferCount++;
                } else {
                    $this->confirmedCount++;
                }

                if ($player->trashed()) $player->restore();
                
                $player->update([
                    'name' => (strlen($player->name) < strlen($nome)) ? $nome : $player->name,
                    'role' => $normalizedRoles['role_main'],
                    'detailed_position' => $normalizedRoles['detailed_position']
                ]);
            }

            // E. Collegamento Roster
            \App\Models\PlayerSeasonRoster::updateOrCreate(
                ['player_id' => $player->id, 'season_id' => $this->seasonId],
                [
                    'team_id' => $teamId,
                    'role' => $normalizedRoles['role_main'],
                    'detailed_position' => $normalizedRoles['detailed_position'],
                    'initial_quotation' => (int)($normalizedRow['qti'] ?? $normalizedRow['qt_i'] ?? 0),
                    'current_quotation' => (int)($normalizedRow['qta'] ?? $normalizedRow['qt_a'] ?? 0),
                    'fvm' => (int)($normalizedRow['fvm'] ?? 0),
                ]
            );

            $this->processedPlayerIds[] = $player->id;
        }

        Log::info("--- [ERP-FAST] COMPLETATO: Creati: {$this->createdCount} | Trasferiti: {$this->transferCount} | Confermati: {$this->confirmedCount} ---");
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