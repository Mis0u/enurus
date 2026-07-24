<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Déclenche la création des fils individuels d'une diffusion admin déjà persistée
 * (ContactThreadComposeService::composeToAudience()) — traité de façon asynchrone
 * (SendContactBroadcastMessageHandler) pour ne pas bloquer la requête admin sur potentiellement
 * des centaines de créations de fil + copies d'image.
 */
final readonly class SendContactBroadcastMessage
{
    public function __construct(
        public string $broadcastId,
        public ?string $sourceImagePath,
    ) {
    }
}
