<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.approved = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * All users for the admin list: pending accounts first, then newest.
     *
     * @return User[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.approved', 'ASC')
            ->addOrderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Other collectors (everyone except the given user).
     *
     * @return User[]
     */
    public function findOthers(User $user): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u != :me')
            ->andWhere('u.approved = true')
            ->andWhere('u.active = true')
            ->setParameter('me', $user)
            ->orderBy('u.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
