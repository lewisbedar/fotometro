<?php

namespace App\Services\Idfm;

use App\Models\Line;
use App\Services\Idfm\Concerns\NormalizesIdfmRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LineImporter
{
    use NormalizesIdfmRecords;

    public function __construct(private readonly LineColorPalette $colors) {}

    private const PARIS_METRO_CODES = [
        '1',
        '2',
        '3',
        '3B',
        '4',
        '5',
        '6',
        '7',
        '7B',
        '8',
        '9',
        '10',
        '11',
        '12',
        '13',
        '14',
    ];

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
                $code = trim((string) ($this->value($record, ['shortname_line', 'shortname', 'route_short_name', 'code', 'line_code']) ?? $externalId));
                $name = trim((string) ($this->value($record, ['name_line', 'route_long_name', 'name', 'line_name']) ?? "Ligne {$code}"));
                $seenExternalIds[] = $externalId;

                $line = Line::query()
                    ->where('external_id', $externalId)
                    ->orWhere('code', $code)
                    ->first();

                $color = $this->resolveColor(
                    $this->value($record, ['colourweb_hexa', 'route_color', 'color']),
                    $line?->color,
                    $code,
                    'color',
                    $report,
                );
                $textColor = $this->resolveColor(
                    $this->value($record, ['textcolourweb_hexa', 'route_text_color', 'text_color']),
                    $line?->text_color,
                    $code,
                    'text_color',
                    $report,
                );

                $attributes = [
                    'external_id' => $externalId,
                    'code' => $code,
                    'name' => $name,
                    'slug' => $line?->slug ?? $this->uniqueSlug('lines', Str::slug($name ?: "ligne-{$code}")),
                    'color' => $color,
                    'text_color' => $textColor,
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
        $mode = Str::lower(Str::ascii((string) ($this->value($record, ['mode', 'route_type', 'transportmode', 'transport_mode']) ?? 'metro')));
        $code = $this->normalizeCode((string) ($this->value($record, ['shortname_line', 'shortname', 'route_short_name', 'code', 'line_code']) ?? ''));

        return (str_contains($mode, 'metro') || $mode === '1')
            && in_array($code, self::PARIS_METRO_CODES, true);
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(str_replace(' ', '', trim($code)));
    }

    private function sortOrder(string $code): int
    {
        preg_match('/\d+/', $code, $matches);

        return isset($matches[0]) ? (int) $matches[0] : 999;
    }

    private function resolveColor(mixed $candidate, ?string $existing, string $code, string $kind, ImportReport $report): string
    {
        $normalized = $this->colors->normalize(is_scalar($candidate) ? (string) $candidate : null);

        if ($normalized !== null) {
            if ($kind === 'color') {
                $report->lineColorsImported++;
            } else {
                $report->lineTextColorsImported++;
            }

            return $normalized;
        }

        if ($candidate !== null && $candidate !== '') {
            $report->lineInvalidColorsIgnored++;
        }

        $existing = $this->colors->normalize($existing);

        if ($existing !== null) {
            $report->lineColorsKept++;

            return $existing;
        }

        $fallback = $this->colors->fallbackFor($code)[$kind];
        $report->lineColorFallbacksUsed++;

        return $fallback;
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
