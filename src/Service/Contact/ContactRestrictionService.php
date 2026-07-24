<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\User;
use App\Enum\Contact\ContactRestrictionDurationEnum;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\Clock\now;

final readonly class ContactRestrictionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function restrict(User $user, ?ContactRestrictionDurationEnum $duration, bool $permanent): void
    {
        $user->contactRestrictedPermanently = $permanent;
        $user->contactRestrictionDuration = $permanent ? null : $duration;
        $user->contactRestrictedUntil = $permanent || null === $duration ? null : $this->resolveUntil($duration);

        $this->entityManager->flush();
    }

    public function liftRestriction(User $user): void
    {
        $user->contactRestrictedPermanently = false;
        $user->contactRestrictedUntil = null;
        $user->contactRestrictionDuration = null;

        $this->entityManager->flush();
    }

    private function resolveUntil(ContactRestrictionDurationEnum $duration): \DateTimeImmutable
    {
        return match ($duration) {
            ContactRestrictionDurationEnum::ONE_WEEK => now()->modify('+7 days'),
            ContactRestrictionDurationEnum::ONE_MONTH => now()->modify('+1 month'),
        };
    }
}
