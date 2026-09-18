<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Service\ProfileSharing\ShareCodeQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShareCodeQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function validQueries(): iterable
    {
        yield 'nickname and code' => ['Misou#A7K2XM', 'Misou', 'A7K2XM'];
        yield 'lowercase code is normalized' => ['Misou#a7k2xm', 'Misou', 'A7K2XM'];
        yield 'surrounding spaces are trimmed' => ['  Misou # A7K2XM ', 'Misou', 'A7K2XM'];
        yield 'nickname containing a hash' => ['Mi#sou#A7K2XM', 'Mi#sou', 'A7K2XM'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'empty' => [''];
        yield 'nickname only' => ['Misou'];
        yield 'missing nickname' => ['#A7K2XM'];
        yield 'missing code' => ['Misou#'];
        yield 'code too short' => ['Misou#A7K2X'];
        yield 'code too long' => ['Misou#A7K2XMM'];
        yield 'code with a forbidden character' => ['Misou#A7K2X0'];
    }

    #[DataProvider('validQueries')]
    public function testParsesNicknameAndShareCode(string $input, string $nickname, string $shareCode): void
    {
        $query = ShareCodeQuery::tryFromString($input);

        self::assertInstanceOf(ShareCodeQuery::class, $query);
        self::assertSame($nickname, $query->nickname);
        self::assertSame($shareCode, $query->shareCode);
    }

    #[DataProvider('invalidQueries')]
    public function testRejectsMalformedQueries(string $input): void
    {
        self::assertNull(ShareCodeQuery::tryFromString($input));
    }
}
