<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\TradeProposalRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly TradeProposalRepository $tradeProposals,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * Country name (French or English) → ISO 3166-1 alpha-2 code used by the
     * flag-icons CSS library (`fi fi-xx`). Home nations use the gb-* subtags.
     */
    private const COUNTRY_CODES = [
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

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_trades_count', $this->pendingTradesCount(...)),
            new TwigFunction('pending_users_count', $this->pendingUsersCount(...)),
            new TwigFunction('country_code', $this->countryCode(...)),
            new TwigFunction('avatar_url', $this->avatarUrl(...)),
        ];
    }

    /**
     * Free, deterministic collector avatar from the DiceBear HTTP API
     * (https://dicebear.com — open source, free to use, hotlink-friendly).
     */
    public function avatarUrl(?string $seed): string
    {
        // pixel-art-neutral style; square (radius=0) with light backgrounds.
        return 'https://api.dicebear.com/9.x/pixel-art-neutral/svg?radius=0&backgroundColor=e7f7ec,fff3bf,d0ebff&seed='
            .rawurlencode($seed ?: 'guest');
    }

    public function pendingUsersCount(): int
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return 0;
        }

        return $this->users->countPending();
    }

    /**
     * Resolve a team/country label to a flag-icons ISO code, or null if the
     * label is not a country (e.g. "Ouverture", "Palmarès", "Divers").
     */
    public function countryCode(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return self::COUNTRY_CODES[mb_strtolower(trim($name))] ?? null;
    }

    public function pendingTradesCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->tradeProposals->countPendingIncoming($user);
    }
}
