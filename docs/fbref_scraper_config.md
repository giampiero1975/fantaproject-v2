## Infrastruttura di Test (TDD)

Per garantire la stabilità del parser senza consumare crediti ScraperAPI, il progetto adotta un approccio TDD basato su fixture locali.

### Componenti del Test
- **Fixture**: I file HTML reali vengono salvati in `tests/fixtures/` (es. `venezia_21_22.html`).
- **Http::fake()**: Utilizzato per intercettare le chiamate a `api.scraperapi.com` e restituire il contenuto della fixture invece di effettuare una richiesta reale.
- **Mocking**: Il `ProxyManagerService` viene mockato per restituire un record di proxy fittizio durante i test, evitando dipendenze dal database reale.

### Comando di Test
Per validare il parser e la logica di mapping:
```bash
php artisan test tests/Feature/FbrefParserTest.php
```

### Logica Dry Run
Il sistema di mapping supporta una modalità `dryRun`. Se attivata (passando `true` a `syncPlayersData`), il servizio esegue tutto il processo di confronto e produce i log di matching, ma **non effettua scritture** sulla tabella `players`. Questo è fondamentale per validare la qualità dei match fuzzy prima del Go-Live.

## Persistenza Configurazione (Database)

La configurazione dei proxy non è più hardcoded nel codice ma viene gestita tramite la tabella `proxy_services`.

### Colonna `default_params`
Abbiamo introdotto una colonna JSON `default_params` che permette di definire parametri obbligatori per provider.
Esempio per ScraperAPI (ID 8):
```json
{
  "country_code": "it",
  "timeout": 120000
}
```
Questi valori vengono letti da `ScraperApiProvider` e uniti automaticamente alla query string di ogni richiesta.

## Matching e Similarità

Il sistema utilizza l'helper centralizzato del progetto tramite il Trait `App\Traits\FindsPlayerByName`.

### Funzionamento
La logica di confronto (`namesAreSimilar`) esegue:
1. **Tokenizzazione**: Scompone i nomi in parole singole.
2. **Normalizzazione**: Rimuove accenti (ASCII), punteggiatura e converte in minuscolo.
3. **Word-Set Matching**: Verifica se i token di un nome sono presenti nell'altro, gestendo automaticamente le inversioni (es. "Ceccaroni Pietro" vs "Pietro Ceccaroni").
4. **Soglia di Similarità**: Utilizza un algoritmo ibrido con soglie validate per minimizzare i falsi positivi (es. evitando match errati tra calciatori con lo stesso nome di battesimo).

