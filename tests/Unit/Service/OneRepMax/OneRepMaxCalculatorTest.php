<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OneRepMax;

use App\Service\OneRepMax\OneRepMaxCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mêmes cas que la formule du navigateur (tests/js/utils/one_rep_max.test.js) : le calculateur
 * public et l'historique d'un exercice donnent toujours le même 1RM.
 */
final class OneRepMaxCalculatorTest extends TestCase
{
    private const string SHARED_CASES = __DIR__ . '/../../../shared/one_rep_max_cases.json';

    /**
     * @return iterable<string, array{float, int, float}>
     */
    public static function estimateProvider(): iterable
    {
        foreach (self::sharedCases('estimates') as $case) {
            $oneRepMax = $case['oneRepMax'] ?? throw new \LogicException('Each estimate case needs its one-rep max.');

            yield \sprintf('%s × %s', $case['weight'], $case['reps']) => [(float) $case['weight'], (int) $case['reps'], (float) $oneRepMax];
        }
    }

    /**
     * Le navigateur refuse aussi des répétitions non entières : sans objet ici, le type l'interdit.
     *
     * @return iterable<string, array{float, int}>
     */
    public static function rejectedProvider(): iterable
    {
        foreach (self::sharedCases('rejected') as $case) {
            if (\is_int($case['reps'])) {
                yield \sprintf('%s × %s', $case['weight'], $case['reps']) => [(float) $case['weight'], $case['reps']];
            }
        }
    }

    #[DataProvider('estimateProvider')]
    public function testEstimatesTheOneRepMaxWithEpley(float $weight, int $reps, float $expected): void
    {
        self::assertEqualsWithDelta($expected, (new OneRepMaxCalculator())->estimate($weight, $reps), 0.01);
    }

    #[DataProvider('rejectedProvider')]
    public function testGivesNoEstimateOutsideTheReliableRange(float $weight, int $reps): void
    {
        self::assertNull((new OneRepMaxCalculator())->estimate($weight, $reps));
    }

    /**
     * @return list<array{weight: int|float, reps: int|float, oneRepMax?: int|float}>
     */
    private static function sharedCases(string $group): array
    {
        $cases = json_decode((string) file_get_contents(self::SHARED_CASES), true, flags: \JSON_THROW_ON_ERROR);
        if (! \is_array($cases) || ! \is_array($cases[$group] ?? null)) {
            throw new \LogicException(\sprintf('Missing "%s" in the shared one-rep max cases.', $group));
        }

        /** @var list<array{weight: int|float, reps: int|float, oneRepMax?: int|float}> */
        return $cases[$group];
    }
}
