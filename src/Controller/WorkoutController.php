<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Workout;
use App\Form\WorkoutType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkoutController extends AbstractController
{
    #[Route(path: [
        'fr' => '/enregistre-seance',
        'en' => '/log-workout',
        'it' => '/registra-allenamento',
        'es' => '/registrar-entrenamiento',
        'pt' => '/registar-treino',
        'de' => '/training-erfassen',
        'nl' => '/training-vastleggen',
        'pl' => '/zapisz-trening',
    ], name: 'app_workout')]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(WorkoutType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Workout $workout */
            $workout = $form->getData();
            $workout->owner = $user;
            $em->persist($workout);
            $em->flush();
            $this->addFlash('success', $translator->trans('workout.created', [], 'navigation'));
            return $this->redirectToRoute('app_dashboard');
        }

        if ($form->isSubmitted() && ! $form->isValid()) {
            $this->addFlash('error', $translator->trans('workout.error.validation', [], 'navigation'));
            return $this->redirectToRoute('app_workout');
        }

        return $this->render('workout/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
