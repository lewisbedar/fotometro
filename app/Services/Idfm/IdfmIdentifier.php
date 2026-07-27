<?php

namespace App\Services\Idfm;

class IdfmIdentifier
{
    public static function line(mixed $value): ?string
    {
        $identifier = strtoupper(trim((string) $value, " \t\n\r\0\x0B\"'"));

        if ($identifier === '') {
            return null;
        }

        $identifier = preg_replace('/^IDFM\s*:\s*/', '', $identifier) ?? $identifier;

        if (preg_match('/^\d{5}$/', $identifier) === 1) {
            return "C{$identifier}";
        }

        return $identifier;
    }
}
