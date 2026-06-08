<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Workout;
use App\Security\Voter\WorkoutVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WorkoutDeleteController extends AbstractController
{
    #[Route(
        path: '/workout/{id}/delete',
        name: 'app_workout_delete',
        methods: [Request::METHOD_DELETE],
    )]
    public function __invoke(
        #[MapEntity(mapping: [
            'id' => 'id',
        ])]
        Workout $workout,
        EntityManagerInterface $em,
        Request $request,
    ): JsonResponse {
        // Vérifie que c'est bien une requête AJAX
        if (! $request->isXmlHttpRequest()) {
            return $this->json(
                [
                    'error' => 'Invalid request',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }
        $this->denyAccessUnlessGranted(WorkoutVoter::DELETE, $workout);

        $em->remove($workout);
        $em->flush();

        return $this->json([
            'success' => true,
        ], Response::HTTP_OK);
    }
}
