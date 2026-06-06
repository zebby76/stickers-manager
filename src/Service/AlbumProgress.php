<?php

namespace App\Service;

use App\Entity\Album;

/**
 * Immutable value object describing a user's progress on one album.
 */
final readonly class AlbumProgress
{
    public function __construct(
        public Album $album,
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
     * Expected number of packs needed to complete the album, accounting for the
     * coupon-collector problem (duplicates become more frequent as you progress).
     *
     * Expected stickers to draw to collect the M missing out of N total:
     *   N * (H(N) - H(N - M))   where H(k) is the k-th harmonic number.
     */
    public function estimatedPacksToComplete(): ?int
    {
        $missing = $this->missing();
        if ($missing === 0 || $this->total === 0) {
            return 0;
        }

        $n = $this->total;
        $expectedDraws = $n * ($this->harmonic($n) - $this->harmonic($n - $missing));
        $perPack = max(1, $this->album->getStickersPerPack());

        return (int) ceil($expectedDraws / $perPack);
    }

    private function harmonic(int $k): float
    {
        $sum = 0.0;
        for ($i = 1; $i <= $k; ++$i) {
            $sum += 1 / $i;
        }

        return $sum;
    }
}
