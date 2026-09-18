<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

/**
 * Saisie `Pseudo#CODE`. Le pseudo n'étant pas unique, la coupure se fait sur le dernier `#` : un
 * pseudo qui en contient un reste utilisable.
 */
final readonly class ShareCodeQuery
{
    private const string SEPARATOR = '#';

    private const string CODE_PATTERN = '/^[' . RandomShareCodeGenerator::ALPHABET . ']{' . RandomShareCodeGenerator::LENGTH . '}$/';

    private function __construct(
        public string $nickname,
        public string $shareCode,
    ) {
    }

    public static function tryFromString(string $input): ?self
    {
        $separatorPosition = strrpos($input, self::SEPARATOR);

        if (false === $separatorPosition) {
            return null;
        }

        $nickname = trim(substr($input, 0, $separatorPosition));
        $shareCode = strtoupper(trim(substr($input, $separatorPosition + 1)));

        if ('' === $nickname || 1 !== preg_match(self::CODE_PATTERN, $shareCode)) {
            return null;
        }

        return new self($nickname, $shareCode);
    }
}
