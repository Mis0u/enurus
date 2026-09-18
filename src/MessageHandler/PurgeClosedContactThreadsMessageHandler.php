<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeClosedContactThreadsMessage;
use App\Service\Contact\ContactThreadPurgeService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeClosedContactThreadsMessageHandler
{
    public function __construct(
        private ContactThreadPurgeService $contactThreadPurgeService,
    ) {
    }

    public function __invoke(PurgeClosedContactThreadsMessage $message): void
    {
        $this->contactThreadPurgeService->purgeClosed();
    }
}
