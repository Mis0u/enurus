<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\DeloadPeriod;
use App\Security\Voter\DeloadPeriodVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-seances/deload/{id}/supprimer',
    'en' => '/my-workouts/deload/{id}/delete',
    'it' => '/le-mie-sedute/deload/{id}/elimina',
    'es' => '/mis-entrenamientos/deload/{id}/eliminar',
    'pt' => '/os-meus-treinos/deload/{id}/eliminar',
    'de' => '/meine-trainings/deload/{id}/loeschen',
    'nl' => '/mijn-trainingen/deload/{id}/verwijderen',
    'pl' => '/moje-treningi/deload/{id}/usun',
], name: 'app_deload_period_delete', methods: ['DELETE'])]
final class DeloadPeriodDeleteController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, DeloadPeriod $deloadPeriod): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        $this->denyAccessUnlessGranted(DeloadPeriodVoter::DELETE, $deloadPeriod);

        if (null === $deloadPeriod->id) {
            throw new \LogicException('Cannot delete a deload period without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'deload_period_delete_' . $deloadPeriod->id->toRfc4122());

        $this->em->remove($deloadPeriod);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('workout.list.calendar.flash.deleted', [], 'navigation'),
        ]);
    }
}
