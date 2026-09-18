<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeExpiredAccountDeletionTracesMessage;
use App\Service\Entity\AccountDeletionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeExpiredAccountDeletionTracesMessageHandler
{
    public function __construct(
        private AccountDeletionService $accountDeletionService,
    ) {
    }

    public function __invoke(PurgeExpiredAccountDeletionTracesMessage $message): void
    {
        $this->accountDeletionService->purgeExpiredTraces();
    }
}
