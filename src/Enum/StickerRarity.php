<?php

namespace App\Enum;

enum StickerRarity: string
{
    case Common = 'common';
    case Shiny = 'shiny';   // brillant / holographique
    case Badge = 'badge';   // écusson / logo
    case Legend = 'legend'; // légende / spéciale rare

    public function label(): string
    {
        return match ($this) {
            self::Common => 'Commun',
            self::Shiny => 'Brillant',
            self::Badge => 'Écusson',
            self::Legend => 'Légende',
        };
    }
}
