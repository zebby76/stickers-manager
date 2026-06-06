<?php

namespace App\Service;

use App\Entity\Sticker;
use App\Entity\User;

/**
 * Trade potential between the current user and another collector.
 */
final readonly class MatchResult
{
    /**
     * @param Sticker[] $canGive    my duplicates the other is missing
     * @param Sticker[] $canReceive the other's duplicates I am missing
     */
    public function __construct(
        public User $other,
        public array $canGive,
        public array $canReceive,
    ) {
    }

    public function giveCount(): int
    {
        return \count($this->canGive);
    }

    public function receiveCount(): int
    {
        return \count($this->canReceive);
    }

    public function score(): int
    {
        return $this->giveCount() + $this->receiveCount();
    }

    public function isMutual(): bool
    {
        return $this->giveCount() > 0 && $this->receiveCount() > 0;
    }
}
