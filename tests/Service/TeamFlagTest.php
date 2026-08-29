<?php

namespace App\Tests\Service;

use App\Service\TeamFlag;
use PHPUnit\Framework\TestCase;

class TeamFlagTest extends TestCase
{
    public function testResolvesCountriesInBothLanguagesAndHomeNations(): void
    {
        $flags = new TeamFlag();

        self::assertSame('be', $flags->codeFor('Belgique'));
        self::assertSame('be', $flags->codeFor('Belgium'));
        self::assertSame('be', $flags->codeFor('  BELGIQUE '));
        self::assertSame('gb-eng', $flags->codeFor('Angleterre'));
    }

    public function testSectionsThatAreNotCountriesHaveNoFlag(): void
    {
        $flags = new TeamFlag();

        self::assertNull($flags->codeFor('Divers'));
        self::assertNull($flags->codeFor(null));
        self::assertNull($flags->codeFor('FC Barcelona'));
    }

    public function testEverySectionStillGetsAGlyphToPrint(): void
    {
        $flags = new TeamFlag();

        self::assertSame('bi-trophy-fill', $flags->iconFor('Palmarès'));
        self::assertSame('bi-star-fill', $flags->iconFor('Stars'));
        self::assertSame('bi-three-dots', $flags->iconFor('Divers'));
        // Anything else falls back to a crest, which suits a club section.
        self::assertSame('bi-shield-fill', $flags->iconFor('FC Barcelona'));
        self::assertSame('bi-shield-fill', $flags->iconFor(null));
    }
}
