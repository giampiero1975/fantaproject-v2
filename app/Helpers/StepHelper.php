<?php

namespace App\Helpers;

class StepHelper
{
    /**
     * Restituisce le informazioni di theming per un dato stato dello step.
     *
     * @param string $status Uno tra: 'ok', 'partial', 'missing', o altro (trattato come 'blocked').
     * @return array Array con chiavi: icon, border_style, header_style, badge_style, badge_label.
     */
    public static function stepTheme(string $status): array
    {
        return match ($status) {
            'ok' => [
                'icon'         => '✅',
                'border_style' => 'border-left: 4px solid #198754;',
                'header_style' => 'background-color: #f0fdf4; border-bottom: 1px solid #bbf7d0;',
                'badge_style'  => 'background-color: #198754; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                'badge_label'  => 'Dati Presenti',
            ],
            'partial' => [
                'icon'         => '⚠️',
                'border_style' => 'border-left: 4px solid #f59e0b;',
                'header_style' => 'background-color: #fffbeb; border-bottom: 1px solid #fde68a;',
                'badge_style'  => 'background-color: #d97706; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                'badge_label'  => 'Parziale',
            ],
            'missing' => [
                'icon'         => '❌',
                'border_style' => 'border-left: 4px solid #dc2626;',
                'header_style' => 'background-color: #fef2f2; border-bottom: 1px solid #fecaca;',
                'badge_style'  => 'background-color: #dc2626; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                'badge_label'  => 'Mancante',
            ],
            default => [  // blocked / in attesa
                'icon'         => '⏳',
                'border_style' => 'border-left: 4px solid #9ca3af;',
                'header_style' => 'background-color: #f9fafb; border-bottom: 1px solid #e5e7eb;',
                'badge_style'  => 'background-color: #6b7280; color: #fff; font-weight: 700; font-size: 0.72rem; padding: 2px 10px; border-radius: 4px; white-space: nowrap;',
                'badge_label'  => 'In attesa',
            ],
        };
    }
}
