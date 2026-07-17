<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\Workout;
use App\Security\Voter\WorkoutVoter;
use App\Service\Entity\WorkoutPhotoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class WorkoutDeleteController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly WorkoutPhotoService $workoutPhotoService,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: [
            'fr' => '/seance/{id}/supprimer',
            'en' => '/workout/{id}/delete',
            'it' => '/allenamento/{id}/elimina',
            'es' => '/entrenamiento/{id}/eliminar',
            'pt' => '/treino/{id}/eliminar',
            'de' => '/training/{id}/loeschen',
            'nl' => '/training/{id}/verwijderen',
            'pl' => '/trening/{id}/usun',
        ],
        name: 'app_workout_delete',
        methods: [Request::METHOD_DELETE],
    )]
    public function __invoke(
        #[MapEntity(mapping: [
            'id' => 'id',
        ])]
        Workout $workout,
        Request $request,
    ): JsonResponse {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        $this->denyAccessUnlessGranted(WorkoutVoter::DELETE, $workout);

        if (null === $workout->id) {
            throw new \LogicException('Cannot delete a workout without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'workout_delete_' . $workout->id->toRfc4122());

        // Supprime la photo du storage avant de supprimer l'entité —
        // si le flush échoue, la photo est conservée (pas de perte de données)
        $this->workoutPhotoService->deletePhoto($workout);

        $this->em->remove($workout);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('workout.flash.deleted', [], 'navigation'),
        ], Response::HTTP_OK);
    }
}
