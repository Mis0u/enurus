<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Attribute\AsTwigFilter;

final class FormatSetDurationExtension
{
    private const int SECONDS_IN_ONE_MINUTE = 60;

    /**
     * Formate une durée de série (secondes) en `m:ss` — ex. 90 -> "1:30", 480 -> "8:00".
     */
    #[AsTwigFilter('format_set_duration')]
    public function format(?int $duration): string
    {
        $duration ??= 0;
        $minutes = intdiv($duration, self::SECONDS_IN_ONE_MINUTE);
        $seconds = $duration % self::SECONDS_IN_ONE_MINUTE;

        return \sprintf('%d:%02d', $minutes, $seconds);
    }
}
