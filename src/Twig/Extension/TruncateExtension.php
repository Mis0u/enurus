<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Attribute\AsTwigFilter;

final class TruncateExtension
{
    private const string ELLIPSIS = '…';

    #[AsTwigFilter('truncate')]
    public function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . self::ELLIPSIS;
    }
}
