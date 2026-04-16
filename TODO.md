# FANTAPROJECT-V2 TODO LIST

Questo documento serve a tracciare i cambiamenti significativi, le evoluzioni logiche e le implementazioni future per il progetto.

## 🚀 Priorità Immediate (Ready for Action)
- [x] **Completamento ERP-Fast (Roster Sync)**: Refactoring del comando `players:historical-sync` per caricamento massivo in memoria.
- [x] **Fix Migration Tests**: Risolvere l'errore di sintassi nel file `2026_01_22_add_foreign_keys_to_historical_player_stats_table` che blocca i test SQLite.
- [ ] **Data Integrity Check**: Verificare la parità tra i dati FBref e i dati Listone per la stagione 2024/25.

## 🛠️ Evoluzioni Logiche & Architetturali
- [ ] **Nuovo Sistema di Ponderazione Tier**: Integrare pesi dinamici basati sulla regressione lineare dei voti storici (Power vs Pro).
- [ ] **Nuovo Sistema di Ponderazione Tier**: Integrare pesi dinamici basati sulla regressione lineare dei voti storici (Power vs Pro).
- [ ] **Dashboard Filament (Live Logs)**: Implementare un componente Echo o polling per mostrare i progressi di importazione in tempo reale senza ricaricare la pagina.

## 🧪 Testing & Qualità
- [ ] **Copertura Unit**: Portare la copertura dei Services a >90%.
- [ ] **Stress Test Proxy**: Validare la rotazione dei proxy su 500 chiamate consecutive.

## 📝 Note & Appunti
- *Nota 2026-04-16*: Valutare se spostare il registro Orfani su Redis per velocità di matching globale estrema.
- *Nota Diagnostica 2026-04-16*:
    - **Totale Calciatori**: 1622
    - **Zombie**: 110 (Senza Fanta ID e senza Roster).
    - **Scollegati**: 0 (Dopo il sync massivo 21/22, Koulibaly, Radu, Insigne, Mertens e altri big storici sono stati correttamente ri-collegati).
    - **Integrità**: Bonificati 227 record orfani in `player_season_roster`. Implementato vincolo `ON DELETE CASCADE` tra `players` e `rosters`.

# 📝 TODO LIST: PROGETTO ERP-FAST (V2)

## 🏎️ FASE 1: Il Motore (Performance & In-Memory)
- [x] **Refactoring Importer (Step 9)**: Completare il passaggio a `ToCollection`. Il caricamento dei file Excel deve avvenire interamente in RAM per abbattere le migliaia di query al database.
- [x] **Ottimizzazione Trait**: Modificare `FindsPlayerByName` affinché lavori sulle Collection di Laravel, eliminando i `chunk(200)` e le query ricorsive durante il matching.
- [x] **Fix Tier Logic**: Inter stabilizzato a Tier 1 (Gold Standard).

## 🧹 FASE 2: Pulizia Anagrafica (Sartoriale)
- [x] **Bonifica "Zombie"**: Identificare e cancellare fisicamente i record nati da API-Football che non hanno `fanta_id` e non sono mai stati inseriti in un roster (solo rumore di fondo).
- [x] **Sync "Match o Nulla"**: Modificare il flusso di API-Football: il sistema deve solo unire i dati a giocatori esistenti. Se non c'è un `fanta_id`, non deve creare nuovi record.

## 🏛️ FASE 3: Patrimonio Storico (Step 9 - History Stats)
- [ ] **Creazione "Nuda" da Stats**: Se l'importatore dello storico trova un giocatore con voti/gol non presente in anagrafica:
    - Creare il record Player (Nome, Ruolo, Squadra).
    - Agganciarlo automaticamente al Roster della stagione di riferimento.
    - Lasciare `api_id` e `fbref_id` vuoti (da gestire in Fase 4).
- [x] **Switch Dashboard Copertura**: Perfezionata la logica **BUCHI / DATI**. La dashboard ora monitora correttamente la copertura reale (inclusi calciatori ceduti/soft-deleted) con widget aggregati e panel di audit filtrabili.

## 🛰️ FASE 4: Audit Identità (L'ex Step 8)
- [x] **Tabella Audit External IDs**: Creare una vista dedicata (Audit Identità) per elencare tutti i giocatori che hanno statistiche ma sono "scollegati" dal mondo esterno (senza `api_id` o `fbref_id`).
- [ ] **Integrazione Dashboard**: Perfezionare la logica delle stats e dei widget per una "torre di controllo" definitiva.
- [x] **Data Integrity Check**: Verificare la parità tra i dati FBref e i dati Listone per la stagione 2024/25.

## 📂 FASE 5: Riorganizzazione Finale (User Experience)
- [ ] **Restyling Menù**: Solo a fine lavori, raggruppare le voci per aree logiche (Anagrafica, Sincronizzazione, Patrimonio Storico).
- [ ] **Mantenimento Sequenza**: Preservare la numerazione cronologica degli Step (1-9) per non perdere il filo logico delle operazioni necessarie.

---
**Filosofia del Progetto:** *Il database deve essere "Fanta-centrico". Entrano nel sistema solo i calciatori che hanno un'identità nel Fantacalcio (Fanta ID) o che hanno lasciato una traccia reale nella storia (Stats/Voti). Tutto il resto è scarto da eliminare.*
