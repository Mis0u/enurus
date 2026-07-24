<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Entity\User;
use App\Enum\Contact\ContactRestrictionDurationEnum;
use App\Service\Contact\ContactRestrictionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ContactRestrictionServiceTest extends TestCase
{
    public function testRestrictOneWeekSetsUntilSevenDaysAhead(): void
    {
        $user = $this->createUser();
        $service = new ContactRestrictionService($this->createStub(EntityManagerInterface::class));

        $service->restrict($user, ContactRestrictionDurationEnum::ONE_WEEK, permanent: false);

        self::assertFalse($user->contactRestrictedPermanently);
        self::assertSame(ContactRestrictionDurationEnum::ONE_WEEK, $user->contactRestrictionDuration);
        self::assertNotNull($user->contactRestrictedUntil);
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+7 days'))->getTimestamp(),
            $user->contactRestrictedUntil->getTimestamp(),
            5,
        );
    }

    public function testRestrictOneMonthSetsUntilOneMonthAhead(): void
    {
        $user = $this->createUser();
        $service = new ContactRestrictionService($this->createStub(EntityManagerInterface::class));

        $service->restrict($user, ContactRestrictionDurationEnum::ONE_MONTH, permanent: false);

        self::assertNotNull($user->contactRestrictedUntil);
        self::assertEqualsWithDelta(
            (new \DateTimeImmutable('+1 month'))->getTimestamp(),
            $user->contactRestrictedUntil->getTimestamp(),
            5,
        );
    }

    public function testRestrictPermanentlyIgnoresDurationAndUntil(): void
    {
        $user = $this->createUser();
        $service = new ContactRestrictionService($this->createStub(EntityManagerInterface::class));

        $service->restrict($user, ContactRestrictionDurationEnum::ONE_WEEK, permanent: true);

        self::assertTrue($user->contactRestrictedPermanently);
        self::assertNull($user->contactRestrictionDuration);
        self::assertNull($user->contactRestrictedUntil);
    }

    public function testLiftRestrictionResetsAllFields(): void
    {
        $user = $this->createUser();
        $user->contactRestrictedPermanently = true;
        $user->contactRestrictedUntil = new \DateTimeImmutable('+1 month');
        $user->contactRestrictionDuration = ContactRestrictionDurationEnum::ONE_MONTH;

        $service = new ContactRestrictionService($this->createStub(EntityManagerInterface::class));
        $service->liftRestriction($user);

        self::assertFalse($user->contactRestrictedPermanently);
        self::assertNull($user->contactRestrictedUntil);
        self::assertNull($user->contactRestrictionDuration);
        self::assertFalse($user->isContactRestricted);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->email = 'restricted-target@test.com';
        $user->password = 'hashed';
        $user->nickname = 'RestrictedTarget';
        $user->lastLogin = new \DateTimeImmutable();

        return $user;
    }
}
