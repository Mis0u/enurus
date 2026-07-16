<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactThread>
 */
final class ContactThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactThread::class);
    }

    /**
     * `JOIN FETCH` sur les messages (sans `setMaxResults`) pour que l'aperçu du dernier message
     * affiché dans la liste ne déclenche pas un lazy-load par fil (N+1).
     *
     * @return array<int, ContactThread>
     */
    public function findByOwnerOrderedByActivity(User $owner): array
    {
        /** @var list<ContactThread> $result */
        $result = $this->createQueryBuilder('t')
            ->leftJoin('t.messages', 'm')
            ->addSelect('m')
            ->andWhere('t.owner = :owner')
            ->andWhere('t.hiddenByUserAt IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('t.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
