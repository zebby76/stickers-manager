<?php

namespace App\Tests\Service;

use App\Service\SheetLayout;
use PHPUnit\Framework\TestCase;

/**
 * The solver's contract: whatever the album's shape, the sheet is laid out to
 * fit a single A4 page and stays readable. The height model itself is calibrated
 * against headless Chromium (see SheetLayout::SAFETY); these tests lock in the
 * invariants that calibration relies on.
 */
class SheetLayoutTest extends TestCase
{
    /**
     * @return iterable<string, array{int[]}>
     */
    public static function albumShapes(): iterable
    {
        yield 'tiny album' => [array_fill(0, 4, 6)];
        yield 'fixture album' => [[6, 6, 6, 5]];
        yield '12 teams of 20' => [array_fill(0, 12, 20)];
        yield '25 teams of 20' => [array_fill(0, 25, 20)];
        yield 'standard 48 teams of 20' => [array_fill(0, 48, 20)];
        yield 'uneven sections' => [[3, 40, 1, 25, 60, 12, 8]];
        yield 'one huge section' => [[600]];
    }

    /**
     * @param int[] $sections
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('albumShapes')]
    public function testEveryShapeIsSolvedAndStaysReadable(array $sections): void
    {
        $layout = SheetLayout::fit($sections, 3);

        self::assertTrue($layout->fits, 'A realistic album must fit one A4 page');
        self::assertGreaterThan(1, $layout->columns, 'The grid must use the page width, not print one tall column');
        self::assertGreaterThanOrEqual(3.6, $layout->numberSize);
        self::assertGreaterThan(0.0, $layout->cellWidth);
        self::assertGreaterThan(0.0, $layout->cellHeight);
        self::assertLessThanOrEqual($layout->cellWidth, $layout->cellHeight, 'A cell is never taller than it is wide');
    }

    public function testDenseAlbumsFallBackToASingleLineCell(): void
    {
        $roomy = SheetLayout::fit(array_fill(0, 12, 20), 3);
        self::assertFalse($roomy->singleLine, 'A roomy album keeps the mark under the number');

        $dense = SheetLayout::fit(array_fill(0, 48, 20), 3);
        self::assertTrue($dense->singleLine, 'A 960-sticker album moves the mark beside the number');
        self::assertGreaterThan(5.0, $dense->numberSize, 'The standard album must still print legibly');
    }

    public function testLongStickerCodesShrinkTheTypeRatherThanOverflow(): void
    {
        $shortCodes = SheetLayout::fit(array_fill(0, 20, 20), 2);
        $longCodes = SheetLayout::fit(array_fill(0, 20, 20), 6);

        self::assertLessThan($shortCodes->numberSize, $longCodes->numberSize);
    }

    public function testAnImpossibleAlbumIsReportedRatherThanSilentlyOverflowing(): void
    {
        // Far beyond any real album: the solver must admit it cannot fit.
        $layout = SheetLayout::fit(array_fill(0, 400, 40), 3);

        self::assertFalse($layout->fits);
    }

    public function testTheLabelColumnShrinksToTheFlagItHolds(): void
    {
        // A dense album prints short rows, so the flag — and the column around it
        // — must shrink with them rather than reserve width for a name that is no
        // longer printed.
        $dense = SheetLayout::fit(array_fill(0, 48, 20), 5);
        $roomy = SheetLayout::fit([6, 6, 6, 5], 3);

        self::assertLessThan($roomy->labelWidth, $dense->labelWidth);
        self::assertEqualsWithDelta($dense->cellHeight, $dense->flagHeight, 0.01,
            'The flag is scaled to the row it labels');
        self::assertEqualsWithDelta($dense->flagHeight * 4 / 3, $dense->flagWidth, 0.01,
            'Flags keep their 4:3 aspect');
        self::assertLessThanOrEqual($dense->labelWidth, $dense->flagWidth,
            'The flag never overflows its column');
    }

    public function testDroppingTheSectionNameBuysWidthForTheCells(): void
    {
        // Regression guard for 1.9.1: the printed left column is one flag wide,
        // not a spelled-out name, which is what widened the cells.
        $layout = SheetLayout::fit(array_fill(0, 48, 20), 5);

        self::assertLessThan(10.0, $layout->labelWidth);
        self::assertGreaterThan(8.0, $layout->cellWidth);
    }

    public function testEmptyAlbumDoesNotBlowUp(): void
    {
        $layout = SheetLayout::fit([], 3);

        self::assertTrue($layout->fits);
        self::assertGreaterThan(0.0, $layout->cellWidth);
    }
}
