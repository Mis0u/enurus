<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\Clock\now;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class UserService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    public function createUser(User $user, FormInterface $form, string $locale): User
    {
        $this->hashPassword($user, $form);
        $user->setLastLogin(now());
        $user->setLocale($locale);
        $this->save($user);

        return $user;
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function hashPassword(
        User $user,
        FormInterface $form
    ): void {
        /** @var string $plainPassword */
        $plainPassword = $form->get('plainPassword')->getData();

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }
}
