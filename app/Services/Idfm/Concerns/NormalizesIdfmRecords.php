<?php

namespace App\Services\Idfm\Concerns;

use Illuminate\Support\LazyCollection;

trait NormalizesIdfmRecords
{
    protected function records(array $payload): LazyCollection
    {
        if (array_is_list($payload)) {
            return LazyCollection::make($payload);
        }

        if (isset($payload['results'])) {
            return LazyCollection::make($payload['results']);
        }

        if (isset($payload['records']) && is_array($payload['records'])) {
            return LazyCollection::make($payload['records'])
                ->map(fn (array $record) => $record['fields'] ?? $record);
        }

        return LazyCollection::empty();
    }

    protected function value(array $record, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record) && $record[$key] !== '') {
                return $record[$key];
            }
        }

        return null;
    }

    protected function lineIdentifier(array $record): ?string
    {
        return \App\Services\Idfm\IdfmIdentifier::line($this->value($record, [
            'route_id',
            'id_line',
            'line_id',
            'external_id',
            'id',
            'LineId',
            'lineid',
        ]));
    }

    protected function stationIdentifier(array $record): ?string
    {
        $value = $this->value($record, [
            'stop_id',
            'id_stop',
            'station_id',
            'external_id',
            'ZdAId',
            'zdaid',
            'zda_id',
        ]);

        $identifier = trim((string) $value, " \t\n\r\0\x0B\"'");

        return $identifier === '' ? null : $identifier;
    }

    protected function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'oui', 'y'], true);
    }

    protected function coordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) str_replace(',', '.', (string) $value);

        return is_finite($number) ? $number : null;
    }
}
