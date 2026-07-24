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
}
