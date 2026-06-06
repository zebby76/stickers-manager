<?php

namespace App\Repository;

use App\Entity\TradeProposal;
use App\Entity\User;
use App\Enum\TradeStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TradeProposal>
 */
class TradeProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TradeProposal::class);
    }

    public function save(TradeProposal $proposal, bool $flush = true): void
    {
        $this->getEntityManager()->persist($proposal);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Proposals received by the user (incoming).
     *
     * @return TradeProposal[]
     */
    public function findIncoming(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.toUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Proposals created by the user (outgoing).
     *
     * @return TradeProposal[]
     */
    public function findOutgoing(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.fromUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingIncoming(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.toUser = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', TradeStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
