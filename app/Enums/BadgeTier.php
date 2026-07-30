<?php

namespace App\Enums;

enum BadgeTier: string
{
    case Bronze = 'bronze';
    case Argent = 'argent';
    case Or = 'or';
    case Platine = 'platine';

    public function label(): string
    {
        return match ($this) {
            self::Bronze => 'Bronze',
            self::Argent => 'Argent',
            self::Or => 'Or',
            self::Platine => 'Platine',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bronze => '#92400E',
            self::Argent => '#64748B',
            self::Or => '#CA8A04',
            self::Platine => '#7C3AED',
        };
    }
}
