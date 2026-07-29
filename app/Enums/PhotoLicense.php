<?php

namespace App\Enums;

enum PhotoLicense: string
{
    case AllRightsReserved = 'all_rights_reserved';
    case PersonalUseOnly = 'personal_use_only';
    case CcBy = 'cc_by';
    case CcByNc = 'cc_by_nc';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::AllRightsReserved => 'Tous droits reserves',
            self::PersonalUseOnly => 'Usage personnel uniquement',
            self::CcBy => 'Creative Commons BY',
            self::CcByNc => 'Creative Commons BY-NC',
            self::Custom => 'Licence personnalisee',
        };
    }

    public function copyrightNotice(string $holder): string
    {
        return match ($this) {
            self::AllRightsReserved => "© {$holder} — Tous droits réservés",
            self::PersonalUseOnly => "© {$holder} — Usage personnel uniquement, toute autre utilisation interdite sans autorisation",
            self::CcBy => "© {$holder} — Publié sous licence Creative Commons BY (attribution requise)",
            self::CcByNc => "© {$holder} — Publié sous licence Creative Commons BY-NC (attribution requise, usage non commercial)",
            self::Custom => "© {$holder} — Licence personnalisée, voir conditions d'utilisation",
        };
    }
}
