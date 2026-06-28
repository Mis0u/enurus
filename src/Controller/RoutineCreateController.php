<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Form\RoutineType;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Security\Voter\RoutineVoter;
use App\Service\Entity\RoutineCreateService;
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
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $exercises = $this->exerciseRepository->findAvailableForUser($user);

        $collator = \Collator::create($locale);

        usort($exercises, function (Exercise $a, Exercise $b) use ($collator): int {
            $nameA = $a->isPublic ? $this->translator->trans($a->name, [], 'exercise') : $a->name;
            $nameB = $b->isPublic ? $this->translator->trans($b->name, [], 'exercise') : $b->name;

            return (int) $collator->compare($nameA, $nameB);
        });

        return $this->render('routine/create/index.html.twig', [
            'form' => $form,
            'muscleGroups' => $this->muscleGroupRepository->findAllOrderedByPosition(),
            'exercises' => $exercises,
            'cancelUrl' => $this->generateUrl('app_routine_list'),
        ]);
    }
}
