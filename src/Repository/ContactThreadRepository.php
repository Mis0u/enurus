<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactThread;
use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
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

    /**
     * `JOIN FETCH` sur les messages pour permettre au service appelant de récupérer leurs
     * `imagePath` (nettoyage disque) sans déclencher un lazy-load par fil. Exclut les fils issus
     * d'une diffusion (`broadcast IS NULL`) — hors scope de la purge 1 to 1.
     *
     * @return list<ContactThread>
     */
    public function findClosedBefore(\DateTimeImmutable $threshold): array
    {
        /** @var list<ContactThread> */
        return $this->createQueryBuilder('t')
            ->leftJoin('t.messages', 'm')
            ->addSelect('m')
            ->andWhere('t.broadcast IS NULL')
            ->andWhere('t.status = :status')
            ->andWhere('t.closedAt <= :threshold')
            ->setParameter('status', ContactThreadStatusEnum::CLOSED)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Nombre de fils en attente d'une réponse admin — sert de badge sur le menu "Messagerie"
     * du back-office. Exclut les fils issus d'une diffusion (jamais répondables, cf.
     * ContactThreadCrudController::createIndexQueryBuilder()).
     */
    public function countAwaitingAdminReply(): int
    {
        /** @var int */
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.status = :status')
            ->andWhere('t.broadcast IS NULL')
            ->setParameter('status', ContactThreadStatusEnum::AWAITING_ADMIN_REPLY)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
