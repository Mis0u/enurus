<?php

declare(strict_types=1);

namespace App\Controller\Help;

use App\Enum\Help\HelpSectionEnum;
use App\Repository\FeatureSettingRepository;
use App\Service\YearInReview\YearInReviewNavigationState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mode d'emploi : une section repliable par fonctionnalité (HelpSectionEnum), texte dans le domaine
 * de traduction `help`. Lien permanent dans la sidebar (desktop) et le panneau « Plus » (mobile).
 */
#[Route(path: [
    'fr' => '/aide',
    'en' => '/help',
    'it' => '/aiuto',
    'es' => '/ayuda',
    'pt' => '/ajuda',
    'de' => '/hilfe',
    'nl' => '/hulp',
    'pl' => '/pomoc',
], name: 'app_help', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class HelpController extends AbstractController
{
    public function __construct(
        private readonly FeatureSettingRepository $featureSettingRepository,
        private readonly YearInReviewNavigationState $yearInReviewNavigationState,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('help/index.html.twig', [
            'sections' => $this->visibleSections(),
            'pointParams' => $this->pointParams(),
        ]);
    }

    /**
     * Le résumé annuel n'est décrit qu'une fois publié : l'Aide ne doit pas l'annoncer avant son
     * lien et sa page.
     *
     * @return list<HelpSectionEnum>
     */
    private function visibleSections(): array
    {
        if ($this->yearInReviewNavigationState->isVisible()) {
            return HelpSectionEnum::cases();
        }

        return array_values(array_filter(
            HelpSectionEnum::cases(),
            static fn (HelpSectionEnum $section): bool => HelpSectionEnum::YEAR_IN_REVIEW !== $section,
        ));
    }

    /**
     * Paramètres ICU des points : une fonctionnalité coupée depuis l'admin (FeatureSetting)
     * disparaît aussi du mode d'emploi (`select` dans la traduction du point concerné).
     *
     * @return array{photo: 'enabled'|'disabled'}
     */
    private function pointParams(): array
    {
        return [
            'photo' => $this->featureSettingRepository->isWorkoutPhotoUploadEnabled() ? 'enabled' : 'disabled',
        ];
    }
}
