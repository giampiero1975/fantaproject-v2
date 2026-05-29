# 🧑‍🤝‍🧑 Step 6: Anagrafica Calciatori

Questo documento descrive il funzionamento dello **Step 6 (Anagrafica Calciatori)**, che costituisce il core dell'anagrafica centralizzata di FantaProject v2.

## 📌 Scopo dello Step
Lo Step 6 non richiede un'azione di importazione diretta (poiché i giocatori vengono caricati tramite lo Step 5), ma funge da centro di controllo e monitoraggio per verificare lo stato di salute del database dei giocatori.
Qui è possibile visualizzare l'elenco completo dei calciatori, lo storico dei trasferimenti, i ruoli e la copertura degli ID esterni (sia Fantapiattaforma che FBref).

## ⚙️ Struttura Dati
La gestione si basa sulla tabella `players`, che mantiene l'anagrafica "agnostica" e persistente.
- **Dati Base:** Nome, data di nascita, ruoli normalizzati (Classic e Mantra).
- **Relazioni:**
  - `parentTeam`: La squadra proprietaria del cartellino.
  - `rosters`: Uno-a-molti verso `player_season_rosters`, che traccia per ogni stagione in quale squadra milita il giocatore e quali sono le sue quotazioni.
  - `latestRoster`: Recupera velocemente la squadra attuale in base alla stagione più recente.
- **Soft Delete:** Se un giocatore lascia la Serie A, non viene mai cancellato fisicamente dal database (per preservare lo storico voti e classifiche passate). Viene messo in stato "Ceduto" (Soft Delete).

## 📊 Dashboard UI
All'interno della Dashboard, lo Step 6 è presentato come un accordion contenente le seguenti metriche di salute:
- **Calciatori in Database:** Il totale assoluto delle anagrafiche attive generate o mantenute dal sistema. Il superamento della soglia dei 400 giocatori attesta un database sufficientemente popolato.
- **ID Fantapiattaforma:** Mostra quanti di questi giocatori sono correttamente mappati con l'ID ufficiale del provider (es. Fantagazzetta/Leghe). Questo numero deve tendere al 100% per garantire che tutti i giocatori a listone possano ricevere voti e quote.

## 🛠️ Pannello Amministrativo (Filament Resource)
Cliccando su "Gestisci Anagrafiche" si accede al `PlayerResource`. Questo pannello offre strumenti avanzati:
- **Filtro Storico Roster:** Permette di cercare i giocatori in base alla squadra in cui militavano in una *specifica stagione* passata.
- **Filtro FBref Status:** Permette di individuare a colpo d'occhio i calciatori che non sono ancora stati mappati con il provider di statistiche avanzate (utile in preparazione dello Step 7).
- **Gestione Ceduti:** Tramite i filtri di default, è possibile visualizzare e ripristinare manualmente i calciatori finiti in Soft Delete.

> [!NOTE]
> Lo Step 6 si sblocca automaticamente e diviene interattivo solo dopo aver completato con successo l'importazione base del Listone (Step 5).
