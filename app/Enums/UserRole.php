<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Utilisateur',
            self::Moderator => 'Modérateur',
            self::Admin => 'Administrateur',
        };
    }
}
