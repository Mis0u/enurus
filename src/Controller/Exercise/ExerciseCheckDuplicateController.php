<?php

declare(strict_types=1);

namespace App\Controller\Exercise;

use App\Entity\User;
use App\Service\Entity\ExerciseCheckDuplicateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/bibliotheque/exercice/verifier-doublon',
    'en' => '/library/exercise/check-duplicate',
    'it' => '/biblioteca/esercizio/verifica-duplicato',
    'es' => '/biblioteca/ejercicio/verificar-duplicado',
    'pt' => '/biblioteca/exercicio/verificar-duplicado',
    'de' => '/bibliothek/uebung/duplikat-pruefen',
    'nl' => '/bibliotheek/oefening/duplicaat-controleren',
    'pl' => '/biblioteka/cwiczenie/sprawdz-duplikat',
], name: 'app_exercise_check_duplicate', methods: ['GET'])]
final class ExerciseCheckDuplicateController extends AbstractController
{
    public function __construct(
        private readonly ExerciseCheckDuplicateService $checkDuplicateService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $name = trim((string) $request->query->get('name', ''));
        $locale = $request->getLocale();

        if ('' === $name) {
            return $this->json([
                'type' => null,
            ]);
        }

        $result = $this->checkDuplicateService->check($name, $user, $locale);

        return $this->json($result);
    }
}
