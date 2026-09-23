<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Badge;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\Workout;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Repository\UserBadgeRepository;
use App\Service\Badge\BadgeCrossing;
use App\Service\Badge\BadgeCrossingResolver;
use App\Service\Badge\BadgeEligibilityResolver;
use App\Service\Badge\BadgeKey;
use App\Service\Badge\BadgeProgress;
use App\Service\Badge\BadgeProgressCalculator;
use App\Service\Badge\BadgeSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class BadgeSyncServiceTest extends TestCase
{
    private const string NOW = '2026-09-23 10:00:00';

    private const string CROSSING_WORKOUT_ID = '01999999-0000-7000-8000-000000000001';

    public function testGainedBadgeIsPersistedAndLinkedToTheTriggeringWorkout(): void
    {
        $user = new User();
        $workout = new Workout();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $result = $this->service($em, new BadgeProgress(1, 0, 0, 0.0), owned: [])->sync($user, $workout);

        self::assertCount(1, $result->gained);
        self::assertFalse($result->isRetroactive);
        self::assertSame($workout, $result->gained[0]->workout);
        self::assertSame(BadgeFamilyEnum::ASSIDUITY, $result->gained[0]->family);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $result->gained[0]->unlockedAt);
    }

    public function testAlreadyOwnedBadgeIsNeitherGainedNorFlushed(): void
    {
        $user = new User();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $owned = [$this->owned($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE)];
        $result = $this->service($em, new BadgeProgress(1, 0, 0, 0.0), $owned)->sync($user);

        self::assertSame([], $result->gained);
    }

    public function testBadgeNoLongerDeservedIsRemovedSilently(): void
    {
        $user = new User();
        $lost = $this->owned($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::SILVER);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($lost);
        $em->expects(self::once())->method('flush');

        $owned = [$this->owned($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE), $lost];
        $result = $this->service($em, new BadgeProgress(9, 0, 0, 0.0), $owned)->sync($user);

        self::assertSame([], $result->gained);
    }

    public function testSeniorityBadgeIsNeverRemoved(): void
    {
        $user = new User();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');

        $owned = [$this->owned($user, BadgeFamilyEnum::SENIORITY, BadgeTierEnum::BRONZE)];
        $this->service($em, new BadgeProgress(0, 0, 0, 0.0), $owned)->sync($user);
    }

    public function testRetroactiveBadgesTakeTheDateAndWorkoutThatReallyCrossedTheThreshold(): void
    {
        $crossingWorkout = new Workout();
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getReference')->willReturn($crossingWorkout);
        $crossings = [
            'assiduity:1' => new BadgeCrossing(new \DateTimeImmutable('2026-03-30 18:00:00'), self::CROSSING_WORKOUT_ID),
            'seniority:1' => new BadgeCrossing(new \DateTimeImmutable('2026-02-15 09:00:00'), null),
        ];

        $result = $this->service($em, new BadgeProgress(12, 7, 0, 0.0), owned: [], crossings: $crossings)->sync(new User(), new Workout());
        $gained = $this->indexGained($result->gained);

        self::assertTrue($result->isRetroactive);
        self::assertCount(4, $result->gained);
        self::assertEquals(new \DateTimeImmutable('2026-03-30 18:00:00'), $gained['assiduity:1']->unlockedAt);
        self::assertSame($crossingWorkout, $gained['assiduity:1']->workout);
        self::assertEquals(new \DateTimeImmutable('2026-02-15 09:00:00'), $gained['seniority:1']->unlockedAt);
        self::assertNull($gained['seniority:1']->workout);
        // Palier introuvable dans l'historique : date de la synchro, aucune séance.
        self::assertEquals(new \DateTimeImmutable(self::NOW), $gained['assiduity:2']->unlockedAt);
        self::assertNull($gained['assiduity:2']->workout);
    }

    public function testTriggeringWorkoutWinsOverTheHistory(): void
    {
        $trigger = new Workout();
        $crossings = [
            'assiduity:1' => new BadgeCrossing(new \DateTimeImmutable('2026-03-30 18:00:00'), self::CROSSING_WORKOUT_ID),
        ];

        $result = $this->service($this->createStub(EntityManagerInterface::class), new BadgeProgress(1, 0, 0, 0.0), owned: [], crossings: $crossings)->sync(new User(), $trigger);

        self::assertSame($trigger, $result->gained[0]->workout);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $result->gained[0]->unlockedAt);
    }

    public function testResultExposesTheComputedProgress(): void
    {
        $progress = new BadgeProgress(3, 0, 0, 0.0);

        $result = $this->service($this->createStub(EntityManagerInterface::class), $progress, owned: [])->sync(new User());

        self::assertSame($progress, $result->progress);
    }

    /**
     * @param list<UserBadge>              $owned
     * @param array<string, BadgeCrossing> $crossings
     */
    private function service(EntityManagerInterface $em, BadgeProgress $progress, array $owned, array $crossings = []): BadgeSyncService
    {
        $calculator = $this->createStub(BadgeProgressCalculator::class);
        $calculator->method('calculate')->willReturn($progress);

        $repository = $this->createStub(UserBadgeRepository::class);
        $repository->method('findByOwner')->willReturn($owned);

        $crossingResolver = $this->createStub(BadgeCrossingResolver::class);
        $crossingResolver->method('resolve')->willReturn($crossings);

        return new BadgeSyncService($calculator, new BadgeEligibilityResolver(), $crossingResolver, $repository, $em, new MockClock(self::NOW));
    }

    /**
     * @param list<UserBadge> $badges
     * @return array<string, UserBadge>
     */
    private function indexGained(array $badges): array
    {
        $indexed = [];

        foreach ($badges as $badge) {
            $indexed[$badge->key()->id()] = $badge;
        }

        return $indexed;
    }

    private function owned(User $user, BadgeFamilyEnum $family, BadgeTierEnum $tier): UserBadge
    {
        return UserBadge::unlock($user, new BadgeKey($family, $tier), new \DateTimeImmutable('2026-01-01'), null);
    }
}
