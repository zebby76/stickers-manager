<?php

namespace App\Service;

/**
 * Immutable value object describing a user's progress on one team / section
 * of an album (e.g. "il te manque 3 joueurs du PSG").
 */
final readonly class TeamProgress
{
    public function __construct(
        public string $team,
        public int $total,
        public int $owned,
        public int $duplicates,
    ) {
    }

    public function missing(): int
    {
        return max(0, $this->total - $this->owned);
    }

    public function completionPercent(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return round($this->owned / $this->total * 100, 1);
    }

    public function isComplete(): bool
    {
        return $this->total > 0 && $this->owned >= $this->total;
    }

    /**
     * Stable, DOM-safe id for this section, so a Turbo Stream can refresh just
     * this team's header after a sticker is added/removed.
     */
    public function domId(): string
    {
        return 'team-progress-'.substr(md5($this->team), 0, 10);
    }
}
