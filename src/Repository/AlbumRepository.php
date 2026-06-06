<?php

namespace App\Repository;

use App\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function save(Album $album, bool $flush = true): void
    {
        $this->getEntityManager()->persist($album);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Album $album, bool $flush = true): void
    {
        $this->getEntityManager()->remove($album);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Album[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.year', 'DESC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
