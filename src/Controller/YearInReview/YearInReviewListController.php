<?php

declare(strict_types=1);

namespace App\Controller\YearInReview;

use App\Entity\User;
use App\Service\YearInReview\TonnageEquivalenceScaleBuilder;
use App\Service\YearInReview\YearInReviewListViewBuilder;
use App\Service\YearInReview\YearInReviewNavigationState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Mes résumés » : une carte par année publiée (résumé, encouragement ou génération en cours),
 * l'interrupteur de l'email d'annonce et, pour l'admin seulement, l'échelle des équivalences.
 * Inexistante (404) avant la première publication, même pour qui devine l'URL.
 */
#[Route(path: [
    'fr' => '/mes-resumes',
    'en' => '/my-recaps',
    'it' => '/i-miei-riepiloghi',
    'es' => '/mis-resumenes',
    'pt' => '/meus-resumos',
    'de' => '/meine-rueckblicke',
    'nl' => '/mijn-overzichten',
    'pl' => '/moje-podsumowania',
], name: 'app_year_in_review_list', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class YearInReviewListController extends AbstractController
{
    public function __construct(
        private readonly YearInReviewNavigationState $navigationState,
        private readonly YearInReviewListViewBuilder $listViewBuilder,
        private readonly TonnageEquivalenceScaleBuilder $equivalenceScaleBuilder,
    ) {
    }

    public function __invoke(): Response
    {
        if (! $this->navigationState->isVisible()) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        return $this->render('year_in_review/list/index.html.twig', [
            'cards' => $this->listViewBuilder->cards($user),
            'emailOnYearInReview' => $user->emailOnYearInReview,
            'equivalenceScale' => $this->isGranted('ROLE_ADMIN') ? $this->equivalenceScaleBuilder->rows($user->unitOfMeasure) : [],
        ]);
    }
}
