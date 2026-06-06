<?php

namespace App\Enum;

/**
 * Direction of a trade item, relative to the proposal initiator (fromUser).
 */
enum TradeDirection: string
{
    case Give = 'give';       // the initiator gives this sticker to the recipient
    case Receive = 'receive'; // the initiator receives this sticker from the recipient

    public function label(): string
    {
        return match ($this) {
            self::Give => 'Je donne',
            self::Receive => 'Je reçois',
        };
    }
}
