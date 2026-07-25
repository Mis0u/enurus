<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactBroadcast;
use App\Entity\ContactPollVote;
use App\Entity\ContactThread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactPollVote>
 */
final class ContactPollVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactPollVote::class);
    }

    /**
     * @return array<string, int> optionId (RFC4122) => nombre de votes
     */
    public function countByOption(ContactBroadcast $broadcast): array
    {
        /** @var list<array{optionId: string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.option) AS optionId', 'COUNT(v.id) AS total')
            ->join('v.thread', 't')
            ->andWhere('t.broadcast = :broadcast')
            ->setParameter('broadcast', $broadcast)
            ->groupBy('v.option')
            ->getQuery()
            ->getResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['optionId']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countForBroadcast(ContactBroadcast $broadcast): int
    {
        /** @var int */
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->join('v.thread', 't')
            ->andWhere('t.broadcast = :broadcast')
            ->setParameter('broadcast', $broadcast)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Participation groupée par une propriété de `User` (locale ou genre) — parcourt les fils de
     * la diffusion (un par destinataire, cf. SendContactBroadcastMessageHandler) plutôt que la
     * table des votes seule, pour connaître aussi les destinataires n'ayant pas voté.
     *
     * @return array<string, array{voted: int, total: int}> valeur du groupe (ex. "fr", "male") => compte
     */
    public function countParticipationGroupedByUserProperty(ContactBroadcast $broadcast, string $userProperty): array
    {
        /** @var list<array{groupKey: mixed, total: int|string, voted: int|string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('u.' . $userProperty . ' AS groupKey', 'COUNT(t.id) AS total', 'COUNT(v.id) AS voted')
            ->from(ContactThread::class, 't')
            ->join('t.owner', 'u')
            ->leftJoin('t.pollVote', 'v')
            ->andWhere('t.broadcast = :broadcast')
            ->setParameter('broadcast', $broadcast)
            ->groupBy('u.' . $userProperty)
            ->getQuery()
            ->getResult()
        ;

        $result = [];
        foreach ($rows as $row) {
            // `u.gender` est une colonne enum-typée (Doctrine hydrate un GenderEnum), contrairement
            // à `u.locale` (chaîne brute, cf. CLAUDE.md) — jamais un enum utilisable tel quel comme
            // clé de tableau.
            $rawGroupKey = $row['groupKey'];
            $groupKey = match (true) {
                $rawGroupKey instanceof \BackedEnum => (string) $rawGroupKey->value,
                is_string($rawGroupKey) => $rawGroupKey,
                default => throw new \LogicException('Unexpected group key type from Doctrine aggregation.'),
            };

            $result[$groupKey] = [
                'voted' => (int) $row['voted'],
                'total' => (int) $row['total'],
            ];
        }

        return $result;
    }
}
