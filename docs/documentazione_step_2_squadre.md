# Documentazione Step 2: Anagrafica Squadre

## 1. Obiettivo dello Step
Lo **Step 2** ha lo scopo di preparare, popolare e validare l'anagrafica di base dei club di Serie A. 
Senza le squadre, nessuna anagrafica giocatori o calcolo statistico può avere fondamenta solide. Questo step funge da prerequisito vitale per tutti gli automatismi successivi.

## 2. Struttura dei Dati
I dati vengono salvati nella tabella `teams`, la quale funge da collettore unico.
I campi principali includono:
- **Dati Anagrafici Base**: `name`, `logo_path`.
- **Integrazioni Esterne**: 
  - `api_football_data_id`: Collegamento per lo scaricamento delle statistiche reali via API esterne.
  - `fbref_url`: URL della pagina FBref (usato successivamente per raschiare i dati storici delle classifiche).
- **Ranking / Potenziale**: `tier_globale`, e i vari punteggi settoriali (Portieri, Difesa, Centrocampo, Attacco) che vengono poi arricchiti da step successivi.

## 3. Comportamento nella Dashboard
Nello Step 2 la Dashboard analizza le metriche essenziali:
1. **Squadre Partecipanti**: Il numero totale di squadre in anagrafica (il target minimo e ideale per la Serie A è 20).
2. **Copertura ID API**: Indica la percentuale di squadre correttamente mappate con un ID valido per l'integrazione di `api-football`. Questo garantisce che i voti e le presenze possano essere sincronizzati.
3. **Validazione Mappatura FBRef**: Verifica che tutte le squadre abbiano un link valido per permettere lo scraping dello storico delle classifiche (Step 3).

## 4. Stato (Semaforo)
Il badge dello Step 2 nella Dashboard riflette i seguenti stati:
- **BLOCCATO**: Se lo step precedente (Stagioni) non è completo.
- **MISSING / VUOTO**: Nessuna squadra presente in anagrafica.
- **PARZIALE**: Sono presenti squadre, ma non si è raggiunto il target di 20 oppure la mappatura API/FBRef è incompleta.
- **COMPLETO**: Esattamente 20 squadre in DB, 100% di copertura API e 100% di mappatura FBRef valida.

## 5. Manutenzione
In caso di discrepanze (es. promosse/retrocesse o cambi di naming), l'amministratore interviene tramite la risorsa Filament `TeamResource` per aggiustare l'ID API o aggiornare il link FBRef. La dashboard intercetterà automaticamente il dato sistemato portando lo stato a COMPLETO.
