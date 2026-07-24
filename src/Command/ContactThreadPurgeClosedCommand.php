<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Contact\ContactThreadPurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:contact-thread:purge-closed',
    description: 'Supprime définitivement les fils de discussion 1 to 1 clôturés depuis au moins 3 mois.',
)]
final class ContactThreadPurgeClosedCommand extends Command
{
    public function __construct(
        private readonly ContactThreadPurgeService $contactThreadPurgeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->contactThreadPurgeService->purgeClosed();

        $io->success(sprintf('%d fil(s) de discussion supprimé(s) définitivement.', $count));

        return Command::SUCCESS;
    }
}
