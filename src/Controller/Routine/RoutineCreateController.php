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
use App\Service\Entity\RoutineCreateService;
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
    'fr' => '/mes-routines/creer',
    'en' => '/my-routines/create',
    'it' => '/le-mie-routine/crea',
    'es' => '/mis-rutinas/crear',
    'pt' => '/as-minhas-rotinas/criar',
    'de' => '/meine-routinen/erstellen',
    'nl' => '/mijn-routines/aanmaken',
    'pl' => '/moje-plany/utworz',
], name: 'app_routine_create', methods: ['GET', 'POST'])]
final class RoutineCreateController extends AbstractController
{
    public function __construct(
        private readonly RoutineCreateService $routineCreateService,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly ExerciseRepository $exerciseRepository,
        private readonly TranslatorInterface $translator,
        private readonly RoutineRepository $routineRepository,
        private readonly ExerciseSorterService $exerciseSorter,
        private readonly MuscleGroupSorterService $muscleGroupSorter,
        private readonly ExercisePrimaryMuscleIdsResolver $primaryMuscleIdsResolver,
        private readonly RoutineExerciseAccessService $routineExerciseAccessService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted(RoutineVoter::CREATE);

        $routine = new Routine();
        $form = $this->createForm(RoutineType::class, $routine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleValidForm($form, $routine);
        }

        return $this->renderCreateForm($form, $request->getLocale());
    }

    /**
     * @param FormInterface<Routine> $form
     */
    private function handleValidForm(FormInterface $form, Routine $routine): RedirectResponse
    {
        $exercises = $form->get('exercises')->getData();

        if (! $exercises instanceof Collection || $exercises->isEmpty()) {
            $this->addFlash('error', $this->translator->trans('routine.error.no_exercise', [], 'navigation'));
            return $this->redirectToRoute('app_routine_create');
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
            return $this->redirectToRoute('app_routine_create');
        }

        /** @var Collection<int, RoutineExercise> $exercises */
        $this->routineCreateService->create($routine, $user, $exercises);

        $this->addFlash('success', $this->translator->trans(
            'routine.flash.created',
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
    private function renderCreateForm(FormInterface $form, string $locale): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $exercises = $this->exerciseRepository->findAvailableForUser($user);

        $sortedExercises = $this->exerciseSorter->sortByName($exercises, $locale);
        $sortedMuscleGroups = $this->muscleGroupSorter->sortByName(
            $this->muscleGroupRepository->findAllOrderedByPosition(),
            $locale,
        );

        return $this->render('routine/create/index.html.twig', [
            'form' => $form,
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
