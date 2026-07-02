<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Exercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Service\Entity\MuscleSorterService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('MuscleTags')]
final class MuscleTagsComponent
{
    public Exercise $exercise;

    public function __construct(
        private readonly MuscleSorterService $muscleSorter,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array{label: string, type: MuscleTypeEnum}>
     */
    public function getSortedMuscles(): array
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? LocaleAllowedEnum::FR->value;

        return $this->muscleSorter->sortByTypeThenName($this->exercise, $locale);
    }
}
