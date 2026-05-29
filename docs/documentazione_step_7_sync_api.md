# Documentazione Step 7: Sincronizzazione API-Football

## 1. Obiettivo dello Step
Lo **Step 7** è lo snodo fondamentale per collegare i calciatori dell'anagrafica (caricati dal Listone ufficiale) al mondo dei dati reali.
Tramite l'integrazione con *API-Football*, questo step associa l'ID univoco fornito dal provider di dati sportivi (`api_football_data_id`) alla scheda anagrafica del calciatore. Senza questa mappatura, i cronjob settimanali non saprebbero a chi assegnare voti, ammonizioni, gol e presenze.

## 2. Struttura dei Dati
Il campo cruciale che viene popolato è:
- `players.api_football_data_id`: Identificativo numerico fornito dalle API per il calciatore.

L'operazione è tracciata tramite la tabella `import_logs` con `import_type` impostato a `sync_rose_api`.

## 3. Logica di Sincronizzazione (Motore Ibrido)
La procedura di sincronizzazione sfrutta un algoritmo di fuzzy matching (basato su Levenshtein o similari) per incrociare:
- I nomi provenienti dal Listone (spesso troncati o stilizzati, es. "Kvaratskhelia K.").
- I nomi forniti da API-Football (spesso completi, es. "Khvicha Kvaratskhelia").
L'algoritmo restringe la ricerca ai soli calciatori che militano in una determinata squadra per evitare casi di omonimia tra team diversi.

## 4. Comportamento nella Dashboard
Nello Step 7 (attualmente accessibile in Dashboard dopo il completamento dello Step 6), la UI analizza:
1. **Copertura ID API**: Numero di giocatori che possiedono un `api_football_data_id` non nullo rispetto al totale dei giocatori a listone (`fanta_platform_id` non nullo).
2. **Percentuale di Successo**: Viene calcolata e mostrata la percentuale (`$pct`). Se il valore scende sotto il **90%**, viene evidenziata una potenziale criticità (semaforo rosso/ambra).
3. **Ultimo Sync Massivo**: Data e ora dell'ultima esecuzione coronata da successo.

## 5. Stato (Semaforo)
Il badge dello Step 7 nella Dashboard assume i seguenti stati:
- **BLOCCATO**: Lo step precedente (6. Calciatori) non ha raggiunto la quota minima di 400 anagrafiche.
- **MISSING / VUOTO**: Nessun giocatore mappato (0%).
- **PARZIALE**: Il sync è stato avviato, ma la percentuale di giocatori mappati è inferiore al 90%. Richiede intervento manuale per gli "orfani" (giocatori il cui nome era troppo diverso per il fuzzy matching).
- **COMPLETO**: Percentuale di mappatura $\ge$ 90%. Il sistema è pronto per ricevere statistiche in tempo reale.
