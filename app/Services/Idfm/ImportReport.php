<?php

namespace App\Services\Idfm;

class ImportReport
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $deactivated = 0,
        public int $ignored = 0,
        public int $rawRecords = 0,
        public int $retainedRecords = 0,
        public ?string $phase = null,
        public ?string $databaseDriver = null,
        public ?string $databaseName = null,
        public int $uniqueStationIds = 0,
        public int $uniqueStationStops = 0,
        public int $uniqueStationAreas = 0,
        public int $unresolvedStops = 0,
        public int $accessRecordsRead = 0,
        public int $accessRelationsRead = 0,
        public int $accessStationRelationsCreated = 0,
        public int $unknownZdaid = 0,
        public int $unknownAccid = 0,
        public int $accessesLinkedToMultipleStations = 0,
        public int $gtfsRoutesRead = 0,
        public int $gtfsTripsRead = 0,
        public int $gtfsStopTimesRead = 0,
        public int $gtfsUniqueSequences = 0,
        public int $gtfsSimpleLines = 0,
        public int $gtfsBranchedLines = 0,
        public int $gtfsOrderedStations = 0,
        public int $gtfsRelationsUpdated = 0,
        public int $gtfsUnresolvedStops = 0,
        public int $gtfsTopologySequencesCreated = 0,
        public int $gtfsTrunksDetected = 0,
        public int $gtfsBranchesDetected = 0,
        public int $gtfsLoopsDetected = 0,
        public int $gtfsOrientationsReversed = 0,
        public int $gtfsManualOrientationsUsed = 0,
        public int $gtfsUnresolvedOrientationRules = 0,
        public array $gtfsReversedLines = [],
        public array $gtfsLinesWithoutSequence = [],
        public array $gtfsLineSummaries = [],
        public int $linesLoadedFromDatabase = 0,
        public int $lineLookupKeys = 0,
        public int $matchedLineRecords = 0,
        public int $unmatchedLineRecords = 0,
        public int $lineColorsImported = 0,
        public int $lineTextColorsImported = 0,
        public int $lineColorsKept = 0,
        public int $lineColorFallbacksUsed = 0,
        public int $lineInvalidColorsIgnored = 0,
        public int $stationLineRelationsCreated = 0,
        public int $stationLineRelationsUpdated = 0,
        public array $sourceLineIds = [],
        public array $databaseLineIds = [],
        public array $unmatchedLineIds = [],
        public array $csvHeaders = [],
        public array $ignoredReasons = [],
        public array $warnings = [],
        public array $errors = [],
    ) {}

    public function created(int $count = 1): void
    {
        $this->created += $count;
    }

    public function updated(int $count = 1): void
    {
        $this->updated += $count;
    }

    public function deactivated(int $count = 1): void
    {
        $this->deactivated += $count;
    }

    public function ignored(int $count = 1): void
    {
        $this->ignored += $count;
    }

    public function rawRecords(int $count = 1): void
    {
        $this->rawRecords += $count;
    }

    public function retainedRecords(int $count = 1): void
    {
        $this->retainedRecords += $count;
    }

    public function ignoredReason(string $reason): void
    {
        $this->ignoredReasons[$reason] = ($this->ignoredReasons[$reason] ?? 0) + 1;
        $this->ignored();
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    public function merge(self $report): void
    {
        $this->created += $report->created;
        $this->updated += $report->updated;
        $this->deactivated += $report->deactivated;
        $this->ignored += $report->ignored;
        $this->rawRecords += $report->rawRecords;
        $this->retainedRecords += $report->retainedRecords;
        $this->phase = $report->phase ?? $this->phase;
        $this->databaseDriver = $report->databaseDriver ?? $this->databaseDriver;
        $this->databaseName = $report->databaseName ?? $this->databaseName;
        $this->uniqueStationIds += $report->uniqueStationIds;
        $this->uniqueStationStops += $report->uniqueStationStops;
        $this->uniqueStationAreas += $report->uniqueStationAreas;
        $this->unresolvedStops += $report->unresolvedStops;
        $this->accessRecordsRead += $report->accessRecordsRead;
        $this->accessRelationsRead += $report->accessRelationsRead;
        $this->accessStationRelationsCreated += $report->accessStationRelationsCreated;
        $this->unknownZdaid += $report->unknownZdaid;
        $this->unknownAccid += $report->unknownAccid;
        $this->accessesLinkedToMultipleStations += $report->accessesLinkedToMultipleStations;
        $this->gtfsRoutesRead += $report->gtfsRoutesRead;
        $this->gtfsTripsRead += $report->gtfsTripsRead;
        $this->gtfsStopTimesRead += $report->gtfsStopTimesRead;
        $this->gtfsUniqueSequences += $report->gtfsUniqueSequences;
        $this->gtfsSimpleLines += $report->gtfsSimpleLines;
        $this->gtfsBranchedLines += $report->gtfsBranchedLines;
        $this->gtfsOrderedStations += $report->gtfsOrderedStations;
        $this->gtfsRelationsUpdated += $report->gtfsRelationsUpdated;
        $this->gtfsUnresolvedStops += $report->gtfsUnresolvedStops;
        $this->gtfsTopologySequencesCreated += $report->gtfsTopologySequencesCreated;
        $this->gtfsTrunksDetected += $report->gtfsTrunksDetected;
        $this->gtfsBranchesDetected += $report->gtfsBranchesDetected;
        $this->gtfsLoopsDetected += $report->gtfsLoopsDetected;
        $this->gtfsOrientationsReversed += $report->gtfsOrientationsReversed;
        $this->gtfsManualOrientationsUsed += $report->gtfsManualOrientationsUsed;
        $this->gtfsUnresolvedOrientationRules += $report->gtfsUnresolvedOrientationRules;
        $this->gtfsReversedLines = array_values(array_unique([...$this->gtfsReversedLines, ...$report->gtfsReversedLines]));
        $this->gtfsLinesWithoutSequence = array_values(array_unique([...$this->gtfsLinesWithoutSequence, ...$report->gtfsLinesWithoutSequence]));
        $this->gtfsLineSummaries = [...$this->gtfsLineSummaries, ...$report->gtfsLineSummaries];
        $this->linesLoadedFromDatabase += $report->linesLoadedFromDatabase;
        $this->lineLookupKeys += $report->lineLookupKeys;
        $this->matchedLineRecords += $report->matchedLineRecords;
        $this->unmatchedLineRecords += $report->unmatchedLineRecords;
        $this->lineColorsImported += $report->lineColorsImported;
        $this->lineTextColorsImported += $report->lineTextColorsImported;
        $this->lineColorsKept += $report->lineColorsKept;
        $this->lineColorFallbacksUsed += $report->lineColorFallbacksUsed;
        $this->lineInvalidColorsIgnored += $report->lineInvalidColorsIgnored;
        $this->stationLineRelationsCreated += $report->stationLineRelationsCreated;
        $this->stationLineRelationsUpdated += $report->stationLineRelationsUpdated;
        $this->sourceLineIds = array_values(array_unique([...$this->sourceLineIds, ...$report->sourceLineIds]));
        $this->databaseLineIds = array_values(array_unique([...$this->databaseLineIds, ...$report->databaseLineIds]));
        $this->unmatchedLineIds = array_values(array_unique([...$this->unmatchedLineIds, ...$report->unmatchedLineIds]));
        $this->csvHeaders = $report->csvHeaders ?: $this->csvHeaders;
        foreach ($report->ignoredReasons as $reason => $count) {
            $this->ignoredReasons[$reason] = ($this->ignoredReasons[$reason] ?? 0) + $count;
        }
        $this->warnings = [...$this->warnings, ...$report->warnings];
        $this->errors = [...$this->errors, ...$report->errors];
    }

    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'deactivated' => $this->deactivated,
            'ignored' => $this->ignored,
            'raw_records' => $this->rawRecords,
            'retained_records' => $this->retainedRecords,
            'ignored_reasons' => $this->ignoredReasons,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
}
