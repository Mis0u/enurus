<?php

declare(strict_types=1);

namespace App\Service\OneRepMax;

use App\Service\ExerciseGuide\ExerciseGuideCatalog;
use App\Service\ExerciseGuide\ExerciseGuideSlugger;
use App\Service\ExerciseGuide\View\ExerciseGuideLink;

/**
 * Fiches des grands mouvements proposées sous le calculateur de 1RM : ceux pour lesquels on
 * estime le plus souvent son 1RM.
 */
final readonly class OneRepMaxGuideLinks
{
    private const array GUIDE_KEYS = ['bench_press_bar', 'barbell_squat', 'deadlift', 'military_press_bar'];

    public function __construct(
        private ExerciseGuideCatalog $catalog,
        private ExerciseGuideSlugger $slugger,
    ) {
    }

    /**
     * @return list<ExerciseGuideLink>
     */
    public function build(string $locale): array
    {
        $links = [];
        foreach (self::GUIDE_KEYS as $guideKey) {
            $guide = $this->catalog->find($guideKey);
            if (null !== $guide) {
                $links[] = new ExerciseGuideLink($guide->exerciseName(), $this->slugger->slugFor($guide, $locale));
            }
        }

        return $links;
    }
}
