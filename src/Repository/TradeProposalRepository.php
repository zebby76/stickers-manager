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

    /**
     * All proposals for the admin overview, newest activity first, with both
     * participants eagerly loaded. Optionally filtered by status.
     *
     * @return TradeProposal[]
     */
    public function findAllForAdmin(?TradeStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.fromUser', 'f')->addSelect('f')
            ->join('t.toUser', 'r')->addSelect('r')
            ->orderBy('t.updatedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Number of proposals per status (for the admin filter pills).
     *
     * @return array<string, int> status value => count
     */
    public function countsByStatus(): array
    {
        $counts = [];
        foreach ($this->createQueryBuilder('t')
            ->select('t.status AS status', 'COUNT(t.id) AS total')
            ->groupBy('t.status')
            ->getQuery()->getArrayResult() as $row) {
            $status = $row['status'];
            $key = $status instanceof TradeStatus ? $status->value : (string) $status;
            $counts[$key] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Every proposal the user takes part in (either side), newest activity first,
     * with both participants eagerly loaded. Optionally filtered by status.
     *
     * @return TradeProposal[]
     */
    public function findForUser(User $user, ?TradeStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.fromUser', 'f')->addSelect('f')
            ->join('t.toUser', 'r')->addSelect('r')
            ->andWhere('t.fromUser = :user OR t.toUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.updatedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Number of the user's own proposals per status (for the filter pills).
     *
     * @return array<string, int> status value => count
     */
    public function countsByStatusForUser(User $user): array
    {
        $counts = [];
        foreach ($this->createQueryBuilder('t')
            ->select('t.status AS status', 'COUNT(t.id) AS total')
            ->andWhere('t.fromUser = :user OR t.toUser = :user')
            ->setParameter('user', $user)
            ->groupBy('t.status')
            ->getQuery()->getArrayResult() as $row) {
            $status = $row['status'];
            $key = $status instanceof TradeStatus ? $status->value : (string) $status;
            $counts[$key] = (int) $row['total'];
        }

        return $counts;
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

    /**
     * Completed trades the user took part in, as either side (for the trade
     * achievement badges / reputation).
     */
    public function countCompletedFor(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.fromUser = :user OR t.toUser = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', TradeStatus::Completed)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Completed-trade counts for several users at once (avoids an N+1 when
     * showing reputation on a list of trade partners).
     *
     * @param int[] $userIds
     *
     * @return array<int, int> userId => completed-trade count (every requested id is present)
     */
    public function completedCountsForUsers(array $userIds): array
    {
        $counts = array_fill_keys($userIds, 0);
        if ($userIds === []) {
            return $counts;
        }

        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(t.fromUser) AS fromId', 'IDENTITY(t.toUser) AS toId')
            ->andWhere('t.status = :status')
            ->andWhere('t.fromUser IN (:ids) OR t.toUser IN (:ids)')
            ->setParameter('status', TradeStatus::Completed)
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            foreach ([(int) $row['fromId'], (int) $row['toId']] as $id) {
                if (isset($counts[$id])) {
                    ++$counts[$id];
                }
            }
        }

        return $counts;
    }
}
