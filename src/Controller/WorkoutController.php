<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\WorkoutType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(WorkoutType::class);
        $form->handleRequest($request);

        return $this->render('workout/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
