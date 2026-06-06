<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\User;
use App\Entity\UserAlbum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAlbum>
 */
class UserAlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAlbum::class);
    }

    public function save(UserAlbum $userAlbum, bool $flush = true): void
    {
        $this->getEntityManager()->persist($userAlbum);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserAlbum $userAlbum, bool $flush = true): void
    {
        $this->getEntityManager()->remove($userAlbum);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUserAndAlbum(User $user, Album $album): ?UserAlbum
    {
        return $this->findOneBy(['user' => $user, 'album' => $album]);
    }

    /**
     * @return UserAlbum[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->join('ua.album', 'a')
            ->addSelect('a')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.year', 'DESC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
