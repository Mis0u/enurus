<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\Clock\now;

/**
 * TODO #24 — purge des comptes jamais confirmés au-delà du délai de grâce. Contrairement à
 * `AccountDeletionService::purgeExpired()`, pas de `DeletedAccountTrace` créée ici : un compte
 * jamais vérifié n'a jamais été un vrai utilisateur actif, l'enregistrer déclencherait à tort
 * `DeletedAccountReregistrationNotifierService` lors d'une vraie réinscription ultérieure. Pas de
 * cascade de fichiers non plus : un compte non vérifié n'a jamais pu se connecter pour en créer
 * (avatar, séances...).
 */
final readonly class UnverifiedAccountPurgeService
{
    private const int GRACE_PERIOD_DAYS = 7;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {
    }

    public function purgeExpired(): int
    {
        $threshold = now()->modify(\sprintf('-%d days', self::GRACE_PERIOD_DAYS));
        $users = $this->userRepository->findUnverifiedOlderThan($threshold);

        foreach ($users as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        return \count($users);
    }
}
