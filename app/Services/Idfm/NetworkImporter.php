<?php

namespace App\Services\Idfm;

use App\Models\Station;
use App\Models\Line;
use App\Models\StationAccess;
use App\Models\StationArea;
use App\Models\StationStop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NetworkImporter
{
    public function __construct(
        private readonly IdfmClient $client,
        private readonly LineImporter $lineImporter,
        private readonly StationImporter $stationImporter,
        private readonly TraceImporter $traceImporter,
        private readonly AccessImporter $accessImporter,
    ) {}

    public function import(array $datasets = [], array $options = []): ImportReport
    {
        $options = [
            'dry_run' => false,
            'force' => false,
            'only' => null,
            'skip_traces' => false,
            'skip_accesses' => false,
            'reset_idfm' => false,
            'deactivate_absent' => config('fotometro.idfm.deactivate_absent', true),
            ...$options,
        ];

        $report = new ImportReport;
        $report->phase = $options['only'] ?? 'all';
        $report->databaseDriver = DB::connection()->getDriverName();
        $report->databaseName = (string) DB::connection()->getDatabaseName();
        $failed = false;

        try {
            if ($datasets === []) {
                $datasets = $this->client->fetchConfiguredDatasets($options);
            }
        } catch (\Throwable $exception) {
            $failed = true;
            $report->error($exception->getMessage());

            if ($options['force'] !== true) {
                $this->cleanupTemporaryFiles($datasets, failed: true);
                throw $exception;
            }

            $this->cleanupTemporaryFiles($datasets, failed: true);
            return $report;
        }

        DB::beginTransaction();

        try {
            if ($options['reset_idfm']) {
                if (! $options['force']) {
                    $report->error('Refusing --reset-idfm without --force in non-interactive import mode.');
                    DB::rollBack();
                    return $report;
                }

                $this->resetIdfmData();
            }

            if ($options['only'] === null || $options['only'] === 'lines') {
                $report->merge($this->lineImporter->import($datasets['arrets_lignes'] ?? $datasets['lines'] ?? [], [
                    ...$options,
                    'report_records' => true,
                ]));
            }

            if ($options['only'] === null || $options['only'] === 'stations') {
                $report->merge($this->stationImporter->import($datasets['arrets_lignes'] ?? $datasets['stations'] ?? [], [
                    ...$options,
                    'stop_areas' => $datasets['stop_areas'] ?? [],
                    'stop_relations' => $datasets['stop_relations'] ?? [],
                    'report_records' => $options['only'] === 'stations',
                ]));
            }

            if ($options['only'] === null && ! $options['skip_traces']) {
                $report->merge($this->traceImporter->import($datasets['traces'] ?? []));
            }

            if (($options['only'] === null || $options['only'] === 'accesses') && ! $options['skip_accesses']) {
                if (! config('fotometro.idfm.accesses_url') || ! config('fotometro.idfm.access_relations_url')) {
                    $report->warn('Skipping accesses: FOTOMETRO_IDFM_ACCESSES_URL and FOTOMETRO_IDFM_ACCESS_RELATIONS_URL must both be configured.');
                } elseif (Station::query()->count() === 0) {
                    $report->warn('Skipping accesses: no stations are available in the database.');
                } else {
                    $report->merge($this->accessImporter->import($datasets['accesses'] ?? [], $datasets['access_station'] ?? [], $options));
                }
            }

            if ($options['dry_run']) {
                DB::rollBack();
                $report->warn('Dry-run mode: no database changes were committed.');
            } else {
                DB::commit();
                $this->forgetPublicCaches();
            }
        } catch (\Throwable $exception) {
            $failed = true;
            DB::rollBack();
            $report->error($exception->getMessage());

            if ($options['force'] !== true) {
                $this->cleanupTemporaryFiles($datasets, failed: true);
                throw $exception;
            }
        }

        $this->cleanupTemporaryFiles($datasets, $failed);

        return $report;
    }

    private function cleanupTemporaryFiles(array $datasets, bool $failed): void
    {
        if ($failed && config('app.debug')) {
            return;
        }

        foreach ($datasets as $dataset) {
            foreach (($dataset['_temporary_files'] ?? []) as $path) {
                if (is_string($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function forgetPublicCaches(): void
    {
        foreach ([
            'fotometro.public-map.v1',
            'fotometro.public-lines.v1',
            'fotometro.public-stations.v1',
        ] as $key) {
            Cache::forget($key);
        }
    }

    private function resetIdfmData(): void
    {
        DB::table('access_station')->delete();
        DB::table('station_line')->delete();
        StationStop::query()->delete();
        StationArea::query()->delete();
        StationAccess::query()->where('source', 'idfm')->delete();
        Station::query()->where('source', 'idfm')->delete();
        Line::query()->where('source', 'idfm')->delete();
    }
}
