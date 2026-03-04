<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Attribute\AsTwigFilter;

class InitialExtension
{
    #[AsTwigFilter('formatInitials')]
    public function initials(string $userNickname): string
    {
        if ('' === $userNickname) {
            return '';
        }

        $firstLetter = \mb_substr(trim($userNickname), 0, 1);
        $lastLetter = \mb_substr(trim($userNickname), -1, 1);

        return \mb_strtoupper(\sprintf('%s%s', $firstLetter, $lastLetter));
    }
}
