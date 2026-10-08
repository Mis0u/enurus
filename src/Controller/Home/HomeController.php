<?php

declare(strict_types=1);

namespace App\Controller\Home;

use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil publique, accessible sans authentification (comme TermsShowController) : un
 * visiteur doit comprendre ce qu'est le site avant qu'on lui demande de s'inscrire.
 */
final class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'app_home', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('home/index.html.twig', [
            'locales' => LocaleAllowedEnum::cases(),
            'currentLocale' => LocaleAllowedEnum::from($request->getLocale()),
        ]);
    }
}
