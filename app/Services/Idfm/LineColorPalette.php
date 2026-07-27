<?php

namespace App\Services\Idfm;

class LineColorPalette
{
    /** @return array{color: string, text_color: string} */
    public function fallbackFor(string $code): array
    {
        return self::FALLBACKS[$this->normalizeCode($code)] ?? [
            'color' => '#1D4ED8',
            'text_color' => '#FFFFFF',
        ];
    }

    public function normalize(?string $value): ?string
    {
        $candidate = strtoupper(ltrim(trim((string) $value), '#'));

        return preg_match('/^[0-9A-F]{6}$/', $candidate) ? "#{$candidate}" : null;
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(str_replace(' ', '', trim($code)));
    }

    private const FALLBACKS = [
        '1' => ['color' => '#FFCD00', 'text_color' => '#111111'],
        '2' => ['color' => '#003CA6', 'text_color' => '#FFFFFF'],
        '3' => ['color' => '#837902', 'text_color' => '#FFFFFF'],
        '3B' => ['color' => '#6EC4E8', 'text_color' => '#111111'],
        '4' => ['color' => '#CF009E', 'text_color' => '#FFFFFF'],
        '5' => ['color' => '#FF7E2E', 'text_color' => '#111111'],
        '6' => ['color' => '#6ECA97', 'text_color' => '#111111'],
        '7' => ['color' => '#FA9ABA', 'text_color' => '#111111'],
        '7B' => ['color' => '#6ECA97', 'text_color' => '#111111'],
        '8' => ['color' => '#E19BDF', 'text_color' => '#111111'],
        '9' => ['color' => '#B6BD00', 'text_color' => '#111111'],
        '10' => ['color' => '#C9910D', 'text_color' => '#111111'],
        '11' => ['color' => '#704B1C', 'text_color' => '#FFFFFF'],
        '12' => ['color' => '#007852', 'text_color' => '#FFFFFF'],
        '13' => ['color' => '#6EC4E8', 'text_color' => '#111111'],
        '14' => ['color' => '#62259D', 'text_color' => '#FFFFFF'],
    ];
}
