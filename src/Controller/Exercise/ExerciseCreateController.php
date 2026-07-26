<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\User;
use App\Form\ExerciseType;
use App\Repository\MuscleGroupRepository;
use App\Security\Voter\ExerciseVoter;
use App\Service\Entity\ExerciseCreateService;
use App\Service\Entity\ExerciseMuscleValidationService;
use App\Service\Entity\MuscleGroupSorterService;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/bibliotheque/exercice/creer',
    'en' => '/library/exercise/create',
    'it' => '/biblioteca/esercizio/crea',
    'es' => '/biblioteca/ejercicio/crear',
    'pt' => '/biblioteca/exercicio/criar',
    'de' => '/bibliothek/uebung/erstellen',
    'nl' => '/bibliotheek/oefening/aanmaken',
    'pl' => '/biblioteka/cwiczenie/stworz',
], name: 'app_exercise_create', methods: ['GET', 'POST'])]
final class ExerciseCreateController extends AbstractController
{
    public function __construct(
        private readonly ExerciseCreateService $exerciseCreateService,
        private readonly ExerciseMuscleValidationService $exerciseMuscleValidationService,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly MuscleGroupSorterService $muscleGroupSorter,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ExerciseVoter::CREATE);

        $exercise = new Exercise();
        $form = $this->createForm(ExerciseType::class, $exercise);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->hasPrimaryMuscle($form)) {
            return $this->handleValidForm($form, $exercise);
        }

        return $this->renderCreateForm($form, $request->getLocale());
    }

    /**
     * @param FormInterface<Exercise> $form
     */
    private function handleValidForm(FormInterface $form, Exercise $exercise): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var Collection<int, ExerciseMuscle> $muscles */
        $muscles = $form->get('muscles')->getData();

        $this->exerciseCreateService->create($exercise, $user, $muscles);

        $this->addFlash('success', $this->translator->trans('exercise.flash.created', [], 'navigation'));

        return $this->redirectToRoute('app_exercise_list');
    }

    /**
     * @param FormInterface<Exercise> $form
     */
    private function renderCreateForm(FormInterface $form, string $locale): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('exercise/create/index.html.twig', [
            'form' => $form,
            'muscleGroups' => $this->muscleGroupSorter->sortByName(
                $this->muscleGroupRepository->findAllOrderedByPosition(),
                $locale,
            ),
            'gender' => $user->gender,
        ]);
    }

    /**
     * @param FormInterface<Exercise> $form
     */
    private function hasPrimaryMuscle(FormInterface $form): bool
    {
        /** @var Collection<int, ExerciseMuscle>|null $muscles */
        $muscles = $form->get('muscles')->getData();

        if (null === $muscles) {
            return false;
        }

        return $this->exerciseMuscleValidationService->hasPrimaryMuscle($muscles);
    }
}
