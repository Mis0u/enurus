<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
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
            $user->email = \sprintf('user-fixture-%s@test.com', $i);
            $user->nickname = \sprintf('user-fixture-%s', $i);
            $user->createdBy = $user;
            $user->locale = LocaleAllowedEnum::FR->value;
            $user->lastLogin = $lastLogin;
            if (0 === $i % 5) {
                $user->gender = 'female';
            } else {
                $user->gender = 'male';
            }
            $password = $this->passwordHasher->hashPassword($user, 'pass_1234');
            $user->password = $password;
            $manager->persist($user);
        }

        $manager->flush();
    }
}
