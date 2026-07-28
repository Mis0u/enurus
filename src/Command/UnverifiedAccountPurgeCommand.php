<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Entity\UnverifiedAccountPurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:unverified-account:purge',
    description: 'Supprime les comptes jamais confirmés par email (TODO #24) au-delà du délai de grâce de 7 jours.',
)]
final class UnverifiedAccountPurgeCommand extends Command
{
    public function __construct(
        private readonly UnverifiedAccountPurgeService $unverifiedAccountPurgeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->unverifiedAccountPurgeService->purgeExpired();

        $io->success(\sprintf('%d compte(s) non confirmé(s) supprimé(s) définitivement.', $count));

        return Command::SUCCESS;
    }
}
