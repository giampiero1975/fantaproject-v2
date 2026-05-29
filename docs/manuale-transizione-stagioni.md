# Manuale: Transizione Stagioni e Tier Engine

Questo documento descrive il funzionamento dell'architettura dinamica implementata in FantaProject v2 per gestire automaticamente i cambi di stagione, le chiusure anticipate dei campionati e il calcolo dinamico dello storico per il Tier Engine.

## 1. Architettura del Tempo (`SeasonHelper`)
Il cuore dell'applicazione per la gestione del tempo è la classe `App\Helpers\SeasonHelper`. È stata progettata per eliminare gli anni hardcoded dal sistema.

*   `getCurrentSeason()`: Restituisce l'anno della stagione corrente in base al mese attuale. Se siamo prima di Agosto (mese < 8), la stagione è ancora l'anno precedente (es. a Maggio 2026, restituisce 2025 per la stagione 2025/26). Ad Agosto, scatta automaticamente all'anno nuovo.
*   `getCompletedLookbackSeasons($years)`: Restituisce in modo intelligente le stagioni *concluse*. Verifica il campo `end_date` della stagione nel database. Se la data di fine è passata (es. fine Maggio), considera la stagione corrente come conclusa e la include nel lookback. Se il campionato è ancora in corso (es. Gennaio), scala indietro di un ulteriore anno.

## 2. Transizione Chiusura Stagione
Sulla Dashboard (Step 1) è presente il `SeasonAlertWidget`. 
*   **Condizione di attivazione:** Se il mese attuale è compreso tra Maggio e Luglio, e la stagione marcata come `is_current = true` nel database ha un anno uguale a quello corrente (es. 2025 in Maggio 2026), compare il banner di chiusura.
*   **Azione:** Cliccando il pulsante, il sistema disattiva `is_current` sulla vecchia annata e la imposta a `true` per la nuova (es. 2026/2027), creando il record se non esiste.

## 3. Tier Engine (Step 4) e Storico Classifiche (Step 3)
Entrambi i moduli dipendono al 100% dal `SeasonHelper`.
*   **Nessun anno cablato:** Il `TeamDataService`, quando scarica lo storico (Step 3) o calcola i Tier (Step 4), invoca `array_keys(SeasonHelper::getCompletedLookbackSeasons($maxLookback))`. 
*   **Sincronia Perfetta:** Questo assicura che se il campionato è terminato e l'utente avvia il ricalcolo a fine Maggio, l'algoritmo includerà immediatamente la stagione appena conclusa nei suoi pesi (es. analizzando 2025, 2024, 2023, 2022).
*   **Log Fisici:** Le operazioni del Tier Engine non inquinano il `laravel.log`. Un file dedicato e isolato viene generato in `storage/logs/Tiers/TeamsUpdateTiers.log` dove è possibile ispezionare nel dettaglio i pesi applicati (Storico/Momentum) per ogni singola squadra calcolata.

## Note di Manutenzione
Qualsiasi fix o variazione alle logiche temporali (es. posticipare l'inizio della stagione a Settembre) deve essere fatta esclusivamente modificando `App\Helpers\SeasonHelper`, e l'intero ecosistema (Scraper, Dashboard, Tier Engine) si adatterà di conseguenza senza alcun altro intervento sul codice.
