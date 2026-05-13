<?php

declare(strict_types=1);

namespace App\Controller;

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
    #[Route('/workout/check-date', name: 'workout_check_date', methods: ['GET'])]
    public function __invoke(Request $request, WorkoutRepository $workoutRepository, TranslatorInterface $translator): JsonResponse
    {
        $date = $request->query->get('date');

        if (! $date) {
            return $this->json([
                'exists' => false,
                'count' => 0,
            ]);
        }

        $user = $this->getUser();
        assert($user instanceof \App\Entity\User);

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
