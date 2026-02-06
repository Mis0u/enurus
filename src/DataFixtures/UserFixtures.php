<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        for ($i = 0; 40 > $i; ++$i) {
            $user = new User();
            $user->setEmail(\sprintf('user-fixture-%s@test.com', $i));
            if (0 === $i % 5) {
                $user->setGender('female');
            } else {
                $user->setGender('male');
            }
            $password = $this->passwordHasher->hashPassword($user, 'pass_1234');
            $user->setPassword($password);
            $manager->persist($user);
        }

        $manager->flush();
    }
}
