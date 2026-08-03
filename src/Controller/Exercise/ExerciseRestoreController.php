<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\Exercise;
use App\Security\Voter\ExerciseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/bibliotheque/exercice/{id}/restaurer',
    'en' => '/library/exercise/{id}/restore',
    'it' => '/biblioteca/esercizio/{id}/ripristina',
    'es' => '/biblioteca/ejercicio/{id}/restaurar',
    'pt' => '/biblioteca/exercicio/{id}/restaurar',
    'de' => '/bibliothek/uebung/{id}/wiederherstellen',
    'nl' => '/bibliotheek/oefening/{id}/herstellen',
    'pl' => '/biblioteka/cwiczenie/{id}/przywroc',
], name: 'app_exercise_restore', methods: ['POST'])]
final class ExerciseRestoreController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, Exercise $exercise): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        $this->denyAccessUnlessGranted(ExerciseVoter::RESTORE, $exercise);

        if (null === $exercise->id) {
            throw new \LogicException('Cannot restore an exercise without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'exercise_restore_' . $exercise->id->toRfc4122());

        $exercise->archived = false;
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('exercise.flash.restored', [], 'navigation'),
        ]);
    }
}
