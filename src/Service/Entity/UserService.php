<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
    public function createUser(User $user, FormInterface $form): User
    {
        $this->hashPassword($user, $form);
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
