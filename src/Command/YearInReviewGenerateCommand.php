<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\YearInReview\YearInReviewCalendar;
use App\Service\YearInReview\YearInReviewGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Pendant manuel et synchrone de la tâche planifiée du 16 décembre (`GenerateYearInReviewsMessage`) :
 * rattrapage en prod, ou prévisualisation en dev avec `YEAR_IN_REVIEW_FAKE_NOW`. Ne recalcule
 * jamais un résumé déjà figé.
 */
#[AsCommand(
    name: 'app:year-in-review:generate',
    description: 'Fige les résumés annuels « Ton année » manquants (année publiée uniquement).',
)]
final class YearInReviewGenerateCommand extends Command
{
    public function __construct(
        private readonly YearInReviewCalendar $calendar,
        private readonly YearInReviewGenerator $generator,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('year', InputArgument::OPTIONAL, 'Année à figer (par défaut : la dernière publiée)')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Email du seul utilisateur à traiter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $year = $this->requestedYear($input);

        if (null === $year || ! $this->calendar->isPublished($year)) {
            $io->error('Cette année n\'est pas encore publiée (le 16 décembre à minuit, heure de Paris).');

            return Command::FAILURE;
        }

        $email = $input->getOption('user');

        return \is_string($email) ? $this->generateForOne($io, $email, $year) : $this->generateForEveryone($io, $year);
    }

    private function requestedYear(InputInterface $input): ?int
    {
        $year = $input->getArgument('year');

        return is_numeric($year) ? (int) $year : $this->calendar->latestPublishedYear();
    }

    private function generateForOne(SymfonyStyle $io, string $email, int $year): int
    {
        $user = $this->userRepository->findOneByEmail($email);

        if (! $user instanceof User) {
            $io->error(\sprintf('Aucun utilisateur avec l\'email %s.', $email));

            return Command::FAILURE;
        }

        $review = $this->generator->generate($user, $year);

        if (null === $review) {
            $io->note(\sprintf('Le résumé %d de %s existe déjà : il reste figé.', $year, $email));
        } else {
            $io->success(\sprintf('Résumé %d de %s figé (%d séance(s), %s).', $year, $email, $review->workoutCount, $review->isEligible() ? 'éligible' : 'non éligible'));
        }

        return Command::SUCCESS;
    }

    private function generateForEveryone(SymfonyStyle $io, int $year): int
    {
        $generatedCount = 0;

        foreach ($this->userRepository->findIdsWithoutYearInReview($year) as $userId) {
            $user = $this->userRepository->find(Uuid::fromString($userId));
            $generatedCount += $user instanceof User && null !== $this->generator->generate($user, $year) ? 1 : 0;
        }

        $io->success(\sprintf('%d résumé(s) %d figé(s).', $generatedCount, $year));

        return Command::SUCCESS;
    }
}
