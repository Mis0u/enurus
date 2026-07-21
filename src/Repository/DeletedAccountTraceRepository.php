<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeletedAccountTrace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeletedAccountTrace>
 */
class DeletedAccountTraceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeletedAccountTrace::class);
    }

    public function findByEmailHash(string $emailHash): ?DeletedAccountTrace
    {
        return $this->findOneBy([
            'emailHash' => $emailHash,
        ]);
    }

    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        $affected = $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.deletedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();

        if (! is_int($affected)) {
            throw new \LogicException('Expected the DELETE query to return an affected row count.');
        }

        return $affected;
    }
}
