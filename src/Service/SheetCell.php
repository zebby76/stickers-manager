<?php

namespace App\Service;

use App\Entity\Sticker;

/**
 * One cell of a printable checklist: a sticker plus the number of copies the
 * collector holds. Computed on read from an album's quantity map — no table.
 */
final readonly class SheetCell
{
    public function __construct(
        public Sticker $sticker,
        public int $quantity,
    ) {
    }

    public function isMissing(): bool
    {
        return $this->quantity < 1;
    }

    public function isDuplicate(): bool
    {
        return $this->quantity > 1;
    }

    /**
     * Copies available for trade (0 when the sticker is missing or unique).
     */
    public function spare(): int
    {
        return max(0, $this->quantity - 1);
    }

    /**
     * State marker driving the printed cell: 'missing' (empty box to tick with
     * a pen), 'owned' or 'duplicate'. Keeps the template free of logic.
     */
    public function state(): string
    {
        return match (true) {
            $this->isMissing() => 'missing',
            $this->isDuplicate() => 'duplicate',
            default => 'owned',
        };
    }
}
