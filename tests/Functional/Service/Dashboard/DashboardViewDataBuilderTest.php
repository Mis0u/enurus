<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Dashboard;

use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\UserRepository;
use App\Service\Dashboard\DashboardState;
use App\Service\Dashboard\DashboardUnlockService;
use App\Service\Dashboard\DashboardViewDataBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DashboardViewDataBuilderTest extends KernelTestCase
{
    private const string USER_WITH_WORKOUTS = 'user-fixture-26-workout@test.com';

    private const string USER_WITH_NO_DATA = 'user-fixture-0@test.com';

    private DashboardViewDataBuilder $builder;

    private DashboardUnlockService $unlockService;

    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var DashboardViewDataBuilder $builder */
        $builder = static::getContainer()->get(DashboardViewDataBuilder::class);
        $this->builder = $builder;

        /** @var DashboardUnlockService $unlockService */
        $unlockService = static::getContainer()->get(DashboardUnlockService::class);
        $this->unlockService = $unlockService;

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $this->userRepository = $userRepository;
    }

    public function testBuildsTheWholeDashboardOfAUserWithWorkouts(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);
        $state = $this->unlockService->getStateForUser($user);

        $data = $this->builder->build($user, $user, $state);

        self::assertSame($state, $data->dashboardState);
        self::assertGreaterThanOrEqual(1, $data->sessionStats['last']['sessions']);
        self::assertNotNull($data->regularityData);
        self::assertNotSame([], $data->tonnageData);
        self::assertNotSame([], $data->muscles->session->primary);
        self::assertFalse($data->hasNoVisibleContent);
    }

    public function testEveryWorkoutBasedWidgetIsVisibleWhenNoneIsHidden(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);

        $data = $this->builder->build($user, $user, $this->unlockService->getStateForUser($user));

        foreach ([DashboardWidgetEnum::SESSION, DashboardWidgetEnum::TONNAGE, DashboardWidgetEnum::MUSCLE_DISTRIBUTION, DashboardWidgetEnum::REGULARITY] as $widget) {
            self::assertTrue($data->visibleWidgets[$widget->value], $widget->value . ' should be visible');
        }
    }

    public function testEveryWidgetHasAVisibilityEntryEvenWhenItIsLocked(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);

        $data = $this->builder->build($user, $user, $this->unlockService->getStateForUser($user));

        foreach (DashboardWidgetEnum::cases() as $widget) {
            self::assertArrayHasKey($widget->value, $data->visibleWidgets);
        }
    }

    public function testWidgetHiddenByTheUserIsNotVisible(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);
        $user->hiddenWidgets = [DashboardWidgetEnum::TONNAGE->value];

        $data = $this->builder->build($user, $user, $this->unlockService->getStateForUser($user));

        self::assertFalse($data->visibleWidgets[DashboardWidgetEnum::TONNAGE->value]);
        self::assertTrue($data->visibleWidgets[DashboardWidgetEnum::SESSION->value]);
        self::assertFalse($data->hasNoVisibleContent);
    }

    public function testHidingEveryWidgetFlagsTheDashboardAsHavingNoVisibleContent(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);
        $user->hiddenWidgets = array_map(static fn (DashboardWidgetEnum $widget): string => $widget->value, DashboardWidgetEnum::cases());

        $data = $this->builder->build($user, $user, $this->unlockService->getStateForUser($user));

        self::assertTrue($data->hasNoVisibleWidgets);
        self::assertTrue($data->hasNoVisibleContent);
    }

    public function testHidingEveryWidgetWhileRegularityIsLockedLeavesTheLockedCardAsContent(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);
        $user->hiddenWidgets = array_map(static fn (DashboardWidgetEnum $widget): string => $widget->value, DashboardWidgetEnum::cases());

        $data = $this->builder->build($user, $user, new DashboardState(1));

        self::assertTrue($data->hasNoVisibleWidgets);
        self::assertFalse($data->hasNoVisibleContent);
    }

    public function testSomeVisibleWidgetMeansThereIsNoEmptyStateOfAnyKind(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);

        $data = $this->builder->build($user, $user, $this->unlockService->getStateForUser($user));

        self::assertFalse($data->hasNoVisibleWidgets);
        self::assertFalse($data->hasNoVisibleContent);
    }

    public function testLockedWidgetsAreNotComputedFromTheStateGiven(): void
    {
        $user = $this->findUser(self::USER_WITH_WORKOUTS);

        $data = $this->builder->build($user, $user, new DashboardState(1));

        self::assertNull($data->regularityData);
        self::assertSame([], $data->muscles->week->primary);
        self::assertSame([], $data->muscles->month->primary);
    }

    public function testWeightUnitFollowsTheViewerWhileTheDataIsTheOwners(): void
    {
        $owner = $this->findUser(self::USER_WITH_WORKOUTS);
        $viewer = new User();
        $viewer->unitOfMeasure = UnitOfMeasureEnum::LBS;

        $data = $this->builder->build($owner, $viewer, $this->unlockService->getStateForUser($owner));

        self::assertSame(UnitOfMeasureEnum::LBS->value, $data->tonnageData['unit']);
        self::assertGreaterThanOrEqual(1, $data->sessionStats['last']['sessions']);
    }

    public function testWidgetVisibilityFollowsTheOwnerNotTheViewer(): void
    {
        $owner = $this->findUser(self::USER_WITH_WORKOUTS);
        $viewer = new User();
        $viewer->hiddenWidgets = [DashboardWidgetEnum::TONNAGE->value];

        $data = $this->builder->build($owner, $viewer, $this->unlockService->getStateForUser($owner));

        self::assertTrue($data->visibleWidgets[DashboardWidgetEnum::TONNAGE->value]);
    }

    public function testRefusesADashboardWithoutAnyWorkout(): void
    {
        $user = $this->findUser(self::USER_WITH_NO_DATA);

        $this->expectException(\LogicException::class);

        $this->builder->build($user, $user, $this->unlockService->getStateForUser($user));
    }

    private function findUser(string $email): User
    {
        return $this->userRepository->findOneByEmail($email) ?? throw new \LogicException(\sprintf('Fixture user "%s" not found.', $email));
    }
}
