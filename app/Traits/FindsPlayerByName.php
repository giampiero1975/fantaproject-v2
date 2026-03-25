<?php

namespace App\Traits;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

/**
 * Trait FindsPlayerByName
 *
 * Provides a smart, multi-level player lookup by name (+ optional team and role).
 * Used by import classes (e.g. TuttiSheetImport) as a fallback when no
 * fanta_platform_id match is found.
 *
 * Call signature used from the importer:
 *   $this->findPlayer(['name' => $nome], $teamModel, $role)
 */
trait FindsPlayerByName
{
    /**
     * Find a player record using a cascading strategy:
     *   L1 – exact name + team_id
     *   L2 – case-insensitive name + team_id
     *   L3 – case-insensitive name only (across all teams)
     *
     * Soft-deleted records are included so the importer can restore them.
     *
     * @param  array       $criteria  Associative array, must contain 'name' key.
     * @param  Team|null   $team      Team model to restrict search (optional).
     * @param  string|null $role      Role character (P/D/C/A) for tie-breaking (optional).
     * @return Player|null
     */
    public function findPlayer(array $criteria, ?Team $team = null, ?string $role = null): ?Player
    {
        $name = trim((string) ($criteria['name'] ?? ''));

        if ($name === '') {
            Log::warning('[FindsPlayerByName] findPlayer() called with empty name.');
            return null;
        }

        Log::debug("[FindsPlayerByName] Inizio ricerca per nome: '{$name}'" . ($team ? " | team: '{$team->name}'" : '') . ($role ? " | ruolo: {$role}" : ''));

        // ── L1: exact name + team_id ──────────────────────────────────────────
        if ($team) {
            $player = Player::withTrashed()
                ->where('name', $name)
                ->where('team_id', $team->id)
                ->first();

            if ($player) {
                Log::info("[FindsPlayerByName] ✅ L1 (exact name+team) → ID {$player->id}");
                return $player;
            }
        }

        // ── L2: case-insensitive name + team_id ──────────────────────────────
        if ($team) {
            $player = Player::withTrashed()
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->where('team_id', $team->id)
                ->first();

            if ($player) {
                Log::info("[FindsPlayerByName] ✅ L2 (icase name+team) → ID {$player->id}");
                return $player;
            }
        }

        // ── L3: case-insensitive name, all teams (role tie-breaking) ─────────
        $query = Player::withTrashed()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)]);

        if ($role) {
            $query->where('role', $role);
        }

        $player = $query->first();

        if ($player) {
            Log::info("[FindsPlayerByName] ✅ L3 (icase name global" . ($role ? "+role" : '') . ") → ID {$player->id}");
            return $player;
        }

        Log::warning("[FindsPlayerByName] ❌ Nessun giocatore trovato per '{$name}'" . ($team ? " (team: {$team->name})" : ''));
        return null;
    }
}
