<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Form\ExerciseGoalType;
use App\Repository\ExerciseGoalRepository;
use App\Security\Voter\ExerciseGoalVoter;
use App\Security\Voter\ExerciseVoter;
use App\Service\Goal\GoalCardFormatter;
use App\Service\Goal\GoalProgressResolver;
use App\Service\Goal\GoalStateResolver;
use App\Service\Utils\WeightConverterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Toujours au plus un objectif ACTIF par (user, exercise) à la fois, mais un historique
 * d'objectifs atteints s'accumule en base : créer et modifier restent la même opération
 * d'upsert (édite l'objectif actif s'il existe, sinon en crée un nouveau) — pas de controller
 * "edit" séparé, pour ne pas dupliquer la même logique de sauvegarde.
 */
#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/bibliotheque/exercice/{id}/objectif',
    'en' => '/library/exercise/{id}/goal',
    'it' => '/biblioteca/esercizio/{id}/obiettivo',
    'es' => '/biblioteca/ejercicio/{id}/objetivo',
    'pt' => '/biblioteca/exercicio/{id}/objetivo',
    'de' => '/bibliothek/uebung/{id}/ziel',
    'nl' => '/bibliotheek/oefening/{id}/doel',
    'pl' => '/biblioteka/cwiczenie/{id}/cel',
], name: 'app_exercise_goal_save', methods: ['POST'])]
final class ExerciseGoalSaveController extends AbstractController
{
    public function __construct(
        private readonly ExerciseGoalRepository $exerciseGoalRepository,
        private readonly GoalStateResolver $goalStateResolver,
        private readonly GoalProgressResolver $goalProgressResolver,
        private readonly GoalCardFormatter $goalCardFormatter,
        private readonly WeightConverterService $weightConverterService,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, Exercise $exercise): Response
    {
        $this->denyAccessUnlessGranted(ExerciseVoter::VIEW, $exercise);

        /** @var User $user */
        $user = $this->getUser();

        if (null !== $exercise->bodyweightPercent && null === $user->bodyweightKg) {
            $this->addFlash('error', $this->translator->trans('exercise.goal.flash.bodyweight_required', [], 'navigation'));

            return $this->redirectToRoute('app_exercise_history', [
                'id' => $exercise->id,
                '_locale' => $request->getLocale(),
            ]);
        }

        $existingGoals = $this->exerciseGoalRepository->findAllByOwnerAndExercise($user, $exercise);
        $goal = $this->goalStateResolver->findActiveGoal($user, $existingGoals) ?? ExerciseGoal::draftFor($user, $exercise);

        $this->denyAccessUnlessGranted(ExerciseGoalVoter::EDIT, $goal);

        $form = $this->createForm(ExerciseGoalType::class, $goal, [
            'exercise' => $exercise,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->convertTargetToKg($goal, $user);
            $achievedAt = $this->alreadyAchievedAt($goal, $user);

            if (null !== $achievedAt) {
                $this->notifyAlreadyAchieved($goal, $user, $achievedAt);
            } else {
                $this->em->persist($goal);
                $this->em->flush();

                $this->addFlash('success', $this->translator->trans('exercise.goal.flash.saved', [], 'navigation'));
            }
        } else {
            $this->addFlash('error', $this->translator->trans('exercise.goal.flash.error', [], 'navigation'));
        }

        return $this->redirectToRoute('app_exercise_history', [
            'id' => $exercise->id,
            '_locale' => $request->getLocale(),
        ]);
    }

    /**
     * Objectif fixé sous une performance déjà réalisée (ex. cible plus basse que le record actuel) :
     * ne doit jamais être enregistré (créerait une entrée d'historique dupliquée à chaque
     * soumission identique) — l'utilisateur est simplement informé de la date à laquelle il a
     * déjà atteint cette cible, voir `notifyAlreadyAchieved()`.
     */
    private function alreadyAchievedAt(ExerciseGoal $goal, User $user): ?\DateTimeImmutable
    {
        $progress = $this->goalProgressResolver->resolve($user, $goal);

        return $progress->achieved ? $progress->achievedAt : null;
    }

    private function notifyAlreadyAchieved(ExerciseGoal $goal, User $user, \DateTimeImmutable $achievedAt): void
    {
        $this->addFlash('goal_already_achieved', [
            'exerciseName' => $this->goalCardFormatter->exerciseName($goal->exercise),
            'targetLabel' => $this->goalCardFormatter->formatTarget($goal, $user),
            'achievedAt' => $achievedAt,
        ]);
    }

    /**
     * `targetWeight` est toujours un poids dans l'unité de l'utilisateur quand il est renseigné,
     * que ce soit la cible principale (`WEIGHT_REPS`) ou la charge additionnelle optionnelle
     * (`TIME`/`DISTANCE`) — conversion identique dans les deux cas.
     */
    private function convertTargetToKg(ExerciseGoal $goal, User $user): void
    {
        if (null === $goal->targetWeight) {
            return;
        }

        $goal->targetWeight = $this->weightConverterService->convertToKg($goal->targetWeight, $user->unitOfMeasure);
    }
}
