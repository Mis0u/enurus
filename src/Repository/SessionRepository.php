<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    public function deleteAllForUser(Uuid $userId): int
    {
        /** @var int */
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('IDENTITY(s.user) = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    public function deleteOtherSessionsForUser(Uuid $userId, string $currentSessionId): int
    {
        /** @var int */
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('IDENTITY(s.user) = :userId')
            ->andWhere('s.sessionId != :currentSessionId')
            ->setParameter('userId', $userId)
            ->setParameter('currentSessionId', $currentSessionId)
            ->getQuery()
            ->execute();
    }
}
