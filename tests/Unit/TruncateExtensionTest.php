<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\Extension\TruncateExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TruncateExtensionTest extends TestCase
{
    private TruncateExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new TruncateExtension();
    }

    /**
     * @return array<int, list<mixed>>
     */
    public static function textCases(): array
    {
        return [
            ['Short text', 160, 'Short text'],
            [str_repeat('a', 160), 160, str_repeat('a', 160)],
            [str_repeat('a', 161), 160, str_repeat('a', 160) . '…'],
            ['', 160, ''],
            ['Étienne Noé', 5, 'Étien…'],
            ['😀😀😀😀😀', 3, '😀😀😀…'],
        ];
    }

    #[DataProvider('textCases')]
    public function testTruncate(string $text, int $length, string $expected): void
    {
        $this->assertSame($expected, $this->extension->truncate($text, $length));
    }
}
