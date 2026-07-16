<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\ExerciseRepository;
use App\Service\Entity\ExerciseSorterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ExerciseListController extends AbstractController
{
    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly ExerciseSorterService $exerciseSorter,
    ) {
    }

    #[Route(path: [
        'fr' => '/bibliotheque',
        'en' => '/library',
        'it' => '/biblioteca',
        'es' => '/biblioteca',
        'pt' => '/biblioteca',
        'de' => '/bibliothek',
        'nl' => '/bibliotheek',
        'pl' => '/biblioteka',
    ], name: 'app_exercise_list', methods: ['GET'])]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $exercises = $this->exerciseRepository->findAvailableForUser($user);
        $sorted = $this->exerciseSorter->sortByName($exercises, $user->locale ?? LocaleAllowedEnum::EN->value);

        return $this->render('exercise/list/index.html.twig', [
            'exercises' => $sorted,
            'totalCount' => count($sorted),
        ]);
    }
}
