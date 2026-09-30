<?php

declare(strict_types=1);

namespace App\Controller\YearInReview;

use App\Entity\User;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\Screen\YearInReviewScreensBuilder;
use App\Service\YearInReview\YearInReviewCalendar;
use App\Service\YearInReview\YearInReviewSeenMarker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Parcours en écrans d'un résumé annuel. Le résumé est cherché par propriétaire et par année
 * (jamais par un id d'URL) : impossible d'ouvrir celui d'un autre. 404 avant publication, sans
 * résumé ou sous le seuil de séances (ce dernier n'a que son message sur « Mes résumés »).
 */
#[Route(path: [
    'fr' => '/mes-resumes/{year}',
    'en' => '/my-recaps/{year}',
    'it' => '/i-miei-riepiloghi/{year}',
    'es' => '/mis-resumenes/{year}',
    'pt' => '/meus-resumos/{year}',
    'de' => '/meine-rueckblicke/{year}',
    'nl' => '/mijn-overzichten/{year}',
    'pl' => '/moje-podsumowania/{year}',
], name: 'app_year_in_review_show', requirements: [
    'year' => '\d{4}',
], methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class YearInReviewShowController extends AbstractController
{
    public function __construct(
        private readonly YearInReviewCalendar $calendar,
        private readonly YearInReviewRepository $yearInReviewRepository,
        private readonly YearInReviewScreensBuilder $screensBuilder,
        private readonly YearInReviewSeenMarker $seenMarker,
    ) {
    }

    public function __invoke(int $year): Response
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $review = $this->calendar->isPublished($year) ? $this->yearInReviewRepository->findEligibleByOwnerAndYear($user, $year) : null;

        if (null === $review) {
            throw $this->createNotFoundException();
        }

        $this->seenMarker->markSeen($review);

        return $this->render('year_in_review/show/index.html.twig', [
            'year' => $year,
            'screens' => $this->screensBuilder->build($review, $user),
        ]);
    }
}
