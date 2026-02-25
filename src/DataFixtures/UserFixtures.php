<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use function Symfony\Component\Clock\now;
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
            $lastLogin = now(\sprintf('+%d day +%d hours', $i, $i));
            $user->setEmail(\sprintf('user-fixture-%s@test.com', $i))
                ->setNickname(\sprintf('user-fixture-%s', $i))
                ->setCreatedBy($user)
                ->setLastLogin($lastLogin);
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
