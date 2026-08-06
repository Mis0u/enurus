<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Doctrine\DBAL\Connection;

/**
 * Lit directement `messenger_messages` en DBAL brut, jamais via l'ORM — cette table est gérée en
 * interne par le transport Doctrine de Messenger (cf. `DoctrineSessionHandler` pour le même
 * raisonnement sur `sessions`), pas une entité applicative. `async` (emails + notifications
 * Telegram, cf. CLAUDE.md TODO #24) et `failed` partagent la même table, distingués par
 * `queue_name` (`'default'` par défaut faute d'option `queue_name` explicite dans le DSN `async`,
 * cf. `Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection`).
 */
final readonly class MessengerQueueStatsService
{
    private const string QUEUE_ASYNC = 'default';

    private const string QUEUE_FAILED = 'failed';

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array{pending: int, failed: int}
     */
    public function getData(): array
    {
        return [
            'pending' => $this->countByQueue(self::QUEUE_ASYNC),
            'failed' => $this->countByQueue(self::QUEUE_FAILED),
        ];
    }

    private function countByQueue(string $queueName): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queueName',
            [
                'queueName' => $queueName,
            ],
        );

        return is_numeric($count) ? (int) $count : 0;
    }
}
