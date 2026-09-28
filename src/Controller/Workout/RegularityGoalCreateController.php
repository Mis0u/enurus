<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Controller\Trait\FlashesFirstFormErrorTrait;
use App\Entity\RegularityGoal;
use App\Entity\User;
use App\Form\RegularityGoalType;
use App\Service\RegularityGoal\RegularityGoalCreateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-seances/objectif-regularite/creer',
    'en' => '/my-workouts/regularity-goal/create',
    'it' => '/le-mie-sedute/obiettivo-regolarita/crea',
    'es' => '/mis-entrenamientos/objetivo-regularidad/crear',
    'pt' => '/os-meus-treinos/objetivo-regularidade/criar',
    'de' => '/meine-trainings/regelmaessigkeitsziel/erstellen',
    'nl' => '/mijn-trainingen/regelmaatdoel/aanmaken',
    'pl' => '/moje-treningi/cel-regularnosci/utworz',
], name: 'app_regularity_goal_create', methods: ['POST'])]
final class RegularityGoalCreateController extends AbstractController
{
    use FlashesFirstFormErrorTrait;

    public function __construct(
        private readonly RegularityGoalCreateService $createService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $goal = new RegularityGoal();
        $goal->owner = $user;

        $form = $this->createForm(RegularityGoalType::class, $goal);
        $form->handleRequest($request);

        match (true) {
            ! $form->isSubmitted() || ! $form->isValid() => $this->addFlash('error', $this->firstFormErrorMessage($form, $this->translator->trans('regularity_goal.flash.error', [], 'navigation'))),
            ! $this->createService->create($goal) => $this->addFlash('error', $this->translator->trans('regularity_goal.flash.already_ongoing', [], 'navigation')),
            default => $this->addFlash('success', $this->translator->trans('regularity_goal.flash.created', [], 'navigation')),
        };

        return $this->redirectToRoute('app_workout_list', [
            'view' => 'calendar',
        ]);
    }
}
