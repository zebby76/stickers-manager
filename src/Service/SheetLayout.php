<?php

namespace App\Service;

/**
 * Print geometry for an album sheet, solved so the whole checklist fits on a
 * single A4 portrait page while staying as legible as that allows.
 *
 * The solver walks every sensible column count in two arrangements — the section
 * label on its own line above its grid (mirrors the on-screen checklist), or
 * pushed into a narrow left column, which buys back a line per section once an
 * album has many of them — and keeps whichever candidate prints the largest
 * numbers. Cell width follows from the column count; cell height is whatever the
 * remaining page height allows, so the two are solved independently: on a dense
 * album height is what binds, and forcing a fixed aspect ratio there would throw
 * away readable width for nothing.
 */
final readonly class SheetLayout
{
    /** A4 portrait and the @page margin the sheet prints with, in millimetres. */
    private const float PAGE_WIDTH = 210.0;
    private const float PAGE_HEIGHT = 297.0;
    private const float MARGIN = 10.0;

    /** Sheet header + legend. */
    private const float CHROME_HEIGHT = 26.0;

    private const float LABEL_COLUMN = 26.0;
    private const float LABEL_GAP = 2.0;
    private const float STACKED_LABEL_HEIGHT = 4.2;
    private const float STACKED_SECTION_GAP = 1.2;
    private const float INLINE_SECTION_GAP = 0.5;
    private const float CELL_GAP = 0.7;

    /**
     * The model is an approximation of what a print engine actually does (font
     * metrics, line boxes, rounding, and `break-inside: avoid` pushing a
     * straddling section onto the next page). Calibrated against headless
     * Chromium over albums of 23 to 960 stickers, the usable height lands near
     * 200mm rather than the 251mm the raw geometry suggests, so only this share
     * of the page is offered to the model.
     */
    private const float SAFETY = 0.80;

    private const float MIN_CELL_WIDTH = 4.4;
    private const float MAX_CELL_WIDTH = 11.0;
    private const float MIN_CELL_HEIGHT = 2.6;
    /** A cell may be squarer than this but never taller, or the grid looks odd. */
    private const float MAX_CELL_RATIO = 0.75;
    private const int MAX_COLUMNS = 40;

    /**
     * Below this printed size the stacked cell stops being comfortably readable,
     * and the mark is moved beside the number to buy the whole cell height back.
     */
    private const float LEGIBLE_FLOOR = 5.5;

    private const float MM_TO_PT = 2.835;
    /** Average glyph advance of the body font, in em. */
    private const float GLYPH_WIDTH = 0.58;

    public function __construct(
        public float $cellWidth,
        public float $cellHeight,
        public float $numberSize,
        public float $markSize,
        public int $columns,
        public bool $labelAbove,
        /** Number and mark side by side rather than stacked, for dense albums. */
        public bool $singleLine,
        public bool $fits,
    ) {
    }

    /**
     * Solve the layout for a rendered sheet.
     *
     * @param SheetSection[] $sections
     */
    public static function forSheet(array $sections): self
    {
        $sizes = [];
        $longest = 1;
        foreach ($sections as $section) {
            $sizes[] = \count($section->cells);
            foreach ($section->cells as $cell) {
                $longest = max($longest, mb_strlen((string) $cell->sticker->getNumber()));
            }
        }

        return self::fit($sizes, $longest);
    }

    /**
     * @param int[] $sectionSizes number of stickers in each section
     */
    public static function fit(array $sectionSizes, int $longestNumber = 3): self
    {
        if ($sectionSizes === []) {
            return self::at(self::MAX_CELL_WIDTH, 6.8, 1, true, $longestNumber, true);
        }

        $usableWidth = self::PAGE_WIDTH - 2 * self::MARGIN;
        $usableHeight = (self::PAGE_HEIGHT - 2 * self::MARGIN - self::CHROME_HEIGHT) * self::SAFETY;
        $sectionCount = \count($sectionSizes);

        $best = null;
        foreach ([true, false] as $labelAbove) {
            $gridWidth = $labelAbove
                ? $usableWidth
                : $usableWidth - self::LABEL_COLUMN - self::LABEL_GAP;

            $sectionGap = $labelAbove ? self::STACKED_SECTION_GAP : self::INLINE_SECTION_GAP;
            // Every section costs a gap (bar the last) and, when stacked, a label.
            $overhead = ($sectionCount - 1) * $sectionGap
                + ($labelAbove ? $sectionCount * self::STACKED_LABEL_HEIGHT : 0.0);

            for ($columns = 1; $columns <= self::MAX_COLUMNS; ++$columns) {
                $width = ($gridWidth - ($columns - 1) * self::CELL_GAP) / $columns;
                if ($width < self::MIN_CELL_WIDTH) {
                    break; // only narrower still with more columns
                }
                $width = min($width, self::MAX_CELL_WIDTH);

                $rows = 0;
                foreach ($sectionSizes as $size) {
                    $rows += (int) ceil(max(1, $size) / $columns);
                }

                $forCells = $usableHeight - $overhead;
                if ($forCells <= 0) {
                    continue;
                }

                $height = $forCells / $rows - self::CELL_GAP;
                if ($height < self::MIN_CELL_HEIGHT) {
                    continue;
                }
                $height = min($height, $width * self::MAX_CELL_RATIO);

                $candidate = self::at($width, $height, $columns, $labelAbove, $longestNumber, true);
                // Bigger numbers win; at equal size the grid that fills the row
                // wins, so a small album never prints as a single tall column.
                if ($best === null
                    || $candidate->numberSize > $best->numberSize
                    || ($candidate->numberSize === $best->numberSize && $candidate->columns > $best->columns)
                ) {
                    $best = $candidate;
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        // Nothing fits: print as tight as still legible and let the caller say so.
        $gridWidth = $usableWidth - self::LABEL_COLUMN - self::LABEL_GAP;
        $columns = max(1, (int) floor(($gridWidth + self::CELL_GAP) / (self::MIN_CELL_WIDTH + self::CELL_GAP)));

        return self::at(self::MIN_CELL_WIDTH, self::MIN_CELL_HEIGHT, $columns, false, $longestNumber, false);
    }

    private static function at(
        float $width,
        float $height,
        int $columns,
        bool $labelAbove,
        int $longestNumber,
        bool $fits,
    ): self {
        // Stacked, the number owns the top half of the cell; on one line it shares
        // the cell width with the mark ("×2") sitting beside it. Prefer stacked —
        // it is the clearer of the two — and only fall back to one line when the
        // stacked cell would print below the legible floor.
        $stacked = self::typeSize($width, $height, $longestNumber, false);
        $singleLine = false;
        $numberSize = $stacked;

        if ($stacked < self::LEGIBLE_FLOOR) {
            $compact = self::typeSize($width, $height, $longestNumber, true);
            if ($compact > $stacked) {
                $singleLine = true;
                $numberSize = $compact;
            }
        }

        return new self(
            cellWidth: round($width, 2),
            cellHeight: round($height, 2),
            numberSize: round($numberSize, 2),
            markSize: round($numberSize * ($singleLine ? 1.0 : 1.12), 2),
            columns: $columns,
            labelAbove: $labelAbove,
            singleLine: $singleLine,
            fits: $fits,
        );
    }

    /**
     * Largest type size that keeps the number inside its cell, both across the
     * cell width and within the vertical share the arrangement leaves it.
     */
    private static function typeSize(float $width, float $height, int $longestNumber, bool $singleLine): float
    {
        $glyphs = $singleLine ? $longestNumber + 2 : $longestNumber;
        $byWidth = ($width * 0.88 * self::MM_TO_PT) / (max(1, $glyphs) * self::GLYPH_WIDTH);
        $byHeight = $height * ($singleLine ? 0.72 : 0.46) * self::MM_TO_PT;

        return max(3.6, min(8.5, $byWidth, $byHeight));
    }
}
