<?php

namespace App\Enum;

enum TradeStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Declined => 'Refusée',
            self::Cancelled => 'Annulée',
            self::Completed => 'Terminée',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Accepted;
    }
}
