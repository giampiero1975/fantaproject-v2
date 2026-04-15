<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "--- 🛠️ SURGICAL MERGE 2024: ELIMINAZIONE SDOPPIAMENTI ---\n";

/**
 * Logica di Bonifica:
 * Per ogni coppia (Record Listone Soft-Deleted, Record L4 da API):
 * 1. Ripristina Listone.
 * 2. Copia API ID su Listone.
 * 3. Sposta record Roster su Listone ID.
 * 4. Elimina L4.
 */

$merges = [
    // [ListoneID, L4_ID, Nome]
    [827, 1292, 'Jimenez A. -> Álex Jiménez'],
    [843, 1293, 'Chukwueze -> Samuel Chukwueze'],
    [894, 1299, 'Beltran L. -> Lucas Beltrán'],
    [308, 1295, 'Ikone\' -> Jonathan Ikoné'],
    [274, 1345, 'Felipe Anderson -> Felipe Anderson'],
    [862, 1365, 'Tchatchoua -> Jackson Tchatchoua'],
    [637, 1403, 'Sosa -> Borna Sosa'],
    [1009, 1406, 'Iovine -> Alessio Iovine'],
    [844, 1320, 'Ndoye -> Dan Ndoye'],
    [858, 1339, 'Weah -> Tim Weah'],
    [835, 1470, 'Tchaouna -> Loum Tchaouna'],
    [499, 1482, 'Gyasi -> Emmanuel Gyasi'],
    [1045, 1490, 'Tete Morente -> Tete Morente'],
    [1072, 1497, 'Martins K. -> Kevin Martins'],
    [1053, 1506, 'Hainaut -> Antoine Hainaut'],
    [606, 1307, 'Celik -> Zeki Çelik'],
    [993, 1313, 'Palestra -> Marco Palestra'],
    [1076, 1499, 'Forson O. -> Omari Forson'],
    [1058, 1462, 'Veiga R. -> Renato Veiga'],
    [1086, 1421, 'Liberali -> Mattia Liberali'],
    [866, 1311, 'Golic -> Lovro Golič'],
    [921, 1321, 'Karlsson -> Jesper Karlsson'],
    [840, 1317, 'Plaia -> Matteo Plaia'],
    [670, 1388, 'Esposito Sa. -> Sebastian Esposito'], // Attenzione a quale Esposito! Sebastiano è al Lecce.
    [640, 1363, 'Magnani -> Giangiacomo Magnani'],
    [836, 1301, 'Bove -> Edoardo Bove'],
    [340, 1350, 'Politano -> Matteo Politano'],
    [342, 1294, 'Pulisic -> Christian Pulisic'],
    [309, 1358, 'Zapata -> Duván Zapata'],
];

DB::transaction(function () use ($merges) {
    foreach ($merges as $m) {
        $targetId = $m[0];
        $sourceId = $m[1];
        $desc     = $m[2];

        $target = Player::withTrashed()->find($targetId);
        $source = Player::find($sourceId);

        if (!$target || !$source) {
            echo "   ⚠️ Skipping {$desc}: Uno dei due record non trovato.\n";
            continue;
        }

        echo "🚀 Merging: {$desc}\n";

        // 1. Ripristino target se eliminato
        if ($target->trashed()) {
            $target->restore();
            echo "      - ✅ Target ripristinato.\n";
        }

        // 1.5 Liberiamo l'ID API dal record sorgente (per evitare duplicati su indice unico)
        $apiId = $source->api_football_data_id;
        $source->update(['api_football_data_id' => null]);

        // 2. Copia dei dati API su Target
        $target->update([
            'api_football_data_id' => $apiId,
            'date_of_birth'        => $source->date_of_birth ?? $target->date_of_birth,
            'name'                 => $source->name,
        ]);
        echo "      - ✅ Dati API migrati.\n";

        // 3. Spostamento record nel Roster (Pivot)
        $sourceRosters = PlayerSeasonRoster::where('player_id', $sourceId)->get();
        foreach ($sourceRosters as $sr) {
            $conflict = PlayerSeasonRoster::where('player_id', $targetId)
                ->where('season_id', $sr->season_id)
                ->first();

            if ($conflict) {
                // Se il conflitto esiste (già presente in questa stagione), uniamo i dati utili
                if ($sr->role && !$conflict->role) $conflict->update(['role' => $sr->role]);
                if ($sr->parent_team_id && !$conflict->parent_team_id) $conflict->update(['parent_team_id' => $sr->parent_team_id]);
                
                $sr->forceDelete(); 
                echo "      - ℹ️ Roster conflict season {$sr->season_id} resolved (merged into existing).\n";
            } else {
                $sr->update(['player_id' => $targetId]);
                echo "      - ✅ Roster per stagione {$sr->season_id} spostato.\n";
            }
        }

        // 4. Update Parent Team references
        Player::where('parent_team_id', $sourceId)->update(['parent_team_id' => $targetId]);
        PlayerSeasonRoster::where('parent_team_id', $sourceId)->update(['parent_team_id' => $targetId]);

        // 5. Hard Delete del duplicato L4
        $source->forceDelete();
        echo "      - 🗑️ Record sdoppiato eliminato.\n";
    }
});

echo "\n--- 🏁 BONIFICA COMPLETATA ---";
