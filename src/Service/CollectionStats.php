<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\User;
use App\Repository\UserAlbumRepository;
use App\Repository\UserStickerRepository;

class CollectionStats
{
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
