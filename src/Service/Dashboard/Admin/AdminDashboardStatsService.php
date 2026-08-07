<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use App\Enum\Entity\User\GenderEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Model\Chart;

final readonly class AdminDashboardStatsService
{
    /**
     * Fenêtre de la courbe de tendance des inscriptions — 12 semaines pleines, assez pour repérer
     * une tendance sans tasser les points sur un widget de largeur modeste.
     */
    private const int TREND_WEEKS = 12;

    private const int PERCENT_SCALE = 100;

    private const int PERCENT_ROUNDING_PRECISION = 1;

    public function __construct(
        private UserRepository $userRepository,
        private WorkoutRepository $workoutRepository,
        private DashboardPeriodCalculator $periodCalculator,
        private AdminLocaleChartBuilder $localeChartBuilder,
        private AdminUnitOfMeasureChartBuilder $unitOfMeasureChartBuilder,
        private AdminGenderChartBuilder $genderChartBuilder,
        private AdminRegistrationTrendChartBuilder $registrationTrendChartBuilder,
        private MessengerQueueStatsService $messengerQueueStatsService,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{
     *     totalUsers: int,
     *     newToday: int,
     *     newThisWeek: int,
     *     newThisMonth: int,
     *     pendingDeletions: int,
     *     workoutsLast30Days: int,
     *     messengerQueue: array{pending: int, failed: int},
     *     localeChart: Chart,
     *     unitOfMeasureChart: Chart,
     *     genderChart: Chart,
     *     registrationTrendChart: Chart,
     * }
     */
    public function getData(): array
    {
        $now = $this->clock->now();

        return [
            'totalUsers' => $this->userRepository->countExcludingAdmins(),
            'newToday' => $this->userRepository->countCreatedSince($now->setTime(0, 0)),
            'newThisWeek' => $this->userRepository->countCreatedSince($now->modify('-7 days')),
            'newThisMonth' => $this->userRepository->countCreatedSince($now->modify('-30 days')),
            'pendingDeletions' => $this->userRepository->countPendingDeletion(),
            'workoutsLast30Days' => $this->workoutRepository->countPerformedSince($now->modify('-30 days'), $this->userRepository->findAdminIds()),
            'messengerQueue' => $this->messengerQueueStatsService->getData(),
            'localeChart' => $this->buildLocaleChart(),
            'unitOfMeasureChart' => $this->buildUnitOfMeasureChart(),
            'genderChart' => $this->buildGenderChart(),
            'registrationTrendChart' => $this->buildRegistrationTrendChart($now),
        ];
    }

    private function buildLocaleChart(): Chart
    {
        $counts = $this->userRepository->countGroupedByLocale();
        $total = $this->userRepository->countExcludingAdmins();

        $labels = [];
        $values = [];
        foreach (LocaleAllowedEnum::cases() as $locale) {
            $labels[] = strtoupper($locale->value);
            $values[] = $this->percent($counts[$locale->value] ?? 0, $total);
        }

        return $this->localeChartBuilder->build($labels, $values);
    }

    private function buildUnitOfMeasureChart(): Chart
    {
        $counts = $this->userRepository->countGroupedByUnitOfMeasure();
        $total = $this->userRepository->countExcludingAdmins();

        $cases = UnitOfMeasureEnum::cases();
        usort(
            $cases,
            static fn (UnitOfMeasureEnum $a, UnitOfMeasureEnum $b): int => ($counts[$b->value] ?? 0) <=> ($counts[$a->value] ?? 0),
        );

        $labels = [];
        $values = [];
        foreach ($cases as $unit) {
            $labels[] = strtoupper($unit->value);
            $values[] = $this->percent($counts[$unit->value] ?? 0, $total);
        }

        return $this->unitOfMeasureChartBuilder->build($labels, $values);
    }

    private function buildGenderChart(): Chart
    {
        $counts = $this->userRepository->countGroupedByGender();
        $total = $this->userRepository->countExcludingAdmins();

        $labels = [];
        $values = [];
        foreach (GenderEnum::cases() as $gender) {
            $labels[] = $this->translator->trans('field.gender.' . $gender->value, [], 'common', LocaleAllowedEnum::FR->value);
            $values[] = $this->percent($counts[$gender->value] ?? 0, $total);
        }

        return $this->genderChartBuilder->build($labels, $values);
    }

    private function percent(int $part, int $total): float
    {
        return 0 === $total ? 0.0 : round($part / $total * self::PERCENT_SCALE, self::PERCENT_ROUNDING_PRECISION);
    }

    private function buildRegistrationTrendChart(\DateTimeImmutable $now): Chart
    {
        $firstWeekStart = $this->periodCalculator->weekStartOf($now)->modify('-' . (self::TREND_WEEKS - 1) . ' weeks');
        $createdAtList = $this->userRepository->findCreatedAtSince($firstWeekStart);

        $counts = [];
        foreach ($createdAtList as $createdAt) {
            $key = $this->periodCalculator->weekStartOf($createdAt)->format('Y-m-d');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $formatter = new \IntlDateFormatter(LocaleAllowedEnum::FR->value, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'd MMM');

        $labels = [];
        $values = [];
        $cursor = $firstWeekStart;
        for ($i = 0; self::TREND_WEEKS > $i; ++$i) {
            $key = $cursor->format('Y-m-d');
            $formatted = $formatter->format($cursor);
            $labels[] = false !== $formatted ? $formatted : $key;
            $values[] = $counts[$key] ?? 0;
            $cursor = $cursor->modify('+1 week');
        }

        return $this->registrationTrendChartBuilder->build($labels, $values);
    }
}
