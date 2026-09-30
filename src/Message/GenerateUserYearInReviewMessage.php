<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Un résumé annuel à figer, un message par utilisateur : l'échec de l'un (retry Messenger) ne
 * bloque jamais les autres.
 */
final readonly class GenerateUserYearInReviewMessage
{
    public function __construct(
        public string $userId,
        public int $year,
    ) {
    }
}
