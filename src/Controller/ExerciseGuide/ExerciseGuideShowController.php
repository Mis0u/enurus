<?php

declare(strict_types=1);

namespace App\Controller\ExerciseGuide;

use App\Enum\Translations\LocaleAllowedEnum;
use App\Service\ExerciseGuide\ExerciseGuidePageBuilder;
use App\Service\ExerciseGuide\ExerciseGuideSlugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Fiche publique d'un exercice du catalogue. Le slug est celui du nom traduit dans la langue de
 * l'adresse : un slug d'une autre langue mène à une 404, chaque langue a sa propre adresse.
 */
final class ExerciseGuideShowController extends AbstractController
{
    public const string ROUTE = 'app_exercise_guide_show';

    public function __construct(
        private readonly ExerciseGuideSlugger $slugger,
        private readonly ExerciseGuidePageBuilder $pageBuilder,
    ) {
    }

    #[Route(path: [
        'en' => '/exercises/{slug}',
        'fr' => '/exercices/{slug}',
        'it' => '/esercizi/{slug}',
        'es' => '/ejercicios/{slug}',
        'pt' => '/exercicios/{slug}',
        'de' => '/uebungen/{slug}',
        'nl' => '/oefeningen/{slug}',
        'pl' => '/cwiczenia/{slug}',
    ], name: self::ROUTE, requirements: [
        'slug' => '[a-z0-9-]+',
    ], methods: [Request::METHOD_GET])]
    public function __invoke(Request $request, string $slug): Response
    {
        $guide = $this->slugger->findBySlug($slug, $request->getLocale());
        $page = null === $guide ? null : $this->pageBuilder->build($guide, $request->getLocale());
        if (null === $page) {
            throw $this->createNotFoundException(\sprintf('No exercise guide for slug "%s".', $slug));
        }

        return $this->render('exercise_guide/show.html.twig', [
            'page' => $page,
            'locales' => LocaleAllowedEnum::cases(),
            'currentLocale' => LocaleAllowedEnum::from($request->getLocale()),
            'isLoggedIn' => null !== $this->getUser(),
        ]);
    }
}
