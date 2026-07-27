<?php

namespace App\Console\Commands;

use App\Services\Idfm\NetworkImporter;
use Illuminate\Console\Command;

class ImportNetworkCommand extends Command
{
    protected $signature = 'fotometro:import-network
        {--dry-run : Run the import in a transaction and roll it back}
        {--force : Continue reporting errors without failing the command}
        {--only= : Import only one area: lines, stations or accesses}
        {--skip-traces : Skip line geometry import}
        {--skip-accesses : Skip station access import}
        {--reset-idfm : Delete development IDFM data before importing}';

    protected $description = 'Import the Paris metro network from public Ile-de-France Mobilites datasets.';

    public function handle(NetworkImporter $importer): int
    {
        $only = $this->option('only');

        if ($only !== null && ! in_array($only, ['lines', 'stations', 'accesses', 'gtfs', 'order', 'topology'], true)) {
            $this->error('Invalid --only value. Expected one of: lines, stations, accesses, gtfs, order, topology.');

            return self::FAILURE;
        }

        $report = $importer->import(options: [
            'dry_run' => (bool) $this->option('dry-run'),
            'force' => (bool) $this->option('force'),
            'only' => in_array($only, ['order', 'topology'], true) ? 'gtfs' : $only,
            'skip_traces' => (bool) $this->option('skip-traces'),
            'skip_accesses' => (bool) $this->option('skip-accesses'),
            'reset_idfm' => (bool) $this->option('reset-idfm'),
        ]);

        $this->components->info('IDFM import report');
        $this->table(['Metric', 'Count'], [
            ['Phase', $report->phase ?? ($only ?? 'all')],
            ['Database driver', $report->databaseDriver ?? config('database.default')],
            ['Database name', $report->databaseName ?? ''],
            ['Created', $report->created],
            ['Updated', $report->updated],
            ['Deactivated', $report->deactivated],
            ['Ignored', $report->ignored],
            ['Raw records read', $report->rawRecords],
            ['Retained metro records', $report->retainedRecords],
            ['Unique station IDs detected', $report->uniqueStationIds],
            ['Unique StationStop', $report->uniqueStationStops],
            ['Unique stop areas', $report->uniqueStationAreas],
            ['Unresolved stops', $report->unresolvedStops],
            ['Lines loaded from database', $report->linesLoadedFromDatabase],
            ['Line lookup keys', $report->lineLookupKeys],
            ['Matched line records', $report->matchedLineRecords],
            ['Unmatched line records', $report->unmatchedLineRecords],
            ['Line colors imported', $report->lineColorsImported],
            ['Line text colors imported', $report->lineTextColorsImported],
            ['Line colors kept', $report->lineColorsKept],
            ['Line color fallbacks used', $report->lineColorFallbacksUsed],
            ['Invalid line colors ignored', $report->lineInvalidColorsIgnored],
            ['Station-line relations created', $report->stationLineRelationsCreated],
            ['Station-line relations updated', $report->stationLineRelationsUpdated],
            ['Access records read', $report->accessRecordsRead],
            ['Access relations read', $report->accessRelationsRead],
            ['Access-station relations created', $report->accessStationRelationsCreated],
            ['Unknown zdaid', $report->unknownZdaid],
            ['Unknown accid', $report->unknownAccid],
            ['Accesses linked to multiple stations', $report->accessesLinkedToMultipleStations],
            ['GTFS routes read', $report->gtfsRoutesRead],
            ['GTFS trips read', $report->gtfsTripsRead],
            ['GTFS stop_times read', $report->gtfsStopTimesRead],
            ['GTFS unique sequences', $report->gtfsUniqueSequences],
            ['GTFS simple lines', $report->gtfsSimpleLines],
            ['GTFS branched lines', $report->gtfsBranchedLines],
            ['GTFS ordered stations', $report->gtfsOrderedStations],
            ['GTFS relations updated', $report->gtfsRelationsUpdated],
            ['GTFS unresolved stops', $report->gtfsUnresolvedStops],
            ['GTFS topology sequences created', $report->gtfsTopologySequencesCreated],
            ['GTFS trunks detected', $report->gtfsTrunksDetected],
            ['GTFS branches detected', $report->gtfsBranchesDetected],
            ['GTFS loops detected', $report->gtfsLoopsDetected],
            ['GTFS orientations reversed', $report->gtfsOrientationsReversed],
            ['GTFS manual orientations used', $report->gtfsManualOrientationsUsed],
            ['GTFS unresolved orientation rules', $report->gtfsUnresolvedOrientationRules],
            ['Peak memory', $this->formatBytes(memory_get_peak_usage(true))],
        ]);

        if ($this->getOutput()->isVerbose()) {
            $this->line('CSV headers: '.json_encode($report->csvHeaders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line('Source line IDs sample: '.json_encode(array_slice($report->sourceLineIds, 0, 5), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line('Database line external_id sample: '.json_encode(array_slice($report->databaseLineIds, 0, 5), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line('Unmatched line IDs sample: '.json_encode(array_slice($report->unmatchedLineIds, 0, 5), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line('GTFS lines without sequence: '.json_encode($report->gtfsLinesWithoutSequence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line('GTFS reversed lines: '.json_encode(array_values(array_unique($report->gtfsReversedLines)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            foreach ($report->gtfsLineSummaries as $summary) {
                $this->line('GTFS line summary: '.json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        if ($report->ignoredReasons !== []) {
            $this->table(['Ignored reason', 'Count'], collect($report->ignoredReasons)
                ->map(fn (int $count, string $reason) => [$reason, $count])
                ->values()
                ->all());
        }

        foreach ($report->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($report->errors as $error) {
            $this->error($error);
        }

        return $report->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function formatBytes(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 1).' MB';
    }
}
