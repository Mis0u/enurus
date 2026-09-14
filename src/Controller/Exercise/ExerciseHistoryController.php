<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Form\ExerciseGoalType;
use App\Repository\ExerciseGoalRepository;
use App\Security\Voter\ExerciseVoter;
use App\Service\Exercise\ExerciseHistoryDataService;
use App\Service\Goal\GoalCardFormatter;
use App\Service\Goal\GoalProgress;
use App\Service\Goal\GoalProgressResolver;
use App\Service\Goal\GoalStateResolver;
use App\Service\Utils\WeightConverterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
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

    public function __invoke(
        Request $request,
        Exercise $exercise,
        ExerciseHistoryDataService $exerciseHistoryDataService,
        ExerciseGoalRepository $exerciseGoalRepository,
        GoalProgressResolver $goalProgressResolver,
        GoalStateResolver $goalStateResolver,
        GoalCardFormatter $goalCardFormatter,
        WeightConverterService $weightConverterService,
    ): Response {
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

        $existingGoals = $exerciseGoalRepository->findAllByOwnerAndExercise($user, $exercise);
        $goal = $goalStateResolver->findActiveGoal($user, $existingGoals);
        $goalCard = null !== $goal ? $goalCardFormatter->format($goalProgressResolver->resolve($user, $goal), $user) : null;
        $goalForm = $this->createForm(ExerciseGoalType::class, $goal ?? ExerciseGoal::draftFor($user, $exercise), [
            'exercise' => $exercise,
        ]);
        $this->displayGoalTargetInUserUnit($goalForm, $goal, $user, $weightConverterService);

        $goalHistoryCards = array_map(
            fn (GoalProgress $progress) => $goalCardFormatter->format($progress, $user),
            $goalStateResolver->resolveState($user, $existingGoals)->achieved,
        );

        return $this->render('exercise/history/index.html.twig', [
            'exercise' => $exercise,
            'gender' => $user->gender,
            'unit' => $user->unitOfMeasure,
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
            'goalCard' => $goalCard,
            'goalForm' => $goalForm,
            'goalFieldName' => self::goalFieldName($exercise->measurementType),
            'goalHistoryCards' => $goalHistoryCards,
            ...$data,
        ]);
    }

    /**
     * `ExerciseGoal::targetWeight` est stocké en kg (règle du projet), que ce soit la cible
     * principale (`WEIGHT_REPS`) ou la charge additionnelle optionnelle (`TIME`/`DISTANCE`) ; le
     * champ du formulaire doit afficher la valeur dans l'unité de l'utilisateur, sans jamais
     * réécrire l'entité persistée (le formulaire n'est ici jamais soumis, seulement affiché).
     *
     * @param FormInterface<ExerciseGoal> $goalForm
     */
    private function displayGoalTargetInUserUnit(
        FormInterface $goalForm,
        ?ExerciseGoal $goal,
        User $user,
        WeightConverterService $weightConverterService,
    ): void {
        if (null === $goal || null === $goal->targetWeight) {
            return;
        }

        $goalForm->get('targetWeight')->setData(
            $weightConverterService->convertToLbs($goal->targetWeight, $user->unitOfMeasure),
        );
    }

    private static function goalFieldName(MeasurementType $measurementType): string
    {
        return match ($measurementType) {
            MeasurementType::WEIGHT_REPS => 'targetWeight',
            MeasurementType::TIME => 'targetDuration',
            MeasurementType::DISTANCE => 'targetDistance',
        };
    }
}
