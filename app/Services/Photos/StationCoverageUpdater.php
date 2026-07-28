<?php

namespace App\Services\Photos;

use App\Enums\CoverageStatus;
use App\Models\Station;

class StationCoverageUpdater
{
    public function __construct(private readonly StationPhotoCoverageService $coverage)
    {
    }

    public function update(Station $station): void
    {
        $essential = $this->coverage->essentialCoverage($station);

        $attributes = ['coverage_percentage' => $essential['percentage']];

        if (! in_array($station->coverage_status, [CoverageStatus::Planned, CoverageStatus::Complete], true)) {
            $attributes['coverage_status'] = match (true) {
                $essential['percentage'] === 0 => CoverageStatus::NotStarted,
                $essential['complete'] => CoverageStatus::Documented,
                default => CoverageStatus::InProgress,
            };
        }

        $station->forceFill($attributes)->save();
    }
}
