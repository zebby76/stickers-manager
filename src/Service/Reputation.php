<?php

namespace App\Service;

/**
 * A collector's trade reputation: how many trades they have completed, and
 * whether that crosses the "reliable trader" threshold. Computed from
 * completed TradeProposal rows (see TradeProposalRepository).
 */
final readonly class Reputation
{
    /** Completed trades needed to be flagged as a reliable trader. */
    public const int RELIABLE_THRESHOLD = 5;

    public function __construct(
        public int $completedTrades,
    ) {
    }

    public function isReliable(): bool
    {
        return $this->completedTrades >= self::RELIABLE_THRESHOLD;
    }
}
