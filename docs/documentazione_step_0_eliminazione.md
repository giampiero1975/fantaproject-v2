# Documentazione di Dismissione: Step 0 (Monitor Regionale)

## Informazioni Generali
* **Titolo originale della sezione:** `📡 0. Monitor Regionale (Hub Football-Data)`
* **Data di Eliminazione:** 6 Maggio 2026
* **Stato dell'azione:** Dismesso ed Eliminato dalla Dashboard Principale

---

## 🔍 A cosa serviva lo Step 0?
Lo **Step 0** fungeva da pannello di monitoraggio globale e pre-sincronizzazione per l'integrazione con l'API esterna **Football-Data.org** e la gestione delle richieste proxy.

Nello specifico, permetteva di tenere sotto controllo due metriche fondamentali prima di procedere con gli step successivi:
1. **Stato dell'API di Football-Data:** Mostrava lo stato corrente della connessione e sincronizzazione dei dati di campionato (Lega) con le API esterne.
2. **Disponibilità del Proxy:** Indicava la percentuale residua di chiamate proxy disponibili tramite il servizio `ProxyManagerService` (ad es. per evitare il superamento dei limiti di rate limiting imposti dall'API provider).
3. **Sincronizzazione della Struttura:** Forniva il pulsante interattivo **"Sincronizza Struttura"**, che consentiva l'aggiornamento forzato delle stagioni e dei dati base della Serie A direttamente dalle API esterne.

---

## 🛠️ Cosa faceva a livello di Codice (Backend e Logic)?

### 1. Variabili di Stato (recuperate nel Controller `Dashboard.php`):
* `$seasonStatus`: Oggetto di stato ricavato dal monitor delle stagioni (`SeasonMonitorService`).
* `$seasonStatusLabel`: L'etichetta descrittiva dello stato (es. "IN CORSO", "OK", "MANCANTE").
* `$proxyStatus`: Dati estratti da `ProxyManagerService` contenenti l'utilizzo corrente e la quota disponibile dei proxy.

### 2. Azione di Sincronizzazione (`triggerSeasonSync`):
Quando un amministratore cliccava su **"Sincronizza Struttura"**, il componente eseguiva il seguente flusso:
```php
public function triggerSeasonSync()
{
    try {
        $targetSeason = SeasonHelper::getCurrentSeason();
        \Illuminate\Support\Facades\Artisan::call('football:sync-serie-a', [
            'season_year' => $targetSeason
        ]);
        
        Notification::make()
            ->title('Sincronizzazione Completata')
            ->body('Tutti i record snapshot e le stagioni sono stati aggiornati.')
            ->success()
            ->send();
            
        return redirect(\App\Filament\Pages\Dashboard::getUrl());
    } catch (\Exception $e) {
        Notification::make()
            ->title('Errore Sincronizzazione')
            ->body($e->getMessage())
            ->danger()
            ->send();
    }
}
```
Questo comando Artisan (`football:sync-serie-a`) allineava gli ID della Serie A e configurava le stagioni base per la stagione attiva corrente.

---

## 🎯 Perché è stato eliminato?
1. **Riorganizzazione Logica (Coerenza BI):** Lo Step 1 (Gestione Stagioni) rappresenta ora il vero e proprio punto di partenza per l'amministratore, integrando già al suo interno informazioni di allineamento API e timeline target più dettagliate e chiare.
2. **Semplificazione dell'Interfaccia:** La barra del proxy e il monitor del gateway regionale sono stati ritenuti ridondanti o non coerenti con il flusso guidato a step successivi (da 1 a 7) dedicato alla configurazione del database di gioco.
3. **Mantenimento delle Funzioni Sottostanti:** Sebbene la vista dello Step 0 sia stata rimossa, il comando Artisan `football:sync-serie-a` e il relativo controller rimangono attivi a livello di backend per qualsiasi uso automatizzato di sincronizzazione o chiamate API di background.
