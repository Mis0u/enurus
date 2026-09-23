<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\User;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Libellés d'un badge dans la langue et l'unité de celui qui regarde : nom complet ("100 séances",
 * "22 046 lb"), texte court du bandeau ("100", "1 AN", "22K LB") et progression ("73 / 100
 * séances"). Les seuils restent en tonnes métriques ; seule la présentation est convertie.
 * Non `final` : stubbé par `BadgeViewBuilderTest`.
 */
readonly class BadgeLabelFormatter
{
    private const int MONTHS_PER_YEAR = 12;

    private const float KG_PER_TONNE = 1000.0;

    private const float THOUSAND = 1000.0;

    private const float MILLION = 1_000_000.0;

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function name(BadgeKey $key, User $viewer): string
    {
        if (BadgeFamilyEnum::LEGEND === $key->family) {
            return $this->translator->trans('badge.name.legend', [], 'navigation');
        }

        $threshold = $key->family->thresholdOf($key->tier);

        return match ($key->family) {
            BadgeFamilyEnum::SENIORITY => $this->seniorityName($threshold),
            BadgeFamilyEnum::TONNAGE => $this->tonnageName($threshold, $viewer->unitOfMeasure),
            default => $this->translator->trans('badge.name.' . $key->family->value, [
                'count' => $threshold,
            ], 'navigation'),
        };
    }

    public function ribbon(BadgeKey $key, User $viewer): string
    {
        if (BadgeFamilyEnum::LEGEND === $key->family) {
            return $this->translator->trans('badge.ribbon.legend', [], 'navigation');
        }

        $threshold = $key->family->thresholdOf($key->tier);

        return match ($key->family) {
            BadgeFamilyEnum::SENIORITY => $this->seniorityRibbon($threshold),
            BadgeFamilyEnum::TONNAGE => $this->tonnageRibbon($threshold, $viewer->unitOfMeasure),
            default => $this->translator->trans('badge.ribbon.' . $key->family->value, [
                'count' => $threshold,
            ], 'navigation'),
        };
    }

    public function progress(BadgeFamilyEnum $family, BadgeTierEnum $tier, BadgeProgress $progress, User $viewer): string
    {
        $current = $progress->valueFor($family);
        $target = (float) $family->thresholdOf($tier);
        $suffix = $family->value;

        if (BadgeFamilyEnum::TONNAGE === $family && UnitOfMeasureEnum::LBS === $viewer->unitOfMeasure) {
            $current = $this->tonnesToLbs($current);
            $target = $this->tonnesToLbs($target);
            $suffix = 'tonnage_lbs';
        }

        return $this->translator->trans('badge.progress.' . $suffix, [
            'current' => (int) floor($current),
            'target' => (int) round($target),
        ], 'navigation');
    }

    private function seniorityName(int $months): string
    {
        return self::MONTHS_PER_YEAR > $months
            ? $this->translator->trans('badge.name.seniority_months', [
                'count' => $months,
            ], 'navigation')
            : $this->translator->trans('badge.name.seniority_years', [
                'count' => intdiv($months, self::MONTHS_PER_YEAR),
            ], 'navigation');
    }

    private function seniorityRibbon(int $months): string
    {
        return self::MONTHS_PER_YEAR > $months
            ? $this->translator->trans('badge.ribbon.seniority_months', [
                'count' => $months,
            ], 'navigation')
            : $this->translator->trans('badge.ribbon.seniority_years', [
                'count' => intdiv($months, self::MONTHS_PER_YEAR),
            ], 'navigation');
    }

    private function tonnageName(int $tonnes, UnitOfMeasureEnum $unit): string
    {
        return UnitOfMeasureEnum::LBS === $unit
            ? $this->translator->trans('badge.name.tonnage_lbs', [
                'count' => (int) round($this->tonnesToLbs($tonnes)),
            ], 'navigation')
            : $this->translator->trans('badge.name.tonnage', [
                'count' => $tonnes,
            ], 'navigation');
    }

    private function tonnageRibbon(int $tonnes, UnitOfMeasureEnum $unit): string
    {
        return UnitOfMeasureEnum::LBS === $unit
            ? $this->translator->trans('badge.ribbon.tonnage_lbs', [
                'value' => $this->compact($this->tonnesToLbs($tonnes)),
            ], 'navigation')
            : $this->translator->trans('badge.ribbon.tonnage', [
                'count' => $tonnes,
            ], 'navigation');
    }

    /**
     * "22K", "1,1M" — le bandeau n'a la place que pour quelques caractères.
     */
    private function compact(float $value): string
    {
        $formatter = new \NumberFormatter($this->translator->getLocale(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 1);

        return self::MILLION <= $value
            ? $formatter->format($value / self::MILLION) . 'M'
            : $formatter->format(round($value / self::THOUSAND)) . 'K';
    }

    private function tonnesToLbs(float $tonnes): float
    {
        return $tonnes * self::KG_PER_TONNE * UnitOfMeasureEnum::WEIGHT_IN_LBS;
    }
}
