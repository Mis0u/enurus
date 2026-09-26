<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Entity\User;
use App\Service\Workout\Draft\InvalidWorkoutDraftException;
use App\Service\Workout\Draft\WorkoutDraftCardsRenderer;
use App\Service\Workout\Draft\WorkoutDraftPayloadParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cartes d'exercice du brouillon de séance tenu par le navigateur (`workout--draft` controller),
 * à sa restauration. Lecture seule : rien n'est enregistré, d'où l'absence de jeton CSRF.
 */
#[IsGranted('ROLE_USER')]
final class WorkoutDraftExercisesBlockController extends AbstractController
{
    public function __construct(
        private readonly WorkoutDraftPayloadParser $payloadParser,
        private readonly WorkoutDraftCardsRenderer $cardsRenderer,
    ) {
    }

    #[Route(path: [
        'fr' => '/enregistre-seance/bloc-exercices-brouillon',
        'en' => '/log-workout/draft-exercises-block',
        'it' => '/registra-allenamento/blocco-esercizi-bozza',
        'es' => '/registrar-entrenamiento/bloque-ejercicios-borrador',
        'pt' => '/registar-treino/bloco-exercicios-rascunho',
        'de' => '/training-erfassen/entwurf-uebungen-block',
        'nl' => '/training-vastleggen/concept-oefeningen-blok',
        'pl' => '/zapisz-trening/blok-cwiczen-szkicu',
    ], name: 'workout_draft_exercises_block', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $request->isXmlHttpRequest()) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        try {
            $draftExercises = $this->payloadParser->parse($request->getContent());
        } catch (InvalidWorkoutDraftException) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'htmls' => $this->cardsRenderer->render($user, $draftExercises),
        ]);
    }
}
