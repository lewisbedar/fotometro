<?php

namespace App\Services\Idfm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use SplFileObject;

class IdfmClient
{
    private const RECORDS_LIMIT_CEILING = 10000;

    public function fetchConfiguredDatasets(array $options = []): array
    {
        $datasets = [];
        $only = $options['only'] ?? null;

        if ($only === null || $only === 'lines') {
            $datasets['lines'] = $this->fetchComplete(config('fotometro.idfm.lines_url'));
        }

        if ($only === null || $only === 'stations') {
            $datasets['arrets_lignes'] = $this->fetchComplete(config('fotometro.idfm.arrets_lignes_url'));
        }

        if ($only === null || $only === 'stations' || $only === 'accesses') {
            $datasets['stop_areas'] = $this->fetchComplete(config('fotometro.idfm.stop_areas_url'));
            $datasets['stop_relations'] = $this->fetchComplete(config('fotometro.idfm.stop_relations_url'));
        }

        if ($only === null && ! ($options['skip_traces'] ?? false)) {
            $datasets['traces'] = $this->fetchComplete(config('fotometro.idfm.traces_url'));
        }

        if (($only === null || $only === 'accesses') && ! ($options['skip_accesses'] ?? false) && config('fotometro.idfm.import_accesses')) {
            $accessesUrl = config('fotometro.idfm.accesses_url');
            $relationsUrl = config('fotometro.idfm.access_relations_url');

            if ($accessesUrl) {
                $datasets['accesses'] = $this->fetchComplete($accessesUrl);
            }

            if ($relationsUrl) {
                $datasets['access_station'] = $this->fetchComplete($relationsUrl);
            }
        }

        if ($only === null || $only === 'gtfs') {
            $datasets['gtfs_archive'] = $this->fetchGtfsArchive(config('fotometro.idfm.gtfs_url'));
        }

        return $datasets;
    }

    public function fetchGtfsArchive(?string $url): array
    {
        if (blank($url)) {
            return [];
        }

        if (str_starts_with($url, 'file://')) {
            return ['path' => substr($url, 7)];
        }

        $downloadUrl = $url;

        if (! str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.zip')) {
            $payload = $this->fetch($url);
            $downloadUrl = $payload['results'][0]['url']['url'] ?? null;
        }

        if (! is_string($downloadUrl) || $downloadUrl === '') {
            throw new RuntimeException('Unable to resolve IDFM GTFS archive URL.');
        }

        $path = $this->temporaryPath('/exports/gtfs.zip');
        $response = Http::timeout(config('fotometro.idfm.timeout', 30))
            ->withUserAgent('fotometro/1.0 (+https://github.com/lewisbedar/fotometro)')
            ->sink($path)
            ->get($downloadUrl);

        if (! $response->successful()) {
            throw new RuntimeException("IDFM GTFS download failed with HTTP {$response->status()} for {$downloadUrl}.");
        }

        return [
            'path' => $path,
            '_temporary_files' => [$path],
        ];
    }

    public function fetch(?string $url): array
    {
        if (blank($url)) {
            return [];
        }

        if (str_starts_with($url, 'file://')) {
            $path = substr($url, 7);
            $json = file_get_contents($path);

            if ($json === false) {
                throw new RuntimeException("Unable to read IDFM fixture {$path}.");
            }

            return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        }

        if (str_starts_with($url, 'storage://')) {
            $json = Storage::get(substr($url, 10));

            return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        }

        $payload = $this->getJson($url);

        if (! isset($payload['results'], $payload['total_count']) || ! is_array($payload['results'])) {
            return $payload;
        }

        $results = $payload['results'];
        $limit = max(1, count($results));
        $total = (int) $payload['total_count'];
        $offset = $limit;

        while ($offset < $total) {
            if ($offset + $limit >= self::RECORDS_LIMIT_CEILING) {
                throw new RuntimeException(
                    'IDFM records pagination reached the 10,000-record API limit. Use the exports endpoint.'
                );
            }

            $next = $this->getJson($this->withQuery($url, [
                'limit' => $limit,
                'offset' => $offset,
            ]));

            $nextResults = $next['results'] ?? [];

            if (! is_array($nextResults) || $nextResults === []) {
                break;
            }

            $results = [...$results, ...$nextResults];
            $offset += count($nextResults);
        }

        return [
            ...$payload,
            'results' => $results,
        ];
    }

    public function fetchComplete(?string $url): array
    {
        if (blank($url)) {
            return [];
        }

        if (str_starts_with($url, 'file://') || str_starts_with($url, 'storage://')) {
            return $this->fetchLocal($url);
        }

        $dataset = $this->datasetFromUrl($url);

        if ($dataset === null) {
            return $this->fetch($url);
        }

        return $this->fetchExport($this->exportUrl($url, $dataset, 'csv'));
    }

    public function fetchExport(string $url): array
    {
        if (str_starts_with($url, 'file://') || str_starts_with($url, 'storage://')) {
            return $this->fetchLocal($url);
        }

        $path = $this->temporaryPath($url);

        try {
            $response = Http::timeout(config('fotometro.idfm.timeout', 30))
                ->accept('text/csv, application/json')
                ->withUserAgent('fotometro/1.0 (+https://github.com/lewisbedar/fotometro)')
                ->sink($path)
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("IDFM export request failed with HTTP {$response->status()} for {$url}.");
            }

            return [
                ...$this->parseExportFile($path),
                '_temporary_files' => [$path],
            ];
        } finally {
            //
        }
    }

    private function fetchLocal(string $url): array
    {
        $path = str_starts_with($url, 'storage://')
            ? storage_path('app/'.substr($url, 10))
            : substr($url, 7);

        if (! is_file($path)) {
            throw new RuntimeException("Unable to read IDFM fixture {$path}.");
        }

        return $this->parseExportFile($path);
    }

    private function getJson(string $url): array
    {
        $response = Http::timeout(config('fotometro.idfm.timeout', 30))
            ->acceptJson()
            ->withUserAgent('fotometro/1.0 (+https://github.com/lewisbedar/fotometro)')
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("IDFM request failed with HTTP {$response->status()} for {$url}.");
        }

        return $response->json();
    }

    private function parseExportFile(string $path): array
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            $json = file_get_contents($path);

            if ($json === false) {
                throw new RuntimeException("Unable to read IDFM export {$path}.");
            }

            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $records = $payload['results'] ?? $payload;

            if (! is_array($records)) {
                throw new RuntimeException("IDFM export {$path} is not a valid JSON array.");
            }

            return array_is_list($records) ? ['results' => $records, '_headers' => array_keys($records[0] ?? [])] : $payload;
        }

        return [
            'results' => $this->parseCsvFile($path),
            '_headers' => $this->csvHeaders($path),
        ];
    }

    private function parseCsvFile(string $path): LazyCollection
    {
        $delimiter = $this->detectDelimiter($path);

        return LazyCollection::make(function () use ($path, $delimiter) {
            $file = new SplFileObject($path, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl($delimiter);

            $headers = null;

            foreach ($file as $row) {
                if (! is_array($row) || $row === [null]) {
                    continue;
                }

                $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

                if ($headers === null) {
                    $headers = array_map(fn (string $header) => ltrim($header, "\xEF\xBB\xBF"), $row);

                    if (count(array_intersect($headers, ['route_id', 'id', 'id_line', 'access_id', 'stop_id', 'accid', 'zdaid'])) === 0) {
                        throw new RuntimeException("IDFM CSV export {$path} is missing expected headers.");
                    }

                    continue;
                }

                if (count($row) !== count($headers)) {
                    continue;
                }

                yield array_combine($headers, $row);
            }

            if ($headers === null) {
                throw new RuntimeException("IDFM CSV export {$path} is empty.");
            }
        });
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to inspect IDFM CSV export {$path}.");
        }

        $line = fgets($handle) ?: '';
        fclose($handle);

        $candidates = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];

        arsort($candidates);

        return (string) array_key_first($candidates);
    }

    private function csvHeaders(string $path): array
    {
        $delimiter = $this->detectDelimiter($path);
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        foreach ($file as $row) {
            if (is_array($row) && $row !== [null]) {
                return array_map(fn (string $header) => ltrim(trim($header), "\xEF\xBB\xBF"), $row);
            }
        }

        return [];
    }

    private function datasetFromUrl(string $url): ?string
    {
        preg_match('#/datasets/([^/]+)/(records|exports)#', $url, $matches);

        return $matches[1] ?? null;
    }

    private function exportUrl(string $recordsUrl, string $dataset, string $format): string
    {
        $parts = parse_url($recordsUrl);
        $scheme = isset($parts['scheme']) ? "{$parts['scheme']}://" : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $base = "{$scheme}{$host}{$port}/api/explore/v2.1/catalog/datasets/{$dataset}/exports/{$format}";

        return $this->withQuery($base, ['limit' => -1]);
    }

    private function temporaryPath(string $url): string
    {
        $directory = (string) config('fotometro.idfm.temp_dir', storage_path('app/idfm'));

        if (! str_contains($directory, ':') && ! str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $directory = base_path($directory);
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $extension = str_contains($url, '.zip') ? 'zip' : (str_contains($url, '/exports/json') ? 'json' : 'csv');

        return $directory.'/'.uniqid('idfm-export-', true).'.'.$extension;
    }

    private function withQuery(string $url, array $parameters): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $query = [...$query, ...$parameters];

        $scheme = isset($parts['scheme']) ? "{$parts['scheme']}://" : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $path = $parts['path'] ?? '';

        return $scheme.$host.$port.$path.'?'.http_build_query($query);
    }
}
