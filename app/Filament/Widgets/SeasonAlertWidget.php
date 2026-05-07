<?php
namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Helpers\SeasonHelper;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SeasonAlertWidget extends Widget {
    protected static string $view = 'filament.widgets.season-alert-widget';
    protected int | string | array $columnSpan = 'full';

    #[Computed]
    public function metrics(): array {
        $lookback = SeasonHelper::getLookbackYears(); 
        $total = $lookback + 1; 
        
        $currentYear = (int) SeasonHelper::getCurrentSeason(); 
        
        // 1. Generazione Timeline: Anni attesi
        $expectedYears = range($currentYear - $lookback, $currentYear);
        
        // 2. Verifica Gaps: anni mancanti nel database
        $gaps = [];
        $existsCount = 0;
        
        foreach ($expectedYears as $year) {
            $exists = \App\Models\Season::where('season_year', $year)->exists();
            if (!$exists) {
                $gaps[] = $year;
            } else {
                $existsCount++;
            }
        }
        
        $progress = ($existsCount / $total) * 100;
        $progressRound = round($progress);

        // 3. STATO CONNESSIONE API (Ex Step 0)
        // Utilizziamo il caching per massimizzare la velocità di caricamento della dashboard
        $apiActive = Cache::remember('football_data_api_status', 30, function() {
            $apiKey = env('FOOTBALL_DATA_API_KEY');
            if (empty($apiKey)) return false;
            try {
                $response = Http::timeout(3)
                    ->withHeaders(['X-Auth-Token' => $apiKey])
                    ->get('https://api.football-data.org/v4/competitions/SA');
                return $response->successful();
            } catch (\Exception $e) {
                return false;
            }
        });

        // 4. REGOLE DI STATO (Header Accordion)
        // VERDE (Operational): Se Coverage = 100% E API Status = Active.
        // ARANCIONE (Incomplete): Se Coverage < 100% ma API è Active.
        // ROSSO (Critical): Se l'API è Down o il database è vuoto (Coverage = 0%).
        if ($progressRound == 100 && $apiActive) {
            $header_color = '#10b981';
            $status_label = 'OPERATIONAL';
            $status_color = 'emerald';
        } elseif (!$apiActive || $progressRound == 0) {
            $header_color = '#f43f5e';
            $status_label = 'CRITICAL';
            $status_color = 'rose';
        } else {
            $header_color = '#f59e0b';
            $status_label = 'INCOMPLETE';
            $status_color = 'amber';
        }

        return [
            'progress' => $progressRound,
            'total' => $total,
            'lookback' => $lookback,
            'missing' => $gaps,
            'api_active' => $apiActive,
            'status' => $status_label,
            'color' => $status_color,
            'header_color' => $header_color,
        ];
    }
}
