<?php

namespace App\Service;

/**
 * Immutable achievement badge, computed on the fly from a user's collection and
 * trade activity (no persistence). {@see BadgeService}.
 */
final readonly class Badge
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $icon,    // Bootstrap Icons name, rendered as "bi bi-{icon}"
        public int $current,    // current value of the tracked metric
        public int $target,     // value needed to earn the badge
    ) {
    }

    public function isEarned(): bool
    {
        return $this->current >= $this->target;
    }

    /** Progress toward the badge, capped at 100%. */
    public function percent(): float
    {
        if ($this->target <= 0) {
            return 100.0;
        }

        return round(min($this->current, $this->target) / $this->target * 100, 1);
    }
}
