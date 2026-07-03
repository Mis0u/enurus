<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Security\Voter\ExerciseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/bibliotheque/exercice/{id}/supprimer',
    'en' => '/library/exercise/{id}/delete',
    'it' => '/biblioteca/esercizio/{id}/elimina',
    'es' => '/biblioteca/ejercicio/{id}/eliminar',
    'pt' => '/biblioteca/exercicio/{id}/eliminar',
    'de' => '/bibliothek/uebung/{id}/loeschen',
    'nl' => '/bibliotheek/oefening/{id}/verwijderen',
    'pl' => '/biblioteka/cwiczenie/{id}/usun',
], name: 'app_exercise_delete', methods: ['DELETE'])]
final class ExerciseDeleteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, Exercise $exercise): JsonResponse
    {
        if (! $request->isXmlHttpRequest()) {
            return $this->json([
                'error' => 'XHR only',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(ExerciseVoter::DELETE, $exercise);

        $this->em->remove($exercise);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('exercise.flash.deleted', [], 'navigation'),
        ]);
    }
}
