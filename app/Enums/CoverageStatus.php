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
}
