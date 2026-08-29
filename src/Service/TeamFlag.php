<?php

namespace App\Service;

/**
 * Maps a checklist section label (a national team name, in French or English) to
 * the ISO 3166-1 alpha-2 code the flag-icons CSS library expects. Home nations
 * use the gb-* subtags.
 *
 * Sections that are not countries — "Divers", "Ouverture", a club name — have no
 * code, which is what tells the printable sheet it still needs to spell the
 * section out rather than rely on a flag alone.
 */
class TeamFlag
{
    private const array CODES = [
        // French names (used by the WC 2026 import)
        'algérie' => 'dz', 'argentine' => 'ar', 'australie' => 'au', 'autriche' => 'at',
        'belgique' => 'be', 'bosnie-herzégovine' => 'ba', 'brésil' => 'br', 'canada' => 'ca',
        'cap-vert' => 'cv', 'colombie' => 'co', 'rd congo' => 'cd', 'croatie' => 'hr',
        'curaçao' => 'cw', 'tchéquie' => 'cz', 'équateur' => 'ec', 'égypte' => 'eg',
        'angleterre' => 'gb-eng', 'france' => 'fr', 'allemagne' => 'de', 'ghana' => 'gh',
        'haïti' => 'ht', 'iran' => 'ir', 'irak' => 'iq', "côte d'ivoire" => 'ci',
        'japon' => 'jp', 'jordanie' => 'jo', 'mexique' => 'mx', 'maroc' => 'ma',
        'pays-bas' => 'nl', 'nouvelle-zélande' => 'nz', 'norvège' => 'no', 'panama' => 'pa',
        'paraguay' => 'py', 'portugal' => 'pt', 'qatar' => 'qa', 'arabie saoudite' => 'sa',
        'écosse' => 'gb-sct', 'sénégal' => 'sn', 'afrique du sud' => 'za', 'corée du sud' => 'kr',
        'espagne' => 'es', 'suède' => 'se', 'suisse' => 'ch', 'tunisie' => 'tn',
        'turquie' => 'tr', 'uruguay' => 'uy', 'états-unis' => 'us', 'ouzbékistan' => 'uz',
        // English names (used by the demo fixtures / other albums)
        'algeria' => 'dz', 'argentina' => 'ar', 'australia' => 'au', 'austria' => 'at',
        'belgium' => 'be', 'bosnia and herzegovina' => 'ba', 'brazil' => 'br',
        'cape verde' => 'cv', 'colombia' => 'co', 'congo dr' => 'cd', 'croatia' => 'hr',
        'czechia' => 'cz', 'ecuador' => 'ec', 'egypt' => 'eg', 'england' => 'gb-eng',
        'germany' => 'de', 'haiti' => 'ht', 'iraq' => 'iq', 'ivory coast' => 'ci',
        'japan' => 'jp', 'jordan' => 'jo', 'mexico' => 'mx', 'morocco' => 'ma',
        'netherlands' => 'nl', 'new zealand' => 'nz', 'norway' => 'no', 'paraguay ' => 'py',
        'portugal ' => 'pt', 'qatar ' => 'qa', 'saudi arabia' => 'sa', 'scotland' => 'gb-sct',
        'senegal' => 'sn', 'south africa' => 'za', 'south korea' => 'kr', 'spain' => 'es',
        'sweden' => 'se', 'switzerland' => 'ch', 'tunisia' => 'tn', 'türkiye' => 'tr',
        'turkey' => 'tr', 'usa' => 'us', 'united states' => 'us', 'uzbekistan' => 'uz',
    ];

    public function codeFor(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return self::CODES[mb_strtolower(trim($name))] ?? null;
    }

    /**
     * Bootstrap Icons glyph standing in for a section that is not a country, so
     * the printed sheet's left column always carries a mark of some kind rather
     * than an empty box.
     */
    public function iconFor(?string $name): string
    {
        return match (mb_strtolower(trim((string) $name))) {
            'palmarès', 'palmares' => 'bi-trophy-fill',
            'ouverture', 'stades', 'stadiums' => 'bi-flag-fill',
            'divers' => 'bi-three-dots',
            'légendes', 'legends', 'stars' => 'bi-star-fill',
            default => 'bi-shield-fill',
        };
    }
}
