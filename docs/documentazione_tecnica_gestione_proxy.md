# Guida: Come Aggiungere un Nuovo Proxy

> Ultima revisione: 2026-04-07
> Architettura: `ProxyProviderInterface` + `ProxyManagerService` + DB `proxy_services`

---

## Architettura in breve

Il sistema proxy è composto da 3 layer:

```
DB (proxy_services)
    └── ProxyManagerService        ← orchestratore, rotazione e fallback
            └── ProxyProviderInterface
                    ├── ScraperApiProvider.php
                    ├── ScrapingBeeProvider.php
                    └── ZenRowsProvider.php
```

Il `ProxyManagerService::$providers[]` mappa il campo `name` del DB alla classe PHP corrispondente.
La rotazione sceglie il proxy con **priority più bassa** che ha ancora crediti e non è marcato inaffidabile.

---

## CASO A — Nuovo account di un provider già integrato
### (es. terzo account ScraperAPI, secondo account ScrapingBee)

**✅ Basta solo aggiungere un record nel DB. Nessun file PHP da toccare.**

Accedi a Filament → Proxy Services → "Nuovo" oppure usa Tinker:

```php
php artisan tinker
```

```php
\App\Models\ProxyService::create([
    'name'             => 'ScraperAPI',           // DEVE matchare esattamente la chiave in $providers[]
    'base_url'         => 'http://api.scraperapi.com/',
    'account_endpoint' => 'http://api.scraperapi.com/account',
    'api_key'          => 'LA-NUOVA-API-KEY',
    'limit_monthly'    => 1000,
    'current_usage'    => 0,
    'is_active'        => true,
    'priority'         => 5,                      // numero più alto = ultimo nella cascata
]);
```

> **Regola chiave**: il campo `name` nel DB deve corrispondere **esattamente** alla chiave dell'array
> `ProxyManagerService::$providers`. È questo che determina quale classe PHP viene usata.

---

## CASO B — Provider totalmente nuovo
### (es. Oxylabs, BrightData, Webshare, ecc.)

Servono **3 step**:

### Step 1 — Crea il file Provider

```
app/Services/ProxyProviders/OxylabsProvider.php
```

Implementa l'interfaccia `ProxyProviderInterface`:

```php
<?php
namespace App\Services\ProxyProviders;

use App\Contracts\ProxyProviderInterface;
use App\Models\ProxyService;
use Illuminate\Support\Facades\Http;

class OxylabsProvider implements ProxyProviderInterface
{
    public function getProxyUrl(ProxyService $proxy, string $targetUrl): string
    {
        // Costruisci l'URL API del provider
        return 'https://realtime.oxylabs.io/v1/...' . '?' . http_build_query([
            'api_key' => $proxy->api_key,
            'url'     => $targetUrl,
        ]);
    }

    public function checkBalance(ProxyService $proxy): array
    {
        $response = Http::timeout(10)->get('https://api.oxylabs.io/v1/stats', [
            'api_key' => $proxy->api_key,
        ]);

        $data = $response->json();
        return [
            'used'      => (int)($data['used'] ?? 0),
            'limit'     => (int)($data['limit'] ?? $proxy->limit_monthly),
            'remaining' => (int)($data['remaining'] ?? 0),
        ];
    }
}
```

### Step 2 — Registra in `ProxyManagerService`

Apri `app/Services/ProxyManagerService.php` e aggiungi la riga nell'array `$providers`:

```php
protected array $providers = [
    'ScraperAPI'  => \App\Services\ProxyProviders\ScraperApiProvider::class,
    'ScrapingBee' => \App\Services\ProxyProviders\ScrapingBeeProvider::class,
    'ZenRows'     => \App\Services\ProxyProviders\ZenRowsProvider::class,
    'Oxylabs'     => \App\Services\ProxyProviders\OxylabsProvider::class,  // ← NUOVO
];
```

### Step 3 — Aggiungi il record nel DB

```php
\App\Models\ProxyService::create([
    'name'      => 'Oxylabs',   // DEVE matchare la chiave in $providers[]
    'api_key'   => 'LA-TUA-KEY',
    'base_url'  => 'https://realtime.oxylabs.io/v1/...',
    'limit_monthly' => 25000,
    'current_usage' => 0,
    'is_active' => true,
    'priority'  => 5,
]);
```

---

## Tabella riepilogativa

| Scenario | File PHP | ProxyManagerService | DB record |
|---|---|---|---|
| Nuovo account, stesso brand | ❌ No | ❌ No | ✅ Sì |
| Brand totalmente nuovo | ✅ Sì | ✅ Sì | ✅ Sì |

---

## Stato attuale cascata (2026-04-07)

| Priority | ID | Provider | Crediti Rim. | Note |
|---|---|---|---|---|
| 1 | 7 | ZenRows | ~1000 | ⚠️ Trial scade tra 3 gg |
| 2 | 6 | ScrapingBee | ~985 | — |
| 3 | 3 | ScraperAPI (account 1) | ~140 | — |
| 4 | 8 | ScraperAPI (account 2) | 1000 | Appena aggiunto |

---

## Logica di rotazione

1. Proxy attivi (`is_active = true`) con crediti disponibili (`current_usage < limit_monthly`)
2. Ordinati per **priority ASC** (1 = prima scelta)
3. A parità di priority: crediti rimanenti **DESC**
4. Se un proxy fallisce durante una sessione → `markAsUnreliable()` → il manager sceglie automaticamente il successivo
