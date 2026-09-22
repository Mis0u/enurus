<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Entity\DeloadPeriod;
use App\Entity\User;
use App\Form\DeloadPeriodType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-seances/deload/creer',
    'en' => '/my-workouts/deload/create',
    'it' => '/le-mie-sedute/deload/crea',
    'es' => '/mis-entrenamientos/deload/crear',
    'pt' => '/os-meus-treinos/deload/criar',
    'de' => '/meine-trainings/deload/erstellen',
    'nl' => '/mijn-trainingen/deload/aanmaken',
    'pl' => '/moje-treningi/deload/utworz',
], name: 'app_deload_period_create', methods: ['POST'])]
final class DeloadPeriodCreateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $deloadPeriod = new DeloadPeriod();
        $deloadPeriod->owner = $user;

        $form = $this->createForm(DeloadPeriodType::class, $deloadPeriod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($deloadPeriod);
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans('workout.list.calendar.flash.created', [], 'navigation'));
        } else {
            $this->addFlash('error', $this->translator->trans('workout.list.calendar.flash.error', [], 'navigation'));
        }

        return $this->redirectToRoute('app_workout_list', [
            'view' => 'calendar',
            '_locale' => $request->getLocale(),
        ]);
    }
}
