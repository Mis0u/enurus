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
    private const array USERS_ETHNIES = [
        'en' => 'user-english@test.com',
        'es' => 'user-spanish@test.com',
        'de' => 'user-german@test.com',
        'pt' => 'user-portuguese@test.com',
        'pl' => 'user-polish@test.com',
        'nl' => 'user-dutch@test.com',
        'it' => 'user-italian@test.com',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS_ETHNIES as $key => $userEmail) {
            $user = new User();
            $lastLogin = now(\sprintf('+%d day +%d hours', 1, 1));
            $user->email = $userEmail;
            $user->nickname = \sprintf('user-country-%s', $key);
            $user->createdBy = $user;
            $user->locale = $key;
            $user->lastLogin = $lastLogin;
            $user->gender = 'male';
            $password = $this->passwordHasher->hashPassword($user, 'pass_1234');
            $user->password = $password;

            $manager->persist($user);
        }
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
