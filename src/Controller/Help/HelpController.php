<?php

declare(strict_types=1);

namespace App\Controller\Help;

use App\Enum\Help\HelpSectionEnum;
use App\Repository\FeatureSettingRepository;
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
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('help/index.html.twig', [
            'sections' => HelpSectionEnum::cases(),
            'pointParams' => $this->pointParams(),
        ]);
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
