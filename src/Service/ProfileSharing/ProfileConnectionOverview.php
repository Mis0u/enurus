<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

final readonly class ProfileConnectionOverview
{
    public bool $isEmpty;

    /**
     * @param list<ProfileConnectionEntry> $receivedRequests demandes en attente de la réponse de l'utilisateur
     * @param list<ProfileConnectionEntry> $sentRequests     demandes envoyées par l'utilisateur, sans réponse
     * @param list<ProfileConnectionEntry> $connections      connexions acceptées, triées par pseudo
     */
    public function __construct(
        public array $receivedRequests,
        public array $sentRequests,
        public array $connections,
    ) {
        $this->isEmpty = [] === $receivedRequests && [] === $sentRequests && [] === $connections;
    }
}
