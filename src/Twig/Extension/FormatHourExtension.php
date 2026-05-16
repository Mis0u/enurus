<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Attribute\AsTwigFilter;

class FormatHourExtension
{
    private const int MINUTES_IN_ONE_HOUR = 60;

    #[AsTwigFilter('format_hour')]
    public function format(int $duration): string
    {
        if (self::MINUTES_IN_ONE_HOUR > $duration) {
            return \sprintf('%dmin', $duration);
        }

        $hours = intdiv($duration, self::MINUTES_IN_ONE_HOUR);
        $minutes = $duration % self::MINUTES_IN_ONE_HOUR;

        if (0 === $minutes) {
            return \sprintf('%dh', $hours);
        }

        return \sprintf('%dh%02d', $hours, $minutes);
    }
}
