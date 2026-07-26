<?php

declare(strict_types=1);

namespace App\Controller\Routine;

use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Form\RoutineType;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Repository\RoutineRepository;
use App\Security\Voter\RoutineVoter;
use App\Service\Entity\ExercisePrimaryMuscleIdsResolver;
use App\Service\Entity\ExerciseSorterService;
use App\Service\Entity\MuscleGroupSorterService;
use App\Service\Entity\RoutineEditService;
use App\Service\Entity\RoutineExerciseAccessService;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-routines/{id}/modifier',
    'en' => '/my-routines/{id}/edit',
    'it' => '/le-mie-routine/{id}/modifica',
    'es' => '/mis-rutinas/{id}/editar',
    'pt' => '/as-minhas-rotinas/{id}/editar',
    'de' => '/meine-routinen/{id}/bearbeiten',
    'nl' => '/mijn-routines/{id}/bewerken',
    'pl' => '/moje-plany/{id}/edytuj',
], name: 'app_routine_edit', methods: ['GET', 'POST'])]
final class RoutineEditController extends AbstractController
{
    public function __construct(
        private readonly RoutineEditService $routineEditService,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly ExerciseRepository $exerciseRepository,
        private readonly ExerciseSorterService $exerciseSorterService,
        private readonly MuscleGroupSorterService $muscleGroupSorter,
        private readonly ExercisePrimaryMuscleIdsResolver $primaryMuscleIdsResolver,
        private readonly TranslatorInterface $translator,
        private readonly RoutineRepository $routineRepository,
        private readonly RoutineExerciseAccessService $routineExerciseAccessService,
    ) {
    }

    public function __invoke(Request $request, Routine $routine): Response
    {
        $this->denyAccessUnlessGranted(RoutineVoter::EDIT, $routine);

        $form = $this->createForm(RoutineType::class, $routine);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleValidForm($form, $routine);
        }

        return $this->renderEditForm($form, $routine, $request->getLocale());
    }

    /**
     * @param FormInterface<Routine> $form
     */
    private function handleValidForm(FormInterface $form, Routine $routine): RedirectResponse
    {
        $exercises = $form->get('exercises')->getData();

        if (! $exercises instanceof Collection || $exercises->isEmpty()) {
            $this->addFlash('error', $this->translator->trans('routine.error.no_exercise', [], 'navigation'));

            return $this->redirectToRoute('app_routine_edit', [
                'id' => $routine->id,
            ]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $nameAlreadyExists = $this->routineRepository->existsByNameForUser(
            $routine->name,
            $user,
            $routine->id ?? null,  // null en création, UUID en édition
        );

        if ($nameAlreadyExists) {
            throw new \LogicException('Routine name already exists for this user — should have been caught by frontend.');
        }

        if (! $this->routineExerciseAccessService->allAccessible($exercises, $user)) {
            $this->addFlash('error', $this->translator->trans('routine.error.forbidden_exercise', [], 'navigation'));

            return $this->redirectToRoute('app_routine_edit', [
                'id' => $routine->id,
            ]);
        }

        /** @var Collection<int, RoutineExercise> $exercises */
        $this->routineEditService->update($routine, $exercises);

        $this->addFlash('success', $this->translator->trans(
            'routine.flash.updated',
            [
                '{name}' => $routine->name,
            ],
            'navigation'
        ));

        return $this->redirectToRoute('app_routine_list');
    }

    /**
     * @param FormInterface<Routine> $form
     */
    private function renderEditForm(FormInterface $form, Routine $routine, string $locale): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $exercises = $this->exerciseRepository->findAvailableForUser($user);
        $sortedExercises = $this->exerciseSorterService->sortByName($exercises, $locale);
        $sortedMuscleGroups = $this->muscleGroupSorter->sortByName(
            $this->muscleGroupRepository->findAllOrderedByPosition(),
            $locale,
        );

        return $this->render('routine/edit/index.html.twig', [
            'form' => $form,
            'routine' => $routine,
            'muscleGroups' => $sortedMuscleGroups,
            'exercises' => $sortedExercises,
            'primaryMuscleIds' => $this->primaryMuscleIdsResolver->resolve($sortedExercises),
            'secondaryMuscleIds' => $this->primaryMuscleIdsResolver->resolveSecondary($sortedExercises),
            'primaryMuscleGroupIds' => $this->primaryMuscleIdsResolver->resolvePrimaryMuscleGroupIds($sortedExercises),
            'secondaryMuscleGroupIds' => $this->primaryMuscleIdsResolver->resolveSecondaryMuscleGroupIds($sortedExercises),
            'cancelUrl' => $this->generateUrl('app_routine_list'),
        ]);
    }
}
