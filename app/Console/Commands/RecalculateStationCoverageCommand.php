<?php

namespace App\Console\Commands;

use App\Models\Station;
use App\Services\Photos\StationCoverageUpdater;
use Illuminate\Console\Command;

class RecalculateStationCoverageCommand extends Command
{
    protected $signature = 'fotometro:recalculate-coverage';

    protected $description = 'Recompute coverage_percentage and coverage_status for every station.';

    public function handle(StationCoverageUpdater $updater): int
    {
        $stations = Station::query()->get();

        $this->withProgressBar($stations, fn (Station $station) => $updater->update($station));
        $this->newLine();
        $this->info("Recalculated coverage for {$stations->count()} station(s).");

        return self::SUCCESS;
    }
}
