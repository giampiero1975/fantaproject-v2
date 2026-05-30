# Execution Plan: FBref Sync v2.0 Refactoring

- [x] 1. Pulizia `FbrefScrapingService.php`
  - [x] Rimuovere `dispatchScrape`
  - [x] Rimuovere `executeDirectScrape`
  - [x] Rimuovere `executePipelineScrape`
- [x] 2. Allineamento `FbrefSurgicalTeamSync.php`
  - [x] Rimuovere la chiamata a `dispatchScrape`
  - [x] Inserire la logica proxy e scraping diretto (simile a `FbrefSurgicalSeasonSync.php`)
- [x] 3. Verifica (`--dry-run` test)
- [ ] 4. Segnalazione in UI per Sync Massivi Eseguiti
  - Inserire un indicatore nella dashboard Filament per mostrare quando un allineamento massivo è già stato lanciato per una specifica stagione (es. copertura al 94.6%).
  - L'obiettivo è prevenire lanci duplicati accidentali e spreco di chiamate API (crediti) preziose.
- [x] 5. Indagine Discrepanza Dati UI (Bug)
  - La UI per Cremonese 25/26 segna 26 calciatori con 21 `fbref_id` valorizzati (Gap di 5).
  - Tuttavia, nel dettaglio slider dei mancanti in globale compaiono solo 2 calciatori.
  - Risolto: corretto il parametro `trashed => withTrashed` nel file blade per includere correttamente i giocatori soft-deleted con Fanta ID nello slider globale.
- [ ] 6. Restyling Widget Dashboard per Step 7 e 8
  - Rivedere le card della Dashboard relative allo Step 7 (Sync Rose API-Football) e Step 8 (Sync FBref).
  - L'obiettivo è renderle più "parlanti", creando eventualmente una card dedicata per ogni singola specifica/metrica dei relativi step.
