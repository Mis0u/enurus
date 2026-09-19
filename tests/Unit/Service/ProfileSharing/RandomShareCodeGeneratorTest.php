<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Service\ProfileSharing\RandomShareCodeGenerator;
use PHPUnit\Framework\TestCase;

final class RandomShareCodeGeneratorTest extends TestCase
{
    private const int SAMPLE_SIZE = 500;

    public function testGeneratedCodeHasExpectedLength(): void
    {
        $code = new RandomShareCodeGenerator()->generate();

        self::assertSame(RandomShareCodeGenerator::LENGTH, \strlen($code));
    }

    public function testGeneratedCodeOnlyContainsAllowedCharacters(): void
    {
        $generator = new RandomShareCodeGenerator();
        $allowedPattern = '/^[' . RandomShareCodeGenerator::ALPHABET . ']+$/';

        for ($i = 0; self::SAMPLE_SIZE > $i; ++$i) {
            self::assertMatchesRegularExpression($allowedPattern, $generator->generate());
        }
    }

    public function testAlphabetExcludesCharactersEasilyMistakenForOthers(): void
    {
        foreach (['0', 'O', '1', 'I', 'L'] as $ambiguousCharacter) {
            self::assertStringNotContainsString($ambiguousCharacter, RandomShareCodeGenerator::ALPHABET);
        }
    }

    public function testGeneratedCodesVary(): void
    {
        $generator = new RandomShareCodeGenerator();
        $codes = [];

        for ($i = 0; self::SAMPLE_SIZE > $i; ++$i) {
            $codes[$generator->generate()] = true;
        }

        self::assertGreaterThan(1, \count($codes));
    }
}
