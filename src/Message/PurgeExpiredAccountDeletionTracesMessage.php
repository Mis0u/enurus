<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Déclenche AccountDeletionService::purgeExpiredTraces() — dispatché quotidiennement par
 * App\Scheduler\MaintenanceSchedule, jamais manuellement.
 */
final readonly class PurgeExpiredAccountDeletionTracesMessage
{
}
