<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route(
        path: [
            'en' => '/dashboard',
            'fr' => '/tableau-de-bord',
            'it' => '/cruscotto',
            'es' => '/panel',
            'pt' => '/painel',
            'de' => '/uebersicht',
            'nl' => '/overzicht',
            'pl' => '/panel',
        ],
        name: 'app_dashboard'
    )]
    #[IsGranted('ROLE_USER')]
    public function index(WorkoutRepository $workoutRepository): Response
    {
        $user = $this->getUser();
        //PHPSTAN
        assert($user instanceof User);
        /*return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
        ]);*/
        return $this->render('dashboard/dashboard-empty-responsive.html.twig', [
            'controller_name' => 'DashboardController',
            'user' => $this->getUser(),
            'workouts' => $workoutRepository->countByUser($user),
        ]);
    }
}
