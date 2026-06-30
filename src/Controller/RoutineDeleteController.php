<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Routine;
use App\Security\Voter\RoutineVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(Request $request, Routine $routine): JsonResponse
    {
        if (! $request->isXmlHttpRequest()) {
            return $this->json([
                'error' => 'XHR only',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(RoutineVoter::DELETE, $routine);

        $this->em->remove($routine);
        $this->em->flush();

        return $this->json([
            'success' => true,
        ]);
    }
}
