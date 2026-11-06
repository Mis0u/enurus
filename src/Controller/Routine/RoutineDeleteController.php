<?php

declare(strict_types=1);

namespace App\Controller\Routine;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\Routine;
use App\Security\Voter\RoutineVoter;
use App\Service\Entity\RoutineDeletionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-routines/{id}/supprimer',
    'en' => '/my-routines/{id}/delete',
    'it' => '/le-mie-routine/{id}/elimina',
    'es' => '/mis-rutinas/{id}/eliminar',
    'pt' => '/as-minhas-rotinas/{id}/eliminar',
    'de' => '/meine-routinen/{id}/loeschen',
    'nl' => '/mijn-routines/{id}/verwijderen',
    'pl' => '/moje-plany/{id}/usun',
], name: 'app_routine_delete', methods: ['DELETE'])]
final class RoutineDeleteController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly RoutineDeletionService $routineDeletionService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, Routine $routine): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        $this->denyAccessUnlessGranted(RoutineVoter::DELETE, $routine);

        if (null === $routine->id) {
            throw new \LogicException('Cannot delete a routine without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'routine_delete_' . $routine->id->toRfc4122());

        $archived = $this->routineDeletionService->delete($routine);

        return $this->json([
            'success' => true,
            'archived' => $archived,
            'message' => $this->translator->trans($archived ? 'routine.flash.archived' : 'routine.flash.deleted', [
                '{name}' => $routine->name,
            ], 'navigation'),
        ]);
    }
}
