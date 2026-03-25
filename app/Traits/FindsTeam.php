<?php

namespace App\Traits;

use App\Models\Team;
use Illuminate\Support\Facades\Log;

trait FindsTeam
{
    /**
     * Cache per le ricerche dei team, per evitare query ripetute.
     * @var array
     */
    private array $teamCache = [];
    
    /**
     * Carica e memorizza la collection di tutte le squadre.
     * Carica le mappe dei nomi dei team per ricerche veloci su corrispondenze esatte.
     */
    public function preloadTeams()
    {
        $this->allTeams = Team::all(['id', 'name', 'short_name']);
        $teams = Team::all(['id', 'name', 'short_name']);
        $this->teamNameIdMap = $teams->pluck('id', 'name')->toArray();
        $this->teamShortNameIdMap = $teams->pluck('id', 'short_name')->toArray();
    }
    
    /**
     * Trova l'ID di un team usando una logica a cascata a 3 livelli con logging dettagliato.
     *
     * @param string|null $rosterTeamName Il nome del team dal file di import.
     * @return int|null
     */
    public function findTeamIdByName(?string $rosterTeamName): ?int
    {
        if (empty(trim($rosterTeamName))) {
            return null;
        }
        
        $trimmedName = trim($rosterTeamName);
        
        if (isset($this->teamCache[$trimmedName])) {
            return $this->teamCache[$trimmedName];
        }
        
        Log::debug("--------------------------------------------------");
        Log::debug("[Team Finder] Inizio nuova ricerca per: '{$trimmedName}'");
        
        $lowerTrimmedName = strtolower($trimmedName);
        Log::debug("[Team Finder] Nome normalizzato per la query: '{$lowerTrimmedName}'");
        
        // --- LIVELLO 1: Corrispondenza Esatta con short_name ---
        Log::debug("[Team Finder] TENTATIVO LIVELLO 1: Ricerca per short_name esatto.");
        $queryL1 = Team::whereNotNull('api_football_data_id')->whereRaw('LOWER(short_name) = ?', [$lowerTrimmedName]);
        Log::debug("[Team Finder] SQL Livello 1: " . $queryL1->toSql() . " | Bindings: " . json_encode($queryL1->getBindings()));
        
        $team = $queryL1->first();
        
        if ($team) {
            Log::info("[Team Finder] ✅ SUCCESSO Livello 1 (Short Name) per '{$trimmedName}' -> Trovato '{$team->name}' (ID: {$team->id})");
            return $this->teamCache[$trimmedName] = $team->id;
        }
        Log::debug("[Team Finder] ❌ Livello 1 fallito.");
        
        // --- LIVELLO 2: Corrispondenza Esatta con il nome completo ---
        Log::debug("[Team Finder] TENTATIVO LIVELLO 2: Ricerca per nome completo esatto.");
        $queryL2 = Team::whereNotNull('api_football_data_id')->whereRaw('LOWER(name) = ?', [$lowerTrimmedName]);
        Log::debug("[Team Finder] SQL Livello 2: " . $queryL2->toSql() . " | Bindings: " . json_encode($queryL2->getBindings()));
        
        $team = $queryL2->first();
        
        if ($team) {
            Log::info("[Team Finder] ✅ SUCCESSO Livello 2 (Nome Completo) per '{$trimmedName}' -> Trovato '{$team->name}' (ID: {$team->id})");
            return $this->teamCache[$trimmedName] = $team->id;
        }
        Log::debug("[Team Finder] ❌ Livello 2 fallito.");
        
        // --- LIVELLO 3: Corrispondenza "Contains" ---
        Log::debug("[Team Finder] TENTATIVO LIVELLO 3: Ricerca per 'contains' su nome completo.");
        $queryL3 = Team::whereNotNull('api_football_data_id')->where('name', 'LIKE', '%' . $trimmedName . '%');
        Log::debug("[Team Finder] SQL Livello 3: " . $queryL3->toSql() . " | Bindings: " . json_encode($queryL3->getBindings()));
        
        $team = $queryL3->first();
        
        if ($team) {
            Log::info("[Team Finder] ✅ SUCCESSO Livello 3 (Contains) per '{$trimmedName}' -> Trovato '{$team->name}' (ID: {$team->id})");
            return $this->teamCache[$trimmedName] = $team->id;
        }
        Log::debug("[Team Finder] ❌ Livello 3 fallito.");
        
        // Se nessun livello ha funzionato, logga l'errore finale.
        Log::error("[Team Finder] ❌ TEAM NON TROVATO per '{$trimmedName}' dopo 3 livelli di ricerca.");
        Log::debug("--------------------------------------------------");
        $this->teamCache[$trimmedName] = null;
        return null;
    }
    
    
}