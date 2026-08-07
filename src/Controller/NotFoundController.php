<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catch-all pour toute URL sous un préfixe de locale valide qui ne correspond à aucune route
 * réelle. Contrairement à `ErrorPageController` (invoqué par `error_controller` sur
 * kernel.exception quand AUCUNE route ne matche), cette route matche réellement : la requête
 * traverse tout le pipeline normal — firewall inclus — avant d'arriver ici. `getUser()` y est
 * donc fiable, ce qui permet d'afficher la 404 dans la locale du compte plutôt que dans la
 * locale par défaut de l'app (voir ErrorPageController::resolveLocale() pour le détail du piège).
 */
final class NotFoundController extends AbstractController
{
    #[Route(path: '/{catchall}', name: 'app_not_found', requirements: [
        'catchall' => '.*',
    ], priority: -100)]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();

        return $this->render('error/error.html.twig', [
            'statusCode' => Response::HTTP_NOT_FOUND,
            'locale' => $user instanceof User ? $user->locale : $request->getLocale(),
        ], new Response('', Response::HTTP_NOT_FOUND));
    }
}
