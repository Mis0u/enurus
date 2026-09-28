<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Onboarding;

use App\Entity\User;
use App\Service\Onboarding\GuidedTourService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class GuidedTourServiceTest extends TestCase
{
    public function testFirstDisplayShowsTheTourAndMarksItSeen(): void
    {
        $user = new User();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $isFirstDisplay = (new GuidedTourService($entityManager))->consumeFirstDisplay($user);

        self::assertTrue($isFirstDisplay);
        self::assertTrue($user->guidedTourSeen);
    }

    /**
     * Un utilisateur qui se reconnecte sans avoir enregistré de séance ne revoit pas le tour.
     */
    public function testTourAlreadySeenIsNotShownAgain(): void
    {
        $user = new User();
        $user->guidedTourSeen = true;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        self::assertFalse((new GuidedTourService($entityManager))->consumeFirstDisplay($user));
    }
}
