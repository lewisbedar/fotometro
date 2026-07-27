<?php

namespace App\Enums;

enum CoverageStatus: string
{
    case NotStarted = 'not_started';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Documented = 'documented';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Non documentée',
            self::Planned => 'Planifiée',
            self::InProgress => 'En cours',
            self::Documented => 'Photographiée',
            self::Complete => 'Complète',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NotStarted => 'Station non photographiée',
            self::Planned => 'Sortie prévue',
            self::InProgress => 'Couverture commencée',
            self::Documented => 'Station documentée',
            self::Complete => 'Couverture complète',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => '#6B7280',
            self::Planned => '#2563EB',
            self::InProgress => '#D97706',
            self::Documented => '#059669',
            self::Complete => '#111827',
        };
    }
}
