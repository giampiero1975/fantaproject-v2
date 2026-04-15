<?php

namespace App\Traits;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait FindsPlayerByName
{
    /**
     * [ERP-FAST] Versione In-Memory del matching calciatori.
     * Cerca un giocatore all'interno di una Collection pre-caricata, evitando query N+1.
     */
    public function findPlayerInCollection(\Illuminate\Support\Collection $players, array $criteria, ?int $teamId = null, ?string $role = null): ?Player
    {
        $name = trim((string) ($criteria['name'] ?? ''));
        if ($name === '') return null;

        $nameLower = strtolower($name);

        // --- L1: Esatto + Team ---
        if ($teamId) {
            $player = $players->filter(function($p) use ($name, $teamId) {
                return $p->name === $name && $p->rosters->contains('team_id', $teamId);
            })->first();
            if ($player) return $player;
        }

        // --- L2: Case-insensitive + Team ---
        if ($teamId) {
            $player = $players->filter(function($p) use ($nameLower, $teamId) {
                return strtolower($p->name) === $nameLower && $p->rosters->contains('team_id', $teamId);
            })->first();
            if ($player) return $player;
        }

        // --- L3: Case-insensitive Globale ---
        $player = $players->filter(function($p) use ($nameLower, $role) {
            $match = strtolower($p->name) === $nameLower;
            if ($role) {
                $match = $match && $p->rosters->contains('role', $role);
            }
            return $match;
        })->first();
        if ($player) return $player;

        // --- L4: Algoritmo a Similarità (Sartoriale) ---
        // Scansione della collection in RAM (molto più veloce del Chunk DB)
        foreach ($players as $p) {
            if ($this->namesAreSimilar($name, $p->name)) {
                // Se abbiamo teamId o ruolo, verifichiamo la coerenza se possibile
                if ($teamId && !$p->rosters->contains('team_id', $teamId)) continue;
                if ($role && !$p->rosters->contains('role', $role)) continue;
                
                return $p;
            }
        }

        return null;
    }

    public function findPlayer(array $criteria, ?Team $team = null, ?string $role = null): ?Player
    {
        $name = trim((string) ($criteria['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        // --- L1: Esatto + Team ---
        if ($team) {
            $player = Player::withTrashed()
                ->where('name', $name)
                ->whereHas('rosters', fn($q) => $q->where('team_id', $team->id))
                ->first();
            if ($player) return $player;
        }

        // --- L2: Case-insensitive + Team ---
        if ($team) {
            $player = Player::withTrashed()
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->whereHas('rosters', fn($q) => $q->where('team_id', $team->id))
                ->first();
            if ($player) return $player;
        }

        // --- L4: Algoritmo a Erosione Ibrida (In Stessa Squadra) ---
        if ($team) {
            $teamPlayers = Player::withTrashed()
                ->whereHas('rosters', fn($q) => $q->where('team_id', $team->id))
                ->get();
            foreach ($teamPlayers as $p) {
                if ($this->namesAreSimilar($name, $p->name)) {
                    return $p;
                }
            }
        }

        // --- L3: Case-insensitive Globale ---
        $query = Player::withTrashed()->whereRaw('LOWER(name) = ?', [strtolower($name)]);
        if ($role) {
            $query->whereHas('rosters', fn($q) => $q->where('role', $role));
        }
        $player = $query->first();
        if ($player) return $player;

        // --- L5: Hybrid Erosion Globale ---
        $player = null;
        Player::withTrashed()->chunk(200, function ($players) use ($name, &$player) {
            foreach ($players as $p) {
                if ($this->namesAreSimilar($name, $p->name)) {
                    $player = $p;
                    return false;
                }
            }
        });

        if ($player) return $player;

        return null;
    }

    public function namesAreSimilar(string $name1, string $name2): bool
    {
        $tokens1 = $this->getNormalizedTokens($name1);
        $tokens2 = $this->getNormalizedTokens($name2);
        
        if (empty($tokens1) || empty($tokens2)) return false;

        $shortSet = (count($tokens1) <= count($tokens2)) ? $tokens1 : $tokens2;
        $longSet = ($shortSet === $tokens1) ? $tokens2 : $tokens1;

        $matchedCount = 0;
        $longSetCopy = $longSet;

        foreach ($shortSet as $tokenToFind) {
            $foundIndex = -1;

            foreach ($longSetCopy as $idx => $candidate) {
                if ($tokenToFind === $candidate || 
                    (str_ends_with($tokenToFind, '.') && str_starts_with($candidate, rtrim($tokenToFind, '.'))) ||
                    (str_ends_with($candidate, '.') && str_starts_with($tokenToFind, rtrim($candidate, '.')))
                ) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex === -1) {
                $bestScore = 0;
                foreach ($longSetCopy as $idx => $candidate) {
                    similar_text($tokenToFind, $candidate, $percent);
                    if ($percent > 85 && $percent > $bestScore) {
                        $bestScore = $percent;
                        $foundIndex = $idx;
                    }
                }
            }

            if ($foundIndex !== -1) {
                unset($longSetCopy[$foundIndex]);
                $matchedCount++;
            }
        }

        return $matchedCount === count($shortSet);
    }

    private function getNormalizedTokens(string $name): array
    {
        $name = strtolower(Str::ascii($name));
        $name = str_replace(["'", "-"], " ", $name);
        $name = preg_replace('/[^a-z0-9. ]/', '', $name);
        return array_values(array_filter(explode(' ', $name)));
    }
}
