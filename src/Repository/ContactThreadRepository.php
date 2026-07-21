<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * `JOIN FETCH` sur les messages pour que l'aperçu du dernier message affiché dans la liste
     * ne déclenche pas un lazy-load par fil (N+1). Paginée via Knp, qui gère correctement le
     * LIMIT/OFFSET sur une collection one-to-many jointe (Doctrine\ORM\Tools\Pagination\Paginator
     * avec fetchJoinCollection, contrairement à un setMaxResults() manuel).
     */
    public function findByOwnerOrderedByActivity(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.messages', 'm')
            ->addSelect('m')
            ->andWhere('t.owner = :owner')
            ->andWhere('t.hiddenByUserAt IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('t.updatedAt', 'DESC');
    }
}
