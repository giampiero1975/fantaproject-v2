<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Services\TeamFbrefAlignmentService;

class AlignTeamsCommand extends Command
{
    protected $signature = 'app:align-teams {--reset : Reset existing FBref mapping}';
    protected $description = 'Allinea le squadre Master con FBref (v8.0 Compatibility)';

    public function handle(TeamFbrefAlignmentService $service)
    {
        if ($this->option('reset')) {
            $this->info('Reseting existing mappings...');
            Team::query()->update(['fbref_id' => null, 'fbref_url' => null]);
        }

        $this->info('Starting alignment...');
        $result = $service->align();

        $this->table(['Status', 'Matched', 'Errors', 'Proxy Calls'], [$result]);
        $this->info('Done!');
    }
}
