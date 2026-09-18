<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeExpiredAccountDeletionsMessage;
use App\Service\Entity\AccountDeletionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeExpiredAccountDeletionsMessageHandler
{
    public function __construct(
        private AccountDeletionService $accountDeletionService,
    ) {
    }

    public function __invoke(PurgeExpiredAccountDeletionsMessage $message): void
    {
        $this->accountDeletionService->purgeExpired();
    }
}
