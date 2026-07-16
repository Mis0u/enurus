<?php

declare(strict_types=1);

namespace App\Controller\Routine;

use App\Entity\User;
use App\Repository\RoutineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-routines/verifier-nom',
    'en' => '/my-routines/check-name',
    'it' => '/le-mie-routine/verifica-nome',
    'es' => '/mis-rutinas/verificar-nombre',
    'pt' => '/as-minhas-rotinas/verificar-nome',
    'de' => '/meine-routinen/name-pruefen',
    'nl' => '/mijn-routines/naam-controleren',
    'pl' => '/moje-plany/sprawdz-nazwe',
], name: 'app_routine_check_name', methods: ['GET'])]
final class RoutineCheckNameController extends AbstractController
{
    public function __construct(
        private readonly RoutineRepository $routineRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $name = trim((string) $request->query->get('name', ''));
        $excludeId = $request->query->get('excludeId');

        if ('' === $name) {
            return $this->json([
                'available' => true,
            ]);
        }

        $excludeUuid = null;

        if (null !== $excludeId && Uuid::isValid((string) $excludeId)) {
            $excludeUuid = Uuid::fromString((string) $excludeId);
        }

        $exists = $this->routineRepository->existsByNameForUser($name, $user, $excludeUuid);

        return $this->json([
            'available' => ! $exists,
        ]);
    }
}
