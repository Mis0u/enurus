<?php

declare(strict_types=1);

namespace App\Controller\ExerciseGuide;

use App\Enum\Translations\LocaleAllowedEnum;
use App\Service\ExerciseGuide\ExerciseGuideIndexBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Index public des fiches exercices, rangées par muscle. Public comme l'accueil : ces pages font
 * découvrir le site depuis les moteurs de recherche.
 */
final class ExerciseGuideIndexController extends AbstractController
{
    public const string ROUTE = 'app_exercise_guide_index';

    public function __construct(
        private readonly ExerciseGuideIndexBuilder $indexBuilder,
    ) {
    }

    #[Route(path: [
        'en' => '/exercises',
        'fr' => '/exercices',
        'it' => '/esercizi',
        'es' => '/ejercicios',
        'pt' => '/exercicios',
        'de' => '/uebungen',
        'nl' => '/oefeningen',
        'pl' => '/cwiczenia',
    ], name: self::ROUTE, methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): Response
    {
        return $this->render('exercise_guide/index.html.twig', [
            'muscleGroups' => $this->indexBuilder->build($request->getLocale()),
            'locales' => LocaleAllowedEnum::cases(),
            'currentLocale' => LocaleAllowedEnum::from($request->getLocale()),
            'isLoggedIn' => null !== $this->getUser(),
        ]);
    }
}
