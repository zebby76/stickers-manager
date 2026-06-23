<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\Sticker;
use App\Entity\User;
use App\Repository\UserAlbumRepository;
use App\Repository\UserRepository;
use App\Repository\UserStickerRepository;

class TradeMatcher
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserStickerRepository $userStickers,
        private readonly UserAlbumRepository $userAlbums,
    ) {
    }

    /**
     * Trade potential between two users.
     */
    public function match(User $me, User $other): MatchResult
    {
        $myDuplicates = $this->userStickers->findDuplicates($me);
        $otherDuplicates = $this->userStickers->findDuplicates($other);

        $myOwned = array_flip($this->userStickers->findOwnedStickerIds($me));
        $otherOwned = array_flip($this->userStickers->findOwnedStickerIds($other));

        $myAlbums = $this->collectedAlbumIds($me);
        $otherAlbums = $this->collectedAlbumIds($other);

        // My duplicates that the other collects but does not own yet.
        $canGive = $this->wantedBy($myDuplicates, $otherOwned, $otherAlbums);
        // The other's duplicates that I collect but do not own yet.
        $canReceive = $this->wantedBy($otherDuplicates, $myOwned, $myAlbums);

        return new MatchResult($other, $canGive, $canReceive);
    }

    /**
     * All other collectors with whom a mutual or one-sided trade is possible,
     * sorted by best match first.
     *
     * @return MatchResult[]
     */
    public function findMatches(User $me): array
    {
        $results = [];
        foreach ($this->users->findOthers($me) as $other) {
            $result = $this->match($me, $other);
            if ($result->score() > 0) {
                $results[] = $result;
            }
        }

        usort($results, static function (MatchResult $a, MatchResult $b): int {
            // Mutual trades first, then by total score.
            if ($a->isMutual() !== $b->isMutual()) {
                return $a->isMutual() ? -1 : 1;
            }

            return $b->score() <=> $a->score();
        });

        return $results;
    }

    /**
     * Per-album radar: other collectors ranked by how many of the stickers *I* am
     * still missing in this album they hold as spares. Two queries total — my owned
     * ids for the album, and every other collector's duplicates for it.
     *
     * @return array<int, array{user: User, canGet: int, samples: Sticker[]}>
     */
    public function albumRadar(User $me, Album $album): array
    {
        $myOwned = array_flip($this->userStickers->findOwnedStickerIds($me, $album));

        $byUser = [];
        foreach ($this->userStickers->findDuplicateHoldingsForAlbum($album, $me) as $holding) {
            $sticker = $holding->getSticker();
            if (isset($myOwned[$sticker->getId()])) {
                continue; // I already own this one — not useful to me.
            }

            $other = $holding->getUser();
            $uid = $other->getId();
            $byUser[$uid] ??= ['user' => $other, 'canGet' => 0, 'samples' => []];
            ++$byUser[$uid]['canGet'];
            if (\count($byUser[$uid]['samples']) < 8) {
                $byUser[$uid]['samples'][] = $sticker;
            }
        }

        usort($byUser, static fn (array $a, array $b): int => $b['canGet'] <=> $a['canGet']);

        return $byUser;
    }

    /**
     * Filter duplicate holdings down to stickers that a target user wants:
     * the target collects the album and does not own the sticker.
     *
     * @param \App\Entity\UserSticker[] $duplicates
     * @param array<int, mixed>         $targetOwnedIds  set of sticker ids owned
     * @param array<int, mixed>         $targetAlbumIds  set of collected album ids
     *
     * @return Sticker[]
     */
    private function wantedBy(array $duplicates, array $targetOwnedIds, array $targetAlbumIds): array
    {
        $wanted = [];
        foreach ($duplicates as $holding) {
            $sticker = $holding->getSticker();
            $albumId = $sticker->getAlbum()->getId();

            if (isset($targetAlbumIds[$albumId]) && !isset($targetOwnedIds[$sticker->getId()])) {
                $wanted[] = $sticker;
            }
        }

        return $wanted;
    }

    /**
     * @return array<int, true> set of album ids the user collects
     */
    private function collectedAlbumIds(User $user): array
    {
        $ids = [];
        foreach ($this->userAlbums->findByUser($user) as $userAlbum) {
            $ids[$userAlbum->getAlbum()->getId()] = true;
        }

        return $ids;
    }
}
