<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Entity\Workout;
use App\Security\Voter\ProfileConnectionVoter;
use App\Service\Badge\BadgeViewBuilder;
use App\Service\ProfileSharing\ProfileConnectionVisitRecorder;
use App\Service\Workout\WorkoutShowDataService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Détail d'une séance d'une connexion, en lecture seule. `{id}` reste celui de la
 * `ProfileConnection` (jamais un identifiant utilisateur exposé) ; `{workoutId}` doit en plus
 * appartenir au sujet de cette connexion, sans quoi la page n'existe pas pour ce viewer.
 */
#[Route(path: [
    'fr' => '/connexions/{id}/seances/{workoutId}',
    'en' => '/connections/{id}/workouts/{workoutId}',
    'it' => '/connessioni/{id}/allenamenti/{workoutId}',
    'es' => '/conexiones/{id}/entrenamientos/{workoutId}',
    'pt' => '/conexoes/{id}/treinos/{workoutId}',
    'de' => '/verbindungen/{id}/trainings/{workoutId}',
    'nl' => '/verbindingen/{id}/trainingen/{workoutId}',
    'pl' => '/polaczenia/{id}/treningi/{workoutId}',
], name: 'app_profile_connection_workout_show', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionWorkoutShowController extends AbstractController
{
    public function __construct(
        private readonly WorkoutShowDataService $workoutShowDataService,
        private readonly BadgeViewBuilder $badgeViewBuilder,
        private readonly ProfileConnectionVisitRecorder $visitRecorder,
    ) {
    }

    #[IsGranted(ProfileConnectionVoter::VIEW_WORKOUTS, subject: 'connection')]
    public function __invoke(
        ProfileConnection $connection,
        #[MapEntity(id: 'workoutId')]
        Workout $workout,
    ): Response {
        $viewer = $this->getUser();

        if (! $viewer instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $subject = $connection->counterpartOf($viewer);

        if ($workout->owner !== $subject) {
            throw $this->createNotFoundException();
        }

        $this->visitRecorder->record($connection, $viewer);

        $data = $this->workoutShowDataService->build($workout, $subject, $viewer);

        return $this->render('profile_connection/workout/show.html.twig', [
            'connection' => $connection,
            'subject' => $subject,
            'workout' => $workout,
            'exerciseData' => $data['exerciseData'],
            'totalTonnage' => $data['totalTonnage'],
            'totalSets' => $data['totalSets'],
            'totalReps' => $data['totalReps'],
            'unit' => $viewer->unitOfMeasure,
            'allPrimarySvgIds' => $data['allPrimarySvgIds'],
            'allSecondarySvgIds' => $data['allSecondarySvgIds'],
            'unlockedBadges' => $this->isGranted(ProfileConnectionVoter::VIEW_BADGES, $connection)
                ? $this->badgeViewBuilder->buildForWorkout($workout, $viewer)
                : [],
        ]);
    }
}
