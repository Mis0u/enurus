<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use App\Entity\ContactBroadcast;
use App\Enum\Entity\User\GenderEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\ContactPollVoteRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Résultats d'un sondage (ContactBroadcast::isPoll()) pour l'admin uniquement — jamais exposé à
 * l'utilisateur (cf. discussion produit : "strictement admin-only").
 */
final readonly class ContactPollStatsService
{
    private const int PERCENT_SCALE = 100;

    private const int PERCENT_ROUNDING_PRECISION = 1;

    public function __construct(
        private ContactPollVoteRepository $contactPollVoteRepository,
        private ContactPollOptionsChartBuilder $optionsChartBuilder,
        private ContactPollLocaleParticipationChartBuilder $localeChartBuilder,
        private ContactPollGenderParticipationChartBuilder $genderChartBuilder,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{
     *     votedCount: int,
     *     recipientCount: int,
     *     participationPercent: float,
     *     optionsChart: Chart,
     *     localeChart: Chart,
     *     genderChart: Chart,
     * }
     */
    public function getData(ContactBroadcast $broadcast): array
    {
        $votedCount = $this->contactPollVoteRepository->countForBroadcast($broadcast);
        $recipientCount = $broadcast->recipientCount;

        [$localeLabels, $localePercentages] = $this->groupPercentages($broadcast, 'locale', $this->localeLabels());
        [$genderLabels, $genderPercentages] = $this->groupPercentages($broadcast, 'gender', $this->genderLabels());

        return [
            'votedCount' => $votedCount,
            'recipientCount' => $recipientCount,
            'participationPercent' => $this->percent($votedCount, $recipientCount),
            'optionsChart' => $this->buildOptionsChart($broadcast),
            'localeChart' => $this->localeChartBuilder->build($localeLabels, $localePercentages),
            'genderChart' => $this->genderChartBuilder->build($genderLabels, $genderPercentages),
        ];
    }

    private function buildOptionsChart(ContactBroadcast $broadcast): Chart
    {
        $counts = $this->contactPollVoteRepository->countByOption($broadcast);

        $labels = [];
        $values = [];
        foreach ($broadcast->pollOptions as $option) {
            $labels[] = $option->label;
            $values[] = $counts[(string) $option->id] ?? 0;
        }

        return $this->optionsChartBuilder->build($labels, $values);
    }

    /**
     * @param array<string, string> $groupLabels valeur brute (ex. "fr", "male") => libellé affiché
     * @return array{0: list<string>, 1: list<float>}
     */
    private function groupPercentages(ContactBroadcast $broadcast, string $userProperty, array $groupLabels): array
    {
        $counts = $this->contactPollVoteRepository->countParticipationGroupedByUserProperty($broadcast, $userProperty);

        $labels = [];
        $percentages = [];
        foreach ($groupLabels as $rawValue => $label) {
            $group = $counts[$rawValue] ?? [
                'voted' => 0,
                'total' => 0,
            ];
            $labels[] = $label;
            $percentages[] = $this->percent($group['voted'], $group['total']);
        }

        return [$labels, $percentages];
    }

    /**
     * @return array<string, string>
     */
    private function localeLabels(): array
    {
        $labels = [];
        foreach (LocaleAllowedEnum::cases() as $locale) {
            $labels[$locale->value] = strtoupper($locale->value);
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function genderLabels(): array
    {
        $labels = [];
        foreach (GenderEnum::cases() as $gender) {
            $labels[$gender->value] = $this->translator->trans('field.gender.' . $gender->value, [], 'common', LocaleAllowedEnum::FR->value);
        }

        return $labels;
    }

    private function percent(int $part, int $total): float
    {
        return 0 === $total ? 0.0 : round($part / $total * self::PERCENT_SCALE, self::PERCENT_ROUNDING_PRECISION);
    }
}
