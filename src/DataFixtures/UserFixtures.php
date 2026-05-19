<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\Entity\User\GenderEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use function Symfony\Component\Clock\now;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const string REFERENCE_PREFIX = 'user_';

    /**
     * @var array<int, array{email: string, nickname: string, count: int, spreadDays: int}>
     */
    public const array WORKOUT_USERS = [
        [
            'email' => 'user-fixture-11-workout@test.com',
            'nickname' => 'user-fixture-11-workout',
            'count' => 11,
            'spreadDays' => 42,
        ],
        [
            'email' => 'user-fixture-26-workout@test.com',
            'nickname' => 'user-fixture-26-workout',
            'count' => 26,
            'spreadDays' => 90,
        ],
        [
            'email' => 'user-fixture-51-workout@test.com',
            'nickname' => 'user-fixture-51-workout',
            'count' => 51,
            'spreadDays' => 180,
        ],
    ];

    private const array USERS_ETHNIES = [
        'en' => 'user-fixture-english@test.com',
        'es' => 'user-fixture-spanish@test.com',
        'de' => 'user-fixture-german@test.com',
        'pt' => 'user-fixture-portuguese@test.com',
        'pl' => 'user-fixture-polish@test.com',
        'nl' => 'user-fixture-dutch@test.com',
        'it' => 'user-fixture-italian@test.com',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadLocaleUsers($manager);
        $this->loadIndexedUsers($manager);
        $this->loadWorkoutUsers($manager);

        $manager->flush();
    }

    private function loadLocaleUsers(ObjectManager $manager): void
    {
        foreach (self::USERS_ETHNIES as $key => $userEmail) {
            $user = new User();
            $lastLogin = now(\sprintf('+%d day +%d hours', 1, 1));
            $user->email = $userEmail;
            $user->nickname = \sprintf('user-country-%s', $key);
            $user->createdBy = $user;
            $user->locale = $key;
            $user->lastLogin = $lastLogin;
            $user->gender = GenderEnum::MALE->value;
            $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');

            $manager->persist($user);
        }
    }

    private function loadIndexedUsers(ObjectManager $manager): void
    {
        for ($i = 0; 10 > $i; ++$i) {
            $user = new User();
            $lastLogin = now(\sprintf('+%d day +%d hours', $i, $i));
            $user->email = \sprintf('user-fixture-%s@test.com', $i);
            $user->nickname = \sprintf('user-fixture-%s', $i);
            $user->createdBy = $user;
            $user->locale = LocaleAllowedEnum::FR->value;
            $user->lastLogin = $lastLogin;
            $user->gender = 0 === $i % 5 ? GenderEnum::FEMALE->value : GenderEnum::MALE->value;
            $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');

            $manager->persist($user);
        }
    }

    private function loadWorkoutUsers(ObjectManager $manager): void
    {
        foreach (self::WORKOUT_USERS as $userData) {
            $user = new User();
            $user->email = $userData['email'];
            $user->nickname = $userData['nickname'];
            $user->createdBy = $user;
            $user->locale = LocaleAllowedEnum::FR->value;
            $user->gender = 'user-fixture-26-workout@test.com' === $userData['email'] ? GenderEnum::FEMALE->value : GenderEnum::MALE->value;
            $user->lastLogin = new \DateTimeImmutable();
            $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');
            if ('user-fixture-51-workout@test.com' === $userData['email']) {
                $user->unitOfMeasure = UnitOfMeasureEnum::LBS;
            }

            $manager->persist($user);

            $this->addReference(
                \sprintf('%s%s', self::REFERENCE_PREFIX, $userData['email']),
                $user
            );
        }
    }
}
