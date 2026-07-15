<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactThreadMessage>
 */
class ContactThreadMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactThreadMessage::class);
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.thread', 't')
            ->andWhere('t.owner = :owner')
            ->andWhere('m.fromAdmin = true')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('owner', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, ContactThreadMessage>
     */
    public function findUnreadAdminMessagesForThread(ContactThread $thread): array
    {
        /** @var list<ContactThreadMessage> $result */
        $result = $this->createQueryBuilder('m')
            ->andWhere('m.thread = :thread')
            ->andWhere('m.fromAdmin = true')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<string, int> threadId (RFC4122) => nombre de messages admin non lus
     */
    public function countUnreadPerThreadForUser(User $user): array
    {
        /** @var array<int, array{threadId: mixed, unread: mixed}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.thread) AS threadId, COUNT(m.id) AS unread')
            ->join('m.thread', 't')
            ->andWhere('t.owner = :owner')
            ->andWhere('m.fromAdmin = true')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('owner', $user)
            ->groupBy('m.thread')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            /** @var string|\Stringable $threadId */
            $threadId = $row['threadId'];
            /** @var numeric $unread */
            $unread = $row['unread'];

            $map[(string) $threadId] = (int) $unread;
        }

        return $map;
    }
}
