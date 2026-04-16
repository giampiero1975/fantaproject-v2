<?php

namespace App\Traits;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait FindsPlayerByName
{
    /**
     * [ERP-FAST] Versione In-Memory ad alte prestazioni.
     * Cerca un giocatore usando mappe indicizzate (API ID e Slug) per performance O(1).
     *
     * @param array $registryMap Mappa [api_id => Player]
     * @param array $slugMap     Mappa [slug_nome => Player]
     * @param array $criteria    ['name' => string, 'api_id' => int|null]
     * @param int|null $teamId   Filtro per squadra
     * @param string|null $role  Filtro per ruolo
     */
    public function findPlayerInMaps(array $registryMap, array $slugMap, array $criteria, ?int $teamId = null, ?string $role = null): ?Player
    {
        $apiId = $criteria['api_id'] ?? null;
        $name  = trim((string) ($criteria['name'] ?? ''));

        // 1. Match per API ID (O(1))
        if ($apiId && isset($registryMap[$apiId])) {
            return $registryMap[$apiId];
        }

        if ($name === '') return null;
        $slug = Str::slug($name);

        // 2. Match per Slug Esatto (O(1))
        if (isset($slugMap[$slug])) {
            $candidates = is_array($slugMap[$slug]) ? $slugMap[$slug] : [$slugMap[$slug]];
            foreach ($candidates as $p) {
                // Se abbiamo filtri team o ruolo, proviamo a restringere
                if ($teamId && method_exists($p, 'rosters') && !$p->rosters->contains('team_id', $teamId)) continue;
                if ($role && $p->role !== $role) continue;
                return $p;
            }
        }

        // 3. Fallback Similarità (O(N) - solo su subset se possibile)
        // Nota: Qui usiamo la scansione lineare ma solo se i match diretti falliscono.
        foreach ($slugMap as $s => $pGroup) {
            $p = is_array($pGroup) ? $pGroup[0] : $pGroup;
            if ($this->namesAreSimilar($name, $p->name)) {
                if ($teamId && method_exists($p, 'rosters') && !$p->rosters->contains('team_id', $teamId)) continue;
                if ($role && $p->role !== $role) continue;
                return $p;
            }
        }

        return null;
    }

    /**
     * [ERP-FAST] Versione In-Memory del matching calciatori (Legacy Collection).
     */
    public function findPlayerInCollection(\Illuminate\Support\Collection $players, array $criteria, ?int $teamId = null, ?string $role = null): ?Player
    {
        $name = trim((string) ($criteria['name'] ?? ''));
        if ($name === '') return null;

        $nameLower = strtolower($name);
        $apiId     = $criteria['api_id'] ?? null;

        // --- L0: API ID ---
        if ($apiId) {
            $player = $players->firstWhere('api_football_data_id', $apiId);
            if ($player) return $player;
        }

        // --- L1: Esatto + Team ---
        if ($teamId) {
            $player = $players->filter(function($p) use ($name, $teamId) {
                return $p->name === $name && ($p->relationLoaded('rosters') ? $p->rosters->contains('team_id', $teamId) : true);
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
                // Protezione: Non permettere match di un'iniziale se è l'unico token (es. Nani vs N.)
                $isInitialMatch = (str_ends_with($tokenToFind, '.') && str_starts_with($candidate, rtrim($tokenToFind, '.'))) ||
                                 (str_ends_with($candidate, '.') && str_starts_with($tokenToFind, rtrim($candidate, '.')));
                
                if ($tokenToFind === $candidate) {
                    $foundIndex = $idx;
                    break;
                }

                // Se è un match di iniziale, lo accettiamo solo se il nome ha più di un token
                if ($isInitialMatch && count($tokens1) > 1 && count($tokens2) > 1) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex === -1) {
                $bestScore = 0;
                foreach ($longSetCopy as $idx => $candidate) {
                    similar_text($tokenToFind, $candidate, $percent);
                    // Per i nomi corti (< 4 char), alziamo la soglia al 95%
                    $threshold = (strlen($tokenToFind) < 4) ? 95 : 85;
                    if ($percent > $threshold && $percent > $bestScore) {
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
