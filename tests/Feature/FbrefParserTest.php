<?php

namespace Tests\Feature;

use App\Services\FbrefScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FbrefParserTest extends TestCase
{
    /**
     * Test della Fase Rossa: Il parser deve fallire con l'HTML reale del Venezia 21/22
     * a causa del tableId 'stats_standard_11'.
     */
    public function test_venezia_2021_parser_success_with_actual_html()
    {
        $html = file_get_contents(base_path('tests/fixtures/venezia_21_22.html'));
        $targetUrl = 'https://fbref.com/en/squads/af5d5982/2021-2022/Venezia-Stats';
        
        // Mock del ProxyManagerService per evitare errori di database (SQLite memory)
        $this->mock(\App\Services\ProxyManagerService::class, function ($mock) use ($targetUrl) {
            $proxy = new \App\Models\ProxyService();
            $proxy->name = 'MockProxy';
            $mock->shouldReceive('getActiveProxy')->andReturn($proxy);
            $mock->shouldReceive('getProxyUrl')->andReturn('https://api.scraperapi.com/?api_key=test&url=' . urlencode($targetUrl));
        });

        // Simula la risposta di ScraperAPI
        Http::fake([
            '*api.scraperapi.com*' => Http::response($html, 200),
        ]);

        $service = new FbrefScrapingService();
        $data = $service->setTargetUrl($targetUrl)->scrapeTeamStats();

        // Verifica che i dati siano stati estratti
        $this->assertArrayHasKey('stats_standard', $data, 'La tabella stats_standard dovrebbe essere presente');
        $this->assertNotEmpty($data['stats_standard'], 'I dati di stats_standard non dovrebbero essere vuoti');
    }

    /**
     * Test della simulazione di mapping (Dry Run)
     * Verifica che il sistema riconosca i calciatori e mappi gli ID correttamente.
     */
    public function test_venezia_mapping_simulation()
    {
        // Migrazione manuale (SQLite memory)
        $this->artisan('migrate');

        // Setup Dati Locali
        $season = \App\Models\Season::create([
            'season_year' => 2021,
            'start_date' => '2021-08-01',
            'end_date' => '2022-05-30'
        ]);
        $team = \App\Models\Team::create(['name' => 'Venezia', 'fbref_url' => 'https://fbref.com/en/squads/af5d5982/Venezia-Stats']);
        
        // Creazione calciatori con nomi non perfettamente identici per testare il Fuzzy Match
        $p1 = \App\Models\Player::create(['name' => 'Mattia Caldara']); // Match esatto
        $p2 = \App\Models\Player::create(['name' => 'Ceccaroni Pietro']); // Invertito rispetto a "Pietro Ceccaroni"
        $p3 = \App\Models\Player::create(['name' => 'Busio Gianluca']); // Invertito
        $p4 = \App\Models\Player::create(['name' => 'Nico Maenpaa']); // Esiste nel fixture come "Niki Mäenpää"

        // Associazione al roster
        foreach ([$p1, $p2, $p3, $p4] as $p) {
            \App\Models\PlayerSeasonRoster::create([
                'player_id' => $p->id,
                'team_id' => $team->id,
                'season_id' => $season->id,
                'role' => 'D', // Dummy
            ]);
        }

        $html = file_get_contents(base_path('tests/fixtures/venezia_21_22.html'));
        $targetUrl = 'https://fbref.com/en/squads/af5d5982/2021-2022/Venezia-Stats';

        // Mock Proxy
        $this->mock(\App\Services\ProxyManagerService::class, function ($mock) use ($targetUrl) {
            $proxy = new \App\Models\ProxyService();
            $proxy->name = 'MockProxy';
            $mock->shouldReceive('getActiveProxy')->andReturn($proxy);
            $mock->shouldReceive('getProxyUrl')->andReturn('https://api.scraperapi.com/?api_key=test&url=' . urlencode($targetUrl));
        });

        // Fake Http
        \Illuminate\Support\Facades\Http::fake([
            '*api.scraperapi.com*' => \Illuminate\Support\Facades\Http::response($html, 200),
        ]);

        $service = new FbrefScrapingService();
        $scrapedData = $service->setTargetUrl($targetUrl)->scrapeTeamStats();
        
        $playersData = $scrapedData['stats_standard'] ?? [];
        
        // Esecuzione Sync (Dry Run)
        $results = $service->syncPlayersData($playersData, $team->id, $season->id, true);

        // Assertions
        $this->assertGreaterThan(0, $results['matched'], 'Dovrebbe esserci almeno un match');
        
        // Verifica Ceccaroni (Fuzzy Match: "Pietro Ceccaroni" vs "Ceccaroni Pietro")
        $ceccaroniLog = collect($results['log'])->first(fn($l) => str_contains($l, 'MATCH') && str_contains($l, 'Ceccaroni'));
        $this->assertNotNull($ceccaroniLog, 'Ceccaroni dovrebbe essere stato matchato via Fuzzy');
        
        // Verifica Estrazione ID corretta (Ceccaroni -> 4d1efb48 nel fixture)
        $this->assertTrue(
            collect($playersData)->contains(fn($p) => $p['Player'] === 'Pietro Ceccaroni' && $p['fbref_id_extracted'] === '4d1efb48'),
            'L\'ID di Ceccaroni nel fixture deve essere 4d1efb48'
        );

        // Dump dei risultati per visibilità nel log (come richiesto dall'utente)
        dump("Risultati Mapping Venezia 21/22:", [
            'Totale Scraped' => $results['total'],
            'Matchati' => $results['matched'],
            'Log Semplificato' => array_slice($results['log'], 0, 10)
        ]);
    }
}
