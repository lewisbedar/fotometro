<?php

namespace App\Enums;

enum PhotoProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processing => 'Traitement',
            self::Ready => 'Prete',
            self::Failed => 'Erreur',
        };
    }
}
