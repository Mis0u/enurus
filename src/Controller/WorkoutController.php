<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Workout;
use App\Form\WorkoutType;
use App\Service\Utils\WeightConverterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class WorkoutController extends AbstractController
{
    public function __construct(
        private readonly WeightConverterService $weightConverterService,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

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
    public function __invoke(
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(WorkoutType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Workout $workout */
            $workout = $form->getData();
            return $this->handleValidForm($workout, $user, $request);
        }

        if ($form->isSubmitted() && ! $form->isValid()) {
            return $this->handleInvalidForm($request);
        }

        return $this->render('workout/create/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    private function handleValidForm(
        Workout $workout,
        User $user,
        Request $request,
    ): Response {
        $workout->owner = $user;
        $this->weightConverterService->convertWorkoutSetsToKg($workout, $user->unitOfMeasure);

        $this->em->persist($workout);
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->jsonSuccessResponse($workout, $request);
        }

        $this->addFlash('success', $this->translator->trans('workout.created', [], 'navigation'));

        return $this->redirectToRoute('app_dashboard');
    }

    private function handleInvalidForm(Request $request): Response
    {
        $errorMessage = $this->translator->trans('workout.error.validation', [], 'navigation');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'error' => $errorMessage,
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->addFlash('error', $errorMessage);

        return $this->redirectToRoute('app_workout', [
            '_locale' => $request->getLocale(),
        ]);
    }

    private function jsonSuccessResponse(Workout $workout, Request $request): JsonResponse
    {
        if (null === $workout->id) {
            throw new \LogicException('Workout id cannot be null after persist.');
        }

        return new JsonResponse([
            'id' => $workout->id->toRfc4122(),
            'redirectUrl' => $this->generateUrl('app_workout_show', [
                '_locale' => $request->getLocale(),
                'id' => $workout->id->toRfc4122(),
            ]),
        ]);
    }
}
