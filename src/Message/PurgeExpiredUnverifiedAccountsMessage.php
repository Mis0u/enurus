<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Déclenche UnverifiedAccountPurgeService::purgeExpired() — dispatché quotidiennement par
 * App\Scheduler\MaintenanceSchedule, jamais manuellement.
 */
final readonly class PurgeExpiredUnverifiedAccountsMessage
{
}
