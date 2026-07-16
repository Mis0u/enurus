<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
class WorkoutCheckDateController extends AbstractController
{
    #[Route(path: [
        'fr' => '/enregistre-seance/verifier-date',
        'en' => '/log-workout/check-date',
        'it' => '/registra-allenamento/verifica-data',
        'es' => '/registrar-entrenamiento/verificar-fecha',
        'pt' => '/registar-treino/verificar-data',
        'de' => '/training-erfassen/datum-pruefen',
        'nl' => '/training-vastleggen/datum-controleren',
        'pl' => '/zapisz-trening/sprawdz-date',
    ], name: 'workout_check_date', methods: ['GET'])]
    public function __invoke(Request $request, WorkoutRepository $workoutRepository, TranslatorInterface $translator): JsonResponse
    {
        $date = $request->query->get('date');

        if (! $date) {
            return $this->json([
                'exists' => false,
                'count' => 0,
            ]);
        }
        /** @var User $user */
        $user = $this->getUser();

        $start = new \DateTimeImmutable($date . ' 00:00:00');
        $end = new \DateTimeImmutable($date . ' 23:59:59');

        $count = $workoutRepository->countByUserAndDate($user, $start, $end);

        return $this->json([
            'exists' => 0 < $count,
            'count' => $count,
            'message' => $translator->trans('workout.check_date.message', [
                'count' => $count,
            ], 'navigation'),
        ]);
    }
}
