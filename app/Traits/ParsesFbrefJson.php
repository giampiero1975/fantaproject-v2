<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait ParsesFbrefJson
{
    /**
     * VERSIONE FINALE E DEFINITIVA: Gestisce la doppia codifica JSON.
     */
    protected function parseAndFlattenStats(object $row, array $metricsMap): ?array
    {
        $jsonString = $row->data_team;
        
        if (is_null($jsonString) || $jsonString === '') {
            return null;
        }
        
        // Pulisce la stringa da eventuali caratteri invisibili (BOM)
        $cleanedJsonString = trim(preg_replace('/^\x{FEFF}/u', '', $jsonString));
        
        // PRIMA DECODIFICA: Apre la "scatola" esterna.
        // Se i dati sono doppiamente codificati, questo restituirà una stringa.
        $intermediateData = json_decode($cleanedJsonString, true);
        
        // Controlliamo il risultato della prima decodifica.
        if (is_string($intermediateData)) {
            // Se abbiamo ottenuto una stringa, significa che era doppiamente codificata.
            // Eseguiamo la SECONDA DECODIFICA sulla stringa interna.
            $data = json_decode($intermediateData, true);
        } else {
            // Se abbiamo già ottenuto un array, i dati erano codificati singolarmente.
            $data = $intermediateData;
        }
        
        // Ora facciamo il nostro controllo finale su $data.
        if (!is_array($data)) {
            Log::warning("FALLIMENTO FINALE dopo tentativi di decodifica per player_id: {$row->player_id}. Errore JSON: " . json_last_error_msg());
            return null;
        }
        
        $flatRow = [];
        foreach ($metricsMap as $shortName => $longPath) {
            $value = null;
            
            if ($shortName === 'gk_goals_against_per90') {
                if (isset($row->minutes_90s, $row->gk_goals_against) && $row->minutes_90s > 0) {
                    $value = $row->gk_goals_against / $row->minutes_90s;
                }
            } else {
                $pathParts = $this->parseJsonPath($longPath);
                if ($pathParts && isset($data[$pathParts['category']][$pathParts['metric']])) {
                    $value = $data[$pathParts['category']][$pathParts['metric']];
                }
            }
            
            if (!is_null($value) && $value !== '') {
                $cleaned_value = str_replace([',', '%'], '', $value);
                if (is_numeric($cleaned_value)) {
                    $flatRow[$shortName] = (float)$cleaned_value;
                }
            }
        }
        
        return empty($flatRow) ? null : $flatRow;
    }
    
    private function parseJsonPath(string $longPath): ?array
    {
        $parts = explode('_', $longPath);
        if (count($parts) < 3) return null;
        $category = $parts[0] . '_' . $parts[1];
        $metric = implode('_', array_slice($parts, 2));
        return ['category' => $category, 'metric' => $metric];
    }
}