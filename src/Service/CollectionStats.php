<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\User;
use App\Repository\UserAlbumRepository;
use App\Repository\UserStickerRepository;

class CollectionStats
{
    /** Section label for stickers without a team. */
    public const string UNGROUPED = 'Divers';

    public function __construct(
        private readonly UserStickerRepository $userStickers,
        private readonly UserAlbumRepository $userAlbums,
    ) {
    }

    public function forAlbum(User $user, Album $album): AlbumProgress
    {
        $counts = $this->userStickers->getCountsForAlbum($user, $album);

        return new AlbumProgress(
            album: $album,
            total: $album->getTotalStickers(),
            owned: $counts['owned'],
            duplicates: $counts['duplicates'],
        );
    }

    /**
     * Per-team (per-section) progress for one album, derived from an already
     * fetched quantity map (sticker id => quantity) so it costs no extra query.
     * Stickers without a team fall under {@see self::UNGROUPED}.
     *
     * @param array<int, int> $quantityMap
     *
     * @return array<string, TeamProgress> keyed by team label
     */
    public function teamBreakdown(Album $album, array $quantityMap): array
    {
        /** @var array<string, array{total: int, owned: int, duplicates: int}> $acc */
        $acc = [];
        foreach ($album->getStickers() as $sticker) {
            $team = $sticker->getTeam() ?? self::UNGROUPED;
            $acc[$team] ??= ['total' => 0, 'owned' => 0, 'duplicates' => 0];

            $qty = $quantityMap[$sticker->getId()] ?? 0;
            ++$acc[$team]['total'];
            if ($qty >= 1) {
                ++$acc[$team]['owned'];
            }
            if ($qty > 1) {
                $acc[$team]['duplicates'] += $qty - 1;
            }
        }

        $breakdown = [];
        foreach ($acc as $team => $c) {
            $breakdown[$team] = new TeamProgress($team, $c['total'], $c['owned'], $c['duplicates']);
        }

        return $breakdown;
    }

    /**
     * Order sections the way the on-screen checklist does: the album's opening
     * sections first, then teams alphabetically (accent-aware), "Divers" last.
     *
     * @template TValue
     *
     * @param array<string, TValue> $bySection
     *
     * @return array<string, TValue>
     */
    public function orderSections(array $bySection): array
    {
        $priority = ['Ouverture' => 0, 'Palmarès' => 1, self::UNGROUPED => 99];
        $collator = new \Collator('fr_FR');

        uksort($bySection, static function (string $a, string $b) use ($priority, $collator): int {
            $pa = $priority[$a] ?? 50;
            $pb = $priority[$b] ?? 50;

            return $pa !== $pb ? $pa <=> $pb : $collator->compare($a, $b);
        });

        return $bySection;
    }

    /**
     * Printable checklist for one album: every sticker of every section with the
     * quantity held, ready to be laid out as a paper grid. Derived from an
     * already fetched quantity map, so it costs no extra query.
     *
     * @param array<int, int> $quantityMap
     *
     * @return array<string, SheetSection> keyed by section label, in checklist order
     */
    public function printableSheet(Album $album, array $quantityMap): array
    {
        $cells = [];
        foreach ($album->getStickers() as $sticker) {
            $team = $sticker->getTeam() ?? self::UNGROUPED;
            $cells[$team][] = new SheetCell($sticker, $quantityMap[$sticker->getId()] ?? 0);
        }

        $progress = $this->teamBreakdown($album, $quantityMap);

        $sections = [];
        foreach ($this->orderSections($cells) as $team => $sectionCells) {
            $sections[$team] = new SheetSection($team, $sectionCells, $progress[$team]);
        }

        return $sections;
    }

    /**
     * Progress for every album the user actively collects.
     *
     * @return AlbumProgress[]
     */
    public function forCollectedAlbums(User $user): array
    {
        $progress = [];
        foreach ($this->userAlbums->findByUser($user) as $userAlbum) {
            $progress[] = $this->forAlbum($user, $userAlbum->getAlbum());
        }

        return $progress;
    }

    /**
     * Number of fully collected teams/sections across all collected albums
     * (used for the "full team" achievement badge).
     */
    public function completedTeamCount(User $user): int
    {
        $count = 0;
        foreach ($this->userAlbums->findByUser($user) as $userAlbum) {
            $album = $userAlbum->getAlbum();
            $map = $this->userStickers->getQuantityMapForAlbum($user, $album);
            foreach ($this->teamBreakdown($album, $map) as $team) {
                if ($team->isComplete()) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * Global totals across all collected albums.
     *
     * @param AlbumProgress[] $progresses
     *
     * @return array{albums: int, total: int, owned: int, missing: int, duplicates: int, percent: float, completed: int}
     */
    public function summarize(array $progresses): array
    {
        $total = $owned = $duplicates = $completed = 0;
        foreach ($progresses as $p) {
            $total += $p->total;
            $owned += $p->owned;
            $duplicates += $p->duplicates;
            $completed += $p->isComplete() ? 1 : 0;
        }

        return [
            'albums' => \count($progresses),
            'total' => $total,
            'owned' => $owned,
            'missing' => max(0, $total - $owned),
            'duplicates' => $duplicates,
            'percent' => $total > 0 ? round($owned / $total * 100, 1) : 0.0,
            'completed' => $completed,
        ];
    }
}
