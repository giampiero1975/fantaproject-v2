# 🏆 Step 4: Calcolo Tier Squadre

Questo documento descrive il funzionamento dello **Step 4 (Calcolo Tier Squadre)**, il motore nevralgico che suddivide le squadre di Serie A in fasce di rendimento (Tier da 1 a 5).

## 📌 Scopo dello Step
Il motore dei Tier valuta le squadre attive nella stagione corrente e assegna a ciascuna un valore da 1 (Squadre d'Elite) a 5 (Squadre di bassa fascia). Questo punteggio è essenziale per il sistema di proiezioni, in quanto determina i bonus/malus che i giocatori riceveranno in base alla squadra di appartenenza.

## ⚙️ Logica del Motore (Tier Engine)
Il calcolo si basa su un algoritmo ibrido che pondera due fattori principali:
1. **70% Storico (Lookback):** Analizza i piazzamenti in classifica degli ultimi 4 anni (gestiti tramite la funzione di lookback). Squadre con una forte stabilità al vertice mantengono un ranking alto.
2. **30% Momentum (Quotazioni Attuali):** Analizza il valore attuale della rosa, garantendo che squadre emergenti (o neopromosse con grandi investimenti) non vengano penalizzate dall'assenza di uno storico lungo.

Il ricalcolo viene scatenato tramite il comando Artisan dedicato o invocato dai service di backend (es. `TeamDataService::updateTeamTiers()`).

## 📊 Dashboard UI
All'interno della Dashboard, lo Step 4 si presenta con le seguenti metriche:
- **Tier Assegnati:** Mostra il numero di squadre che hanno ricevuto con successo l'assegnazione del Tier rispetto al totale (idealmente 20/20).
- **Bilanciamento Lega:** Una scomposizione competitiva in tre macro-aree: Top (Tier 1 e 2), Mid (Tier 3) e Flop (Tier 4 e 5).
- **Distribuzione Tier:** Una griglia esatta che riporta il conteggio per ogni singolo livello (da T1 a T5), colorato con le scale standard del progetto (Oro, Blu, Grigio, Arancione, Rosso).

> [!NOTE]
> Il calcolo dei Tier per le stagioni passate (tramite `$targetSeason`) sfrutta il `SeasonHelper` per includere correttamente anche le stagioni concluse, permettendo la simulazione retroattiva o la consultazione di archivi storici.

> [!IMPORTANT]
> Lo Step 4 si sblocca solo quando lo Step 3 (Storico Classifiche) ha raggiunto una copertura dati sufficiente.
