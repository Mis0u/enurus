<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Entity\User;
use App\Entity\Workout;
use App\Security\Voter\WorkoutVoter;
use App\Service\Workout\WorkoutShowDataService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WorkoutShowController extends AbstractController
{
    #[Route(path: [
        'fr' => '/seance/{id}',
        'en' => '/workout/{id}',
        'it' => '/allenamento/{id}',
        'es' => '/entrenamiento/{id}',
        'pt' => '/treino/{id}',
        'de' => '/training/{id}',
        'nl' => '/training/{id}',
        'pl' => '/trening/{id}',
    ], name: 'app_workout_show')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[MapEntity(id: 'id')]
        Workout $workout,
        WorkoutShowDataService $workoutShowDataService,
    ): Response {
        $this->denyAccessUnlessGranted(WorkoutVoter::VIEW, $workout);

        /** @var User $user */
        $user = $this->getUser();

        $data = $workoutShowDataService->build($workout, $user);

        return $this->render('workout/show/show.html.twig', [
            'workout' => $workout,
            'exerciseData' => $data['exerciseData'],
            'totalTonnage' => $data['totalTonnage'],
            'totalSets' => $data['totalSets'],
            'totalReps' => $data['totalReps'],
            'unit' => $user->unitOfMeasure,
            'allPrimarySvgIds' => $data['allPrimarySvgIds'],
            'allSecondarySvgIds' => $data['allSecondarySvgIds'],
            'user' => $user,
        ]);
    }
}
