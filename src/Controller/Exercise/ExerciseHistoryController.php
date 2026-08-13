<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Entity\Exercise;
use App\Entity\User;
use App\Security\Voter\ExerciseVoter;
use App\Service\Exercise\ExerciseHistoryDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: [
    'fr' => '/bibliotheque/exercice/{id}/historique',
    'en' => '/library/exercise/{id}/history',
    'it' => '/biblioteca/esercizio/{id}/cronologia',
    'es' => '/biblioteca/ejercicio/{id}/historial',
    'pt' => '/biblioteca/exercicio/{id}/historico',
    'de' => '/bibliothek/uebung/{id}/verlauf',
    'nl' => '/bibliotheek/oefening/{id}/geschiedenis',
    'pl' => '/biblioteka/cwiczenie/{id}/historia',
], name: 'app_exercise_history', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ExerciseHistoryController extends AbstractController
{
    private const int DISPLAY_LIMIT_BY_DEFAULT = 10;

    private const array DISPLAY_LIMIT_ALLOWED = [10, 25, 50];

    public function __invoke(Request $request, Exercise $exercise, ExerciseHistoryDataService $exerciseHistoryDataService): Response
    {
        $this->denyAccessUnlessGranted(ExerciseVoter::VIEW, $exercise);

        /** @var User $user */
        $user = $this->getUser();

        $limit = $request->query->getInt('limit', self::DISPLAY_LIMIT_BY_DEFAULT);
        $limit = in_array($limit, self::DISPLAY_LIMIT_ALLOWED, true) ? $limit : self::DISPLAY_LIMIT_BY_DEFAULT;

        $data = $exerciseHistoryDataService->getData(
            $user,
            $exercise,
            $request->query->getString('period', ExerciseHistoryDataService::PERIOD_ALL),
            $request->query->getInt('page', 1),
            $limit,
        );

        return $this->render('exercise/history/index.html.twig', [
            'exercise' => $exercise,
            'gender' => $user->gender,
            'unit' => $user->unitOfMeasure,
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
            ...$data,
        ]);
    }
}
