<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Entity\Exercise;
use App\Entity\User;
use App\Form\ExerciseType;
use App\Repository\MuscleGroupRepository;
use App\Security\Voter\ExerciseVoter;
use App\Service\Entity\ExerciseEditService;
use App\Service\Entity\ExerciseMuscleValidationService;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: [
    'fr' => '/bibliotheque/exercice/{id}/modifier',
    'en' => '/library/exercise/{id}/edit',
    'it' => '/biblioteca/esercizio/{id}/modifica',
    'es' => '/biblioteca/ejercicio/{id}/editar',
    'pt' => '/biblioteca/exercicio/{id}/editar',
    'de' => '/bibliothek/uebung/{id}/bearbeiten',
    'nl' => '/bibliotheek/oefening/{id}/bewerken',
    'pl' => '/biblioteka/cwiczenie/{id}/edytuj',
], name: 'app_exercise_edit', methods: ['GET', 'POST'])]
final class ExerciseEditController extends AbstractController
{
    public function __construct(
        private readonly ExerciseEditService $exerciseEditService,
        private readonly ExerciseMuscleValidationService $exerciseMuscleValidationService,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function __invoke(Request $request, Exercise $exercise): Response
    {
        $this->denyAccessUnlessGranted(ExerciseVoter::EDIT, $exercise);

        $form = $this->createForm(ExerciseType::class, $exercise);
        $form->get('muscles')->setData($exercise->exerciseMuscles);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleValidForm($form, $exercise);
        }

        return $this->renderEditForm($form, $exercise);
    }

    /**
     * @param FormInterface<Exercise> $form
     */
    private function handleValidForm(FormInterface $form, Exercise $exercise): Response
    {
        $muscles = $form->get('muscles')->getData();

        if (! $muscles instanceof Collection) {
            throw new \LogicException('Expected muscles to be a Collection.');
        }

        if (! $this->exerciseMuscleValidationService->hasPrimaryMuscle($muscles)) {
            return $this->renderEditForm($form, $exercise);
        }

        $this->exerciseEditService->edit($exercise, $muscles);

        $this->addFlash('success', $this->translator->trans('exercise.flash.updated', [], 'navigation'));

        return $this->redirectToRoute('app_exercise_history');
    }

    /**
     * @param FormInterface<Exercise> $form
     */
    private function renderEditForm(FormInterface $form, Exercise $exercise): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('exercise/edit/index.html.twig', [
            'form' => $form,
            'exercise' => $exercise,
            'gender' => $user->gender ?? 'male',
            'muscleGroups' => $this->muscleGroupRepository->findAllOrderedByPosition(),
            'user' => $user,
        ]);
    }
}
