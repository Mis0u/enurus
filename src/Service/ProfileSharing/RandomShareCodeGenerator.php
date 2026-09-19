<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\User;

/**
 * Alphabet sans 0/O ni 1/I/L : le code se recopie à la main (ou se dicte) et ces caractères se
 * confondent à l'écriture.
 */
final class RandomShareCodeGenerator implements ShareCodeGeneratorInterface
{
    public const int LENGTH = User::SHARE_CODE_LENGTH;

    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        $lastIndex = \strlen(self::ALPHABET) - 1;
        $code = '';

        for ($position = 0; self::LENGTH > $position; ++$position) {
            $code .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $code;
    }
}
