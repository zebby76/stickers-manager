<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Sticker;
use App\Entity\User;
use App\Entity\UserSticker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSticker>
 */
class UserStickerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSticker::class);
    }

    public function save(UserSticker $userSticker, bool $flush = true): void
    {
        $this->getEntityManager()->persist($userSticker);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserSticker $userSticker, bool $flush = true): void
    {
        $this->getEntityManager()->remove($userSticker);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUserAndSticker(User $user, Sticker $sticker): ?UserSticker
    {
        return $this->findOneBy(['user' => $user, 'sticker' => $sticker]);
    }

    /**
     * Map of sticker id => quantity for a given album (only rows that exist).
     *
     * @return array<int, int>
     */
    public function getQuantityMapForAlbum(User $user, Album $album): array
    {
        $rows = $this->createQueryBuilder('us')
            ->select('IDENTITY(us.sticker) AS sticker_id', 'us.quantity')
            ->join('us.sticker', 's')
            ->andWhere('us.user = :user')
            ->andWhere('s.album = :album')
            ->setParameter('user', $user)
            ->setParameter('album', $album)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['sticker_id']] = (int) $row['quantity'];
        }

        return $map;
    }

    /**
     * Aggregate counts for an album: owned distinct stickers and total duplicates.
     *
     * @return array{owned: int, duplicates: int}
     */
    public function getCountsForAlbum(User $user, Album $album): array
    {
        $row = $this->createQueryBuilder('us')
            ->select(
                'COALESCE(SUM(CASE WHEN us.quantity >= 1 THEN 1 ELSE 0 END), 0) AS owned',
                'COALESCE(SUM(CASE WHEN us.quantity > 1 THEN us.quantity - 1 ELSE 0 END), 0) AS duplicates'
            )
            ->join('us.sticker', 's')
            ->andWhere('us.user = :user')
            ->andWhere('s.album = :album')
            ->setParameter('user', $user)
            ->setParameter('album', $album)
            ->getQuery()
            ->getSingleResult();

        return [
            'owned' => (int) $row['owned'],
            'duplicates' => (int) $row['duplicates'],
        ];
    }

    /**
     * Sticker ids the user owns (quantity >= 1), optionally limited to an album.
     *
     * @return int[]
     */
    public function findOwnedStickerIds(User $user, ?Album $album = null): array
    {
        $qb = $this->createQueryBuilder('us')
            ->select('IDENTITY(us.sticker) AS sticker_id')
            ->andWhere('us.user = :user')
            ->andWhere('us.quantity >= 1')
            ->setParameter('user', $user);

        if ($album !== null) {
            $qb->join('us.sticker', 's')
                ->andWhere('s.album = :album')
                ->setParameter('album', $album);
        }

        return array_map(
            static fn (array $r): int => (int) $r['sticker_id'],
            $qb->getQuery()->getArrayResult()
        );
    }

    /**
     * Duplicates available for trade: UserSticker rows with quantity > 1, with
     * sticker and album eagerly loaded.
     *
     * @return UserSticker[]
     */
    public function findDuplicates(User $user): array
    {
        return $this->createQueryBuilder('us')
            ->join('us.sticker', 's')->addSelect('s')
            ->join('s.album', 'a')->addSelect('a')
            ->andWhere('us.user = :user')
            ->andWhere('us.quantity > 1')
            ->setParameter('user', $user)
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
