<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workout;

use App\Service\Workout\WorkoutShowDataService;
use PHPUnit\Framework\TestCase;

/**
 * `WorkoutShowDataService::build()` prend `WorkoutExerciseRepository` en dépendance, une classe
 * `final` qui ne peut pas être stubbée (cf. CLAUDE.md) — la couverture bout-en-bout de la card
 * "aperçu séance" est donc fonctionnelle (`WorkoutShowControllerTest`). Ce test unitaire isole la
 * seule logique nouvelle, `sumMetric()`, via Reflection sur une instance sans dépendances
 * construites (méthode privée pure, n'utilise aucune des propriétés injectées).
 */
final class WorkoutShowDataServiceTest extends TestCase
{
    public function testSumsDurationAcrossSetsIgnoringNullValues(): void
    {
        $sets = [
            [
                'duration' => 90,
                'distance' => null,
            ],
            [
                'duration' => 45,
                'distance' => null,
            ],
        ];

        self::assertSame(135, $this->sumMetric($sets, 'duration'));
    }

    public function testSumsDistanceAcrossSetsIgnoringNullValues(): void
    {
        $sets = [
            [
                'duration' => null,
                'distance' => 20,
            ],
            [
                'duration' => null,
                'distance' => 30,
            ],
        ];

        self::assertSame(50, $this->sumMetric($sets, 'distance'));
    }

    public function testReturnsNullWhenNoSetCarriesTheMetric(): void
    {
        $sets = [
            [
                'duration' => null,
                'distance' => null,
            ],
        ];

        self::assertNull($this->sumMetric($sets, 'duration'));
        self::assertNull($this->sumMetric($sets, 'distance'));
    }

    /**
     * @param array<int, array{duration: ?int, distance: ?int}> $sets
     */
    private function sumMetric(array $sets, string $key): ?int
    {
        $service = (new \ReflectionClass(WorkoutShowDataService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(WorkoutShowDataService::class, 'sumMetric');

        /** @var ?int $result */
        $result = $method->invoke($service, $sets, $key);

        return $result;
    }
}
