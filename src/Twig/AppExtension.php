<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\TradeProposalRepository;
use App\Repository\UserRepository;
use App\Service\TeamFlag;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly TradeProposalRepository $tradeProposals,
        private readonly UserRepository $users,
        private readonly TeamFlag $flags,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_trades_count', $this->pendingTradesCount(...)),
            new TwigFunction('pending_users_count', $this->pendingUsersCount(...)),
            new TwigFunction('country_code', $this->countryCode(...)),
            new TwigFunction('team_icon', $this->teamIcon(...)),
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
        return $this->flags->codeFor($name);
    }

    /**
     * Fallback glyph for a section with no country flag (a club, "Palmarès"…).
     */
    public function teamIcon(?string $name): string
    {
        return $this->flags->iconFor($name);
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
