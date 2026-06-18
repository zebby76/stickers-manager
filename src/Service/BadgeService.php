<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TradeProposalRepository;

/**
 * Computes achievement badges for a user from existing collection / trade data.
 * Stateless and read-only: badges are derived on each request, there is no
 * dedicated table. Order of the returned list is the display order.
 */
class BadgeService
{
    public function __construct(
        private readonly CollectionStats $stats,
        private readonly TradeProposalRepository $trades,
    ) {
    }

    /**
     * @return Badge[]
     */
    public function forUser(User $user): array
    {
        $summary = $this->stats->summarize($this->stats->forCollectedAlbums($user));
        $completedTeams = $this->stats->completedTeamCount($user);
        $completedTrades = $this->trades->countCompletedFor($user);

        return [
            new Badge('collector', 'Collectionneur', 'Suivre un premier album', 'bookmark-star', $summary['albums'], 1),
            new Badge('centurion', 'Centurion', 'Posséder 100 vignettes', 'collection-fill', $summary['owned'], 100),
            new Badge('first_album', 'Album complété', 'Compléter un album entier', 'trophy', $summary['completed'], 1),
            new Badge('album_master', 'Maître des albums', 'Compléter 3 albums', 'trophy-fill', $summary['completed'], 3),
            new Badge('team_complete', 'Équipe au complet', 'Compléter une équipe entière', 'people-fill', $completedTeams, 1),
            new Badge('dupe_king', 'Roi des doublons', 'Accumuler 50 doublons', 'stack', $summary['duplicates'], 50),
            new Badge('first_trade', 'Premier échange', 'Réaliser un échange', 'arrow-left-right', $completedTrades, 1),
            new Badge('trade_veteran', 'Échangeur aguerri', 'Réaliser 10 échanges', 'award', $completedTrades, 10),
        ];
    }

    /**
     * Only the badges the user has already earned (for public display).
     *
     * @return Badge[]
     */
    public function earnedForUser(User $user): array
    {
        return array_values(array_filter($this->forUser($user), static fn (Badge $b): bool => $b->isEarned()));
    }
}
