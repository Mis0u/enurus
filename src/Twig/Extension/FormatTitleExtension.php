<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;

readonly class FormatTitleExtension
{
    private const int SEPARATOR_MAX_LENGTH = 3;

    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    #[AsTwigFilter('format_title')]
    public function format(string $word, string $separator = '|'): string
    {
        $wordTrimmed = \trim($word);
        if ('' === $wordTrimmed) {
            throw new \InvalidArgumentException('Word must not be empty.');
        }
        $validSeparator = $this->handleSeparator($separator);
        $brand = $this->translator->trans('name', [], 'brand');
        $wordLower = \mb_strtolower($wordTrimmed);
        $wordCapitalize = \mb_ucfirst($wordLower);

        return \sprintf('%s %s %s', $wordCapitalize, $validSeparator, $brand);
    }

    private function handleSeparator(string $separator): string
    {
        if ('' === $separator) {
            throw new \InvalidArgumentException('Separator must not be empty.');
        }

        if (self::SEPARATOR_MAX_LENGTH < mb_strlen($separator)) {
            throw new \InvalidArgumentException(\sprintf('Separator must not exceed %d letters.', self::SEPARATOR_MAX_LENGTH));
        }

        return $separator;
    }
}
