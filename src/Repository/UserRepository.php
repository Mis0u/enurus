<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    /**
     * @var list<Uuid>|null
     */
    private ?array $adminIdsCache = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (! $user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->password = $newHashedPassword;
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy([
            'email' => $email,
        ]);
    }

    /**
     * @return list<User>
     */
    public function findPendingDeletionOlderThan(\DateTimeImmutable $threshold): array
    {
        /** @var list<User> */
        return $this->createQueryBuilder('u')
            ->where('u.deletionRequestedAt IS NOT NULL')
            ->andWhere('u.deletionRequestedAt <= :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptes jamais confirmés (TODO #24) au-delà du délai de grâce — libère l'email pour une vraie
     * réinscription, cf. `UnverifiedAccountPurgeService`.
     *
     * @return list<User>
     */
    public function findUnverifiedOlderThan(\DateTimeImmutable $threshold): array
    {
        /** @var list<User> */
        return $this->createQueryBuilder('u')
            ->where('u.isVerified = false')
            ->andWhere('u.createdAt <= :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Destinataires d'une diffusion admin (compose EasyAdmin) — exclut les comptes en attente de
     * suppression et l'auteur de la diffusion lui-même, filtre en plus par locale si fournie.
     *
     * @return list<User>
     */
    public function findAllForBroadcast(Uuid $excludedUserId, ?string $locale = null): array
    {
        /** @var list<User> */
        return $this->broadcastRecipientsQueryBuilder($excludedUserId, $locale)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Compte rapide des destinataires d'une diffusion, sans charger les entités — utilisé pour un
     * retour immédiat à l'admin (le nombre réel de fils créés est connu ensuite, de façon
     * asynchrone, cf. SendContactBroadcastMessageHandler).
     */
    public function countForBroadcast(Uuid $excludedUserId, ?string $locale = null): int
    {
        /** @var int */
        return $this->broadcastRecipientsQueryBuilder($excludedUserId, $locale)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Autocomplete du destinataire d'un message admin 1-to-1 (EasyAdmin) — limité à 10 résultats,
     * exclut l'auteur (l'admin lui-même) et les comptes en attente de suppression.
     *
     * @return list<User>
     */
    public function searchForRecipientAutocomplete(string $query, Uuid $excludedUserId): array
    {
        /** @var list<User> */
        return $this->createQueryBuilder('u')
            ->where('u.deletionRequestedAt IS NULL')
            ->andWhere('u.id != :excludedUserId')
            ->andWhere('LOWER(u.email) LIKE :query')
            ->setParameter('excludedUserId', $excludedUserId)
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('u.email', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * `roles` est stocké en colonne JSON — DQL ne sait pas comparer un JSON brut (`LIKE`/`IN`
     * échouent sans cast SQL non portable), d'où une requête native plutôt qu'un `QueryBuilder`.
     * Utilisé pour exclure les comptes admin de l'admin User (jamais gérables via cette UI).
     * Mémoïsé pour la durée de la requête : `excludingAdminsQueryBuilder()` le rappelle à chaque
     * usage (stats du dashboard admin, filtres...) — sans cache, jusqu'à 13 exécutions redondantes
     * sur une seule page (détecté par Doctrine Doctor).
     *
     * @return list<Uuid>
     */
    public function findAdminIds(): array
    {
        if (null !== $this->adminIdsCache) {
            return $this->adminIdsCache;
        }

        /** @var list<string> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT id FROM users WHERE roles::text LIKE '%ROLE_ADMIN%'",
        );

        return $this->adminIdsCache = array_map(static fn (string $id): Uuid => Uuid::fromString($id), $rows);
    }

    /**
     * Statistiques du dashboard admin — mêmes gardes ROLE_ADMIN que `createIndexQueryBuilder()`
     * côté `UserCrudController` (jamais compté dans les chiffres visibles par l'admin lui-même).
     */
    public function countExcludingAdmins(): int
    {
        /** @var int */
        return $this->excludingAdminsQueryBuilder()
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        /** @var int */
        return $this->excludingAdminsQueryBuilder()
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countLoggedInSince(\DateTimeImmutable $since): int
    {
        /** @var int */
        return $this->excludingAdminsQueryBuilder()
            ->select('COUNT(u.id)')
            ->andWhere('u.lastLogin >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * @return array<string, int>
     */
    public function countGroupedByLocale(): array
    {
        return $this->countGroupedBy('u.locale');
    }

    /**
     * @return array<string, int>
     */
    public function countGroupedByGender(): array
    {
        return $this->countGroupedBy('u.gender');
    }

    /**
     * @return array<string, int>
     */
    public function countGroupedByUnitOfMeasure(): array
    {
        return $this->countGroupedBy('u.unitOfMeasure');
    }

    /**
     * Valeurs distinctes pour peupler le sélecteur du filtre admin "email" — même exclusion des
     * comptes ROLE_ADMIN que le reste de la section utilisateurs.
     *
     * @return list<string>
     */
    public function findDistinctEmails(): array
    {
        /** @var list<array{email: string}> $rows */
        $rows = $this->excludingAdminsQueryBuilder()
            ->select('DISTINCT u.email AS email')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_column($rows, 'email');
    }

    /**
     * Valeurs distinctes pour peupler le sélecteur du filtre admin "pseudo".
     *
     * @return list<string>
     */
    public function findDistinctNicknames(): array
    {
        /** @var list<array{nickname: string}> $rows */
        $rows = $this->excludingAdminsQueryBuilder()
            ->select('DISTINCT u.nickname AS nickname')
            ->orderBy('u.nickname', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_column($rows, 'nickname');
    }

    public function countPendingDeletion(): int
    {
        /** @var int */
        return $this->excludingAdminsQueryBuilder()
            ->select('COUNT(u.id)')
            ->andWhere('u.deletionRequestedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Dates d'inscription brutes depuis `$since`, pour bucketing en série temporelle côté service
     * (dashboard admin) — pas de GROUP BY ici, le découpage en semaines dépend de
     * DashboardPeriodCalculator::weekStartOf(), inutilement couplé si fait en DQL.
     *
     * @return list<\DateTimeImmutable>
     */
    public function findCreatedAtSince(\DateTimeImmutable $since): array
    {
        /** @var list<array{createdAt: \DateTimeImmutable}> $rows */
        $rows = $this->excludingAdminsQueryBuilder()
            ->select('u.createdAt AS createdAt')
            ->andWhere('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;

        return array_column($rows, 'createdAt');
    }

    private function broadcastRecipientsQueryBuilder(Uuid $excludedUserId, ?string $locale): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.deletionRequestedAt IS NULL')
            ->andWhere('u.id != :excludedUserId')
            ->setParameter('excludedUserId', $excludedUserId);

        if (null !== $locale) {
            $qb->andWhere('u.locale = :locale')->setParameter('locale', $locale);
        }

        return $qb;
    }

    private function excludingAdminsQueryBuilder(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');
        $adminIds = $this->findAdminIds();

        if ([] !== $adminIds) {
            $qb->andWhere('u.id NOT IN (:adminIds)')->setParameter('adminIds', $adminIds);
        }

        return $qb;
    }

    /**
     * @return array<string, int>
     */
    private function countGroupedBy(string $property): array
    {
        /** @var list<array{groupKey: mixed, total: int}> $rows */
        $rows = $this->excludingAdminsQueryBuilder()
            ->select($property . ' AS groupKey, COUNT(u.id) AS total')
            ->groupBy($property)
            ->getQuery()
            ->getResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $groupKey = $row['groupKey'];

            if ($groupKey instanceof \BackedEnum) {
                $key = (string) $groupKey->value;
            } elseif (\is_string($groupKey)) {
                $key = $groupKey;
            } else {
                throw new \LogicException('Unexpected group key type for user stats aggregation.');
            }

            $counts[$key] = (int) $row['total'];
        }

        return $counts;
    }
}
