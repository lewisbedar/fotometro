<?php

namespace App\Services\Photos;

use App\Enums\CoverageStatus;
use App\Models\Station;

class StationCoverageUpdater
{
    public function update(Station $station): void
    {
        if (in_array($station->coverage_status, [CoverageStatus::Planned, CoverageStatus::Complete], true)) {
            return;
        }

        $count = $station->photos()->publiclyVisible()->count();
        $status = match (true) {
            $count === 0 => CoverageStatus::NotStarted,
            $count >= 5 => CoverageStatus::Documented,
            default => CoverageStatus::InProgress,
        };

        $station->forceFill(['coverage_status' => $status])->save();
    }
}
