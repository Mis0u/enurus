<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\RegularityGoal;
use App\Security\Voter\RegularityGoalVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Abandonner un objectif le supprime : ce n'est pas un échec, il ne va pas dans l'historique.
 */
#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-seances/objectif-regularite/{id}/abandonner',
    'en' => '/my-workouts/regularity-goal/{id}/abandon',
    'it' => '/le-mie-sedute/obiettivo-regolarita/{id}/abbandona',
    'es' => '/mis-entrenamientos/objetivo-regularidad/{id}/abandonar',
    'pt' => '/os-meus-treinos/objetivo-regularidade/{id}/abandonar',
    'de' => '/meine-trainings/regelmaessigkeitsziel/{id}/aufgeben',
    'nl' => '/mijn-trainingen/regelmaatdoel/{id}/opgeven',
    'pl' => '/moje-treningi/cel-regularnosci/{id}/porzuc',
], name: 'app_regularity_goal_abandon', methods: ['DELETE'])]
final class RegularityGoalAbandonController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[IsGranted(RegularityGoalVoter::DELETE, subject: 'regularityGoal')]
    public function __invoke(Request $request, RegularityGoal $regularityGoal): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        $id = $regularityGoal->id ?? throw new \LogicException('Cannot abandon a regularity goal without a persisted id.');
        $this->denyUnlessValidCsrfToken($request, 'regularity_goal_abandon_' . $id->toRfc4122());

        $this->em->remove($regularityGoal);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('regularity_goal.flash.abandoned', [], 'navigation'),
        ]);
    }
}
