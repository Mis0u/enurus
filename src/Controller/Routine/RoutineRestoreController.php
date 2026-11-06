<?php

declare(strict_types=1);

namespace App\Controller\Routine;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\Routine;
use App\Security\Voter\RoutineVoter;
use App\Service\Entity\RoutineRestoreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-routines/{id}/desarchiver',
    'en' => '/my-routines/{id}/restore',
    'it' => '/le-mie-routine/{id}/ripristina',
    'es' => '/mis-rutinas/{id}/restaurar',
    'pt' => '/as-minhas-rotinas/{id}/restaurar',
    'de' => '/meine-routinen/{id}/wiederherstellen',
    'nl' => '/mijn-routines/{id}/herstellen',
    'pl' => '/moje-plany/{id}/przywroc',
], name: 'app_routine_restore', methods: ['POST'])]
final class RoutineRestoreController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly RoutineRestoreService $routineRestoreService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, Routine $routine): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        $this->denyAccessUnlessGranted(RoutineVoter::EDIT, $routine);

        if (null === $routine->id) {
            throw new \LogicException('Cannot restore a routine without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'routine_restore_' . $routine->id->toRfc4122());

        if (! $this->routineRestoreService->restore($routine)) {
            return $this->json([
                'success' => false,
            ], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('routine.flash.restored', [
                '{name}' => $routine->name,
            ], 'navigation'),
        ]);
    }
}
