# 📈 Step 5: Importazione Listone Quotazioni

Questo documento illustra il funzionamento dello **Step 5 (Importazione Listone Quotazioni)**, responsabile del caricamento del roster ufficiale dei calciatori (Listone) all'interno dell'applicativo.

## 📌 Scopo dello Step
Lo Step 5 popola il database con i calciatori ufficiali riconosciuti per la stagione in corso, inclusi i loro ruoli (Classic/Mantra), le quotazioni iniziali e il Valore FVM. Senza questo caricamento, il sistema non possiede i giocatori su cui calcolare le proiezioni o eseguire le simulazioni d'asta.

## ⚙️ Funzionamento Tecnico
L'importazione si avvia caricando il file Excel ufficiale scaricato da Fantagazzetta. Il processo avviene nella classe `ImportaListone.php` ed esegue i seguenti passaggi:
1. **Selezione Stagione:** Il sistema seleziona di default la stagione corrente (`is_current = true`), ma permette di forzare l'import su stagioni passate.
2. **Lettura Excel (Maatwebsite/Excel):** Viene letto il foglio "Tutti" per recuperare dati anagrafici, team di appartenenza e quotazioni.
3. **Creazione/Aggiornamento:** Se il giocatore esiste già (matching per nome) viene aggiornato, altrimenti viene creato. I cambi di maglia estivi o invernali vengono intercettati e processati.
4. **Pulizia Ceduti (Cleanup):** Il sistema effettua un check globale: tutti i giocatori precedentemente presenti ma assenti nel nuovo file caricato vengono etichettati come "Ceduti" o messi fuori rosa (Soft Delete/Rimozione associazione).

## 📊 Dashboard UI
Nella schermata principale, l'accordion dello Step 5 presenta le seguenti card:
- **Giocatori a Listone:** Contatore dei giocatori totali importati. Il target minimo atteso è di 400 giocatori (per considerare il listone "pieno"). Una barra di avanzamento mostra la percentuale di completamento.
- **Orfani (Senza Squadra):** Indica quanti giocatori a listone non sono stati agganciati con successo al loro club in Serie A. Cause comuni includono nomi di squadre differenti (es. "Inter" vs "Internazionale") o giocatori temporaneamente svincolati. È fondamentale mantenere questo numero a 0.
- **Ultimo Caricamento Excel:** Riporta la data e l'ora (dal database log `roster_quotazioni`) in cui l'ultimo listone è stato importato con successo.

> [!WARNING]
> La mancata importazione del Listone (Step 5 incompleto) blocca il successivo Step 6, poiché le anagrafiche dei calciatori risultano vuote o parziali.
