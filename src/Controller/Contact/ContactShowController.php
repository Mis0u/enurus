<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ContactShowController extends AbstractController
{
    #[Route(
        path: [
            'en' => '/contact',
            'fr' => '/contact',
            'it' => '/contatti',
            'es' => '/contacto',
            'pt' => '/contacto',
            'de' => '/kontakt',
            'nl' => '/contact',
            'pl' => '/kontakt',
        ],
        name: 'app_contact',
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isContactRestricted) {
            return $this->render('contact/show.html.twig', [
                'restricted' => true,
                'user' => $user,
            ]);
        }

        return $this->render('contact/show.html.twig', [
            'restricted' => false,
            'contactForm' => $this->createForm(ContactFormType::class),
            'imageMaxSizeBytes' => ImageConstraints::MAX_SIZE_BYTES,
            'imageAllowedMimeTypes' => ImageConstraints::ALLOWED_MIME_TYPES,
        ]);
    }
}
