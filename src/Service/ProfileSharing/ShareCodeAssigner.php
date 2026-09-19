<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Ne flush pas : l'appelant persiste avec le reste de la modification en cours. La vérification
 * de disponibilité n'élimine pas la course entre deux requêtes simultanées — c'est l'index unique
 * en base sur `users.share_code` qui reste la garantie finale.
 */
final readonly class ShareCodeAssigner
{
    public const int MAX_ATTEMPTS = 10;

    public function __construct(
        private ShareCodeGeneratorInterface $shareCodeGenerator,
        private UserRepository $userRepository,
    ) {
    }

    public function assign(User $user): void
    {
        $user->shareCode = $this->generateUnusedCode();
    }

    private function generateUnusedCode(): string
    {
        for ($attempt = 0; self::MAX_ATTEMPTS > $attempt; ++$attempt) {
            $code = $this->shareCodeGenerator->generate();

            if (! $this->userRepository->existsByShareCode($code)) {
                return $code;
            }
        }

        throw new \LogicException(\sprintf('No available share code found after %d attempts.', self::MAX_ATTEMPTS));
    }
}
