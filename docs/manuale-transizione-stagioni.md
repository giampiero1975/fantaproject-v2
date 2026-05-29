# Documentazione: Gestione Stagioni e Tier Engine (Step 3 & 4)

## Informazioni Generali
* **Titolo originale della sezione:** `Gestione Stagioni e Motore Tier`
* **Data di Aggiornamento:** 29 Maggio 2026
* **Stato dell'azione:** Ottimizzato e Attivo in Produzione

---

## 🔍 Cos'è la Transizione Dinamica?
In FantaProject v2 abbiamo eliminato la dipendenza da date "hardcoded" o rigide per il passaggio da una stagione all'altra.
Il sistema adesso capisce automaticamente in che anno calcistico si trova e adatta di conseguenza tutti i moduli che dipendono dal passato (come lo Storico Classifiche e il Tier Engine).

---

## 🛠️ Cosa fa a livello di Codice (Backend e Logic)?

### 1. Il Cuore: `SeasonHelper`
La classe `App\Helpers\SeasonHelper` è l'hub centrale del tempo:
* `getCurrentSeason()`: Calcola l'anno in base al mese (prima di Agosto = stagione precedente).
* `getCompletedLookbackSeasons($years)`: Restituisce l'elenco delle stagioni concluse, interrogando il database (`end_date`). Se una stagione è finita (es. a fine Maggio), scala in avanti includendo i risultati freschissimi.

### 2. Dashboard: `SeasonAlertWidget`
Nello Step 1 della Dashboard, un banner intelligente compare automaticamente tra Maggio e Luglio se la stagione attuale è finita ma risulta ancora attiva nel DB.
Cliccando il pulsante, il widget:
1. Imposta `is_current = false` alla vecchia annata.
2. Crea (o aggiorna) la nuova annata con `is_current = true`.

### 3. Step 3 (Storico) e Step 4 (Tier Engine)
I motori di calcolo non sono più legati a sottrazioni manuali dell'anno (es. `Stagione Attuale - 1`).
Adesso si appoggiano esclusivamente al `SeasonHelper::getCompletedLookbackSeasons()`. Questo garantisce un allineamento millimetrico: non appena chiudi la stagione a Maggio, i Tier ricalcolati includeranno istantaneamente quell'anno appena concluso.

---

## 🎯 Perché è stato strutturato così?
1. **Nessuna manutenzione manuale:** Elimina il rischio di avere bug di "sfasamento" o "off-by-one error" durante il periodo di transizione estivo (Maggio-Agosto).
2. **Robustezza:** Il Tier Engine (che usa un mix Ibrido 70% Storico e 30% Momentum) riceve sempre e solo l'array di stagioni esatto e validato.

---

## ⚠️ Note Operative e Log
* **Log Fisici:** Ogni calcolo del Tier Engine viene tracciato analiticamente in `storage/logs/Tiers/TeamsUpdateTiers.log` (separato dal log generico di Laravel).
* **Modifiche future:** Se la FIGC dovesse cambiare i mesi di inizio campionato, basterà ritoccare unicamente il `SeasonHelper` e tutto l'ecosistema a cascata si allineerà da solo.
