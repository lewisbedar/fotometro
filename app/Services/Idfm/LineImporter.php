<?php

namespace App\Services\Idfm;

use App\Models\Line;
use App\Services\Idfm\Concerns\NormalizesIdfmRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LineImporter
{
    use NormalizesIdfmRecords;

    public function import(array $payload, array $options = []): ImportReport
    {
        $report = new ImportReport;
        $seenExternalIds = [];

        $reportRecords = $options['report_records'] ?? true;

        $this->records($payload)
            ->each(fn () => $reportRecords ? $report->rawRecords() : null)
            ->filter(function (array $record) use ($report, $reportRecords): bool {
                $isMetro = $this->isMetro($record);

                if ($isMetro && $reportRecords) {
                    $report->retainedRecords();
                }

                return $isMetro;
            })
            ->groupBy(fn (array $record) => (string) $this->lineIdentifier($record))
            ->each(function ($records, string $externalId) use ($report, &$seenExternalIds): void {
                if ($externalId === '') {
                    $report->ignored();
                    return;
                }

                $record = $records->first();
                $code = trim((string) ($this->value($record, ['shortname', 'route_short_name', 'code', 'line_code']) ?? $externalId));
                $name = trim((string) ($this->value($record, ['route_long_name', 'name', 'line_name']) ?? "Ligne {$code}"));
                $seenExternalIds[] = $externalId;

                $line = Line::query()
                    ->where('external_id', $externalId)
                    ->orWhere('code', $code)
                    ->first();

                $attributes = [
                    'external_id' => $externalId,
                    'code' => $code,
                    'name' => $name,
                    'slug' => $line?->slug ?? $this->uniqueSlug('lines', Str::slug($name ?: "ligne-{$code}")),
                    'color' => $this->color($this->value($record, ['route_color', 'color']), '#1d4ed8'),
                    'text_color' => $this->color($this->value($record, ['route_text_color', 'text_color']), '#ffffff'),
                    'sort_order' => $this->sortOrder($code),
                    'is_active' => true,
                    'source' => 'idfm',
                    'source_payload' => $record,
                    'source_updated_at' => now(),
                ];

                if ($line) {
                    $line->fill($attributes)->save();
                    $report->updated();
                } else {
                    Line::query()->create($attributes);
                    $report->created();
                }
            });

        if (($options['deactivate_absent'] ?? true) && $seenExternalIds !== []) {
            $count = Line::query()
                ->where('source', 'idfm')
                ->whereNotIn('external_id', $seenExternalIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $report->deactivated($count);
        }

        return $report;
    }

    private function isMetro(array $record): bool
    {
        $mode = Str::lower(Str::ascii((string) ($this->value($record, ['mode', 'route_type', 'transportmode']) ?? 'metro')));

        return str_contains($mode, 'metro') || $mode === '1';
    }

    private function sortOrder(string $code): int
    {
        preg_match('/\d+/', $code, $matches);

        return isset($matches[0]) ? (int) $matches[0] : 999;
    }

    private function color(mixed $color, string $fallback): string
    {
        $candidate = strtoupper(ltrim((string) $color, '#'));

        return preg_match('/^[0-9A-F]{6}$/', $candidate) ? "#{$candidate}" : $fallback;
    }

    private function uniqueSlug(string $table, string $base): string
    {
        $slug = $base ?: 'ligne';
        $candidate = $slug;
        $index = 2;

        while (DB::table($table)->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$index}";
            $index++;
        }

        return $candidate;
    }
}
