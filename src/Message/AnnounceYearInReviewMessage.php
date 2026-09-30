<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Un email d'annonce à envoyer, un message par résumé : l'échec de l'un ne bloque pas les autres.
 */
final readonly class AnnounceYearInReviewMessage
{
    public function __construct(
        public string $yearInReviewId,
    ) {
    }
}
