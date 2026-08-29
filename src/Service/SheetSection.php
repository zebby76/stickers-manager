<?php

namespace App\Service;

/**
 * One section (team / group) of a printable checklist: every sticker of the
 * section in album order, alongside the same progress figures the on-screen
 * checklist shows.
 */
final readonly class SheetSection
{
    /**
     * @param SheetCell[] $cells
     */
    public function __construct(
        public string $team,
        public array $cells,
        public TeamProgress $progress,
    ) {
    }
}
