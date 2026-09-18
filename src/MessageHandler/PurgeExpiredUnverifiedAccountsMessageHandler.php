<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeExpiredUnverifiedAccountsMessage;
use App\Service\Entity\UnverifiedAccountPurgeService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeExpiredUnverifiedAccountsMessageHandler
{
    public function __construct(
        private UnverifiedAccountPurgeService $unverifiedAccountPurgeService,
    ) {
    }

    public function __invoke(PurgeExpiredUnverifiedAccountsMessage $message): void
    {
        $this->unverifiedAccountPurgeService->purgeExpired();
    }
}
