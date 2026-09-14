<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\ExerciseGoal;
use App\Security\Voter\ExerciseGoalVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/bibliotheque/objectif/{id}/supprimer',
    'en' => '/library/goal/{id}/delete',
    'it' => '/biblioteca/obiettivo/{id}/elimina',
    'es' => '/biblioteca/objetivo/{id}/eliminar',
    'pt' => '/biblioteca/objetivo/{id}/eliminar',
    'de' => '/bibliothek/ziel/{id}/loeschen',
    'nl' => '/bibliotheek/doel/{id}/verwijderen',
    'pl' => '/biblioteka/cel/{id}/usun',
], name: 'app_exercise_goal_delete', methods: ['DELETE'])]
final class ExerciseGoalDeleteController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, ExerciseGoal $exerciseGoal): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        $this->denyAccessUnlessGranted(ExerciseGoalVoter::DELETE, $exerciseGoal);

        if (null === $exerciseGoal->id) {
            throw new \LogicException('Cannot delete a goal without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'exercise_goal_delete_' . $exerciseGoal->id->toRfc4122());

        $this->em->remove($exerciseGoal);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('exercise.goal.flash.deleted', [], 'navigation'),
        ]);
    }
}
