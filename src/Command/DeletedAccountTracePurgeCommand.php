<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Entity\AccountDeletionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:deleted-account-trace:purge',
    description: 'Supprime les traces de compte (hash email) dont le délai de rétention de 30 jours est écoulé.',
)]
final class DeletedAccountTracePurgeCommand extends Command
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->accountDeletionService->purgeExpiredTraces();

        $io->success(sprintf('%d trace(s) de compte supprimée(s) définitivement.', $count));

        return Command::SUCCESS;
    }
}
