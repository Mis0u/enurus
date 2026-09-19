<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\User;
use App\Form\ProfileConnectionRequestType;
use App\Service\ProfileSharing\ProfileConnectionOverviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: [
    'fr' => '/connexions',
    'en' => '/connections',
    'it' => '/connessioni',
    'es' => '/conexiones',
    'pt' => '/conexoes',
    'de' => '/verbindungen',
    'nl' => '/verbindingen',
    'pl' => '/polaczenia',
], name: 'app_profile_connection_list', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionListController extends AbstractController
{
    public function __construct(
        private readonly ProfileConnectionOverviewService $overviewService,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        return $this->render('profile_connection/list/index.html.twig', [
            'overview' => $this->overviewService->forUser($user),
            'requestForm' => $this->createForm(ProfileConnectionRequestType::class),
            'isDiscoverable' => $user->isDiscoverable,
            'shareCode' => $user->shareCode,
            'nickname' => $user->nickname,
        ]);
    }
}
