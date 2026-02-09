<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\Clock\now;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class LoginSuccessListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        $user->setLastLogin(now());

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
