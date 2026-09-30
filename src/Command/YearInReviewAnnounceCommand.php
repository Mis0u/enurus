<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\YearInReview;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\YearInReviewAnnouncer;
use App\Service\YearInReview\YearInReviewCalendar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Pendant manuel et synchrone de la tâche planifiée de 7h (`AnnounceYearInReviewsMessage`) :
 * rattrapage en prod, ou aperçu de l'email en dev avec `YEAR_IN_REVIEW_FAKE_NOW`. N'envoie que les
 * emails encore en attente, jamais deux fois le même.
 */
#[AsCommand(
    name: 'app:year-in-review:announce',
    description: 'Envoie les emails d\'annonce du résumé annuel encore en attente (à partir du 16 décembre à 7h).',
)]
final class YearInReviewAnnounceCommand extends Command
{
    public function __construct(
        private readonly YearInReviewCalendar $calendar,
        private readonly YearInReviewRepository $yearInReviewRepository,
        private readonly YearInReviewAnnouncer $announcer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('year', InputArgument::OPTIONAL, 'Année à annoncer (par défaut : la dernière publiée)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $requestedYear = $input->getArgument('year');
        $year = is_numeric($requestedYear) ? (int) $requestedYear : $this->calendar->latestPublishedYear();

        if (null === $year || ! $this->calendar->isAnnounced($year)) {
            $io->error('Les emails de cette année ne partent pas encore (le 16 décembre à 7h, heure de Paris).');

            return Command::FAILURE;
        }

        $sentCount = 0;

        foreach ($this->yearInReviewRepository->findIdsAwaitingAnnouncement($year) as $yearInReviewId) {
            $review = $this->yearInReviewRepository->find(Uuid::fromString($yearInReviewId));
            $sentCount += $review instanceof YearInReview && $this->announceAndTell($review) ? 1 : 0;
        }

        $io->success(\sprintf('%d email(s) d\'annonce %d envoyé(s).', $sentCount, $year));

        return Command::SUCCESS;
    }

    private function announceAndTell(YearInReview $review): bool
    {
        $this->announcer->announce($review);

        return null !== $review->emailedAt;
    }
}
