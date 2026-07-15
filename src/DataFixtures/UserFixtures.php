<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\Contact\ContactRestrictionDurationEnum;
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

    public const string USER_REVERSE_FLY = 'user-fixture-exercise-reverse-fly@test.com';

    public const string USER_TIRAGE_SUPINATION = 'user-fixture-exercise-tirage-supination@test.com';

    public const string USER_ROUTINE_OWNER = 'user-fixture-routine-owner@test.com';

    public const string USER_ROUTINE_OTHER = 'user-fixture-routine-other@test.com';

    public const string USER_DASHBOARD_SINGLE = 'user-fixture-1-workout@test.com';

    public const string USER_ADMIN = 'admin-fixture@test.com';

    public const string USER_RESTRICTED_ONE_WEEK = 'user-fixture-restricted-1-week@test.com';

    public const string USER_RESTRICTED_ONE_MONTH = 'user-fixture-restricted-1-month@test.com';

    public const string USER_RESTRICTED_PERMANENT = 'user-fixture-restricted-permanent@test.com';

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
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadLocaleUsers($manager);
        $this->loadIndexedUsers($manager);
        $this->loadWorkoutUsers($manager);
        $this->loadExerciseUsers($manager);
        $this->loadRoutineUsers($manager);
        $this->loadDashboardUsers($manager);
        $this->loadAdminUser($manager);
        $this->loadRestrictedUsers($manager);

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
            $user->gender = GenderEnum::MALE;
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
            $user->gender = 0 === $i % 5 ? GenderEnum::FEMALE : GenderEnum::MALE;
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
            $user->gender = 'user-fixture-26-workout@test.com' === $userData['email']
                ? GenderEnum::FEMALE
                : GenderEnum::MALE;
            $user->lastLogin = new \DateTimeImmutable();
            $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');

            if ('user-fixture-51-workout@test.com' === $userData['email']) {
                $user->unitOfMeasure = UnitOfMeasureEnum::LBS;
            }

            $manager->persist($user);

            $this->addReference(
                \sprintf('%s%s', self::REFERENCE_PREFIX, $userData['email']),
                $user,
            );
        }
    }

    private function loadExerciseUsers(ObjectManager $manager): void
    {
        foreach ($this->exerciseUsers() as $userData) {
            $user = new User();
            $user->email = $userData['email'];
            $user->nickname = $userData['nickname'];
            $user->createdBy = $user;
            $user->locale = LocaleAllowedEnum::FR->value;
            $user->gender = GenderEnum::MALE;
            $user->lastLogin = new \DateTimeImmutable();
            $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');

            $manager->persist($user);

            $this->addReference(
                \sprintf('%s%s', self::REFERENCE_PREFIX, $userData['email']),
                $user,
            );
        }
    }

    private function loadRoutineUsers(ObjectManager $manager): void
    {
        foreach ($this->routineUsers() as $userData) {
            $user = new User();
            $user->email = $userData['email'];
            $user->nickname = $userData['nickname'];
            $user->createdBy = $user;
            $user->locale = LocaleAllowedEnum::FR->value;
            $user->gender = GenderEnum::MALE;
            $user->lastLogin = new \DateTimeImmutable();
            $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');

            $manager->persist($user);

            $this->addReference(
                \sprintf('%s%s', self::REFERENCE_PREFIX, $userData['email']),
                $user,
            );
        }
    }

    /**
     * @return array<int, array{email: string, nickname: string}>
     */
    private function exerciseUsers(): array
    {
        return [
            [
                'email' => self::USER_REVERSE_FLY,
                'nickname' => 'user-reverse-fly',
            ],
            [
                'email' => self::USER_TIRAGE_SUPINATION,
                'nickname' => 'user-tirage-supination',
            ],
        ];
    }

    private function loadDashboardUsers(ObjectManager $manager): void
    {
        $user = new User();
        $user->email = self::USER_DASHBOARD_SINGLE;
        $user->nickname = 'user-dashboard-1-workout';
        $user->createdBy = $user;
        $user->locale = LocaleAllowedEnum::FR->value;
        $user->gender = GenderEnum::MALE;
        $user->lastLogin = new \DateTimeImmutable();
        $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');

        $manager->persist($user);

        $this->addReference(
            \sprintf('%s%s', self::REFERENCE_PREFIX, self::USER_DASHBOARD_SINGLE),
            $user,
        );
    }

    private function loadAdminUser(ObjectManager $manager): void
    {
        $user = new User();
        $user->email = self::USER_ADMIN;
        $user->nickname = 'admin-fixture';
        $user->createdBy = $user;
        $user->locale = LocaleAllowedEnum::FR->value;
        $user->gender = GenderEnum::MALE;
        $user->lastLogin = new \DateTimeImmutable();
        $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');
        $user->setRoles(['ROLE_ADMIN']);

        $manager->persist($user);

        $this->addReference(
            \sprintf('%s%s', self::REFERENCE_PREFIX, self::USER_ADMIN),
            $user,
        );
    }

    /**
     * Trois cas de restriction demandés (1 semaine / 1 mois / permanente) — pas d'interface admin
     * pour poser ces états pour l'instant, seules les fixtures les couvrent.
     */
    private function loadRestrictedUsers(ObjectManager $manager): void
    {
        $restrictedUsers = [
            [
                'email' => self::USER_RESTRICTED_ONE_WEEK,
                'nickname' => 'user-restricted-1-week',
                'until' => new \DateTimeImmutable('+7 days'),
                'duration' => ContactRestrictionDurationEnum::ONE_WEEK,
                'permanent' => false,
            ],
            [
                'email' => self::USER_RESTRICTED_ONE_MONTH,
                'nickname' => 'user-restricted-1-month',
                'until' => new \DateTimeImmutable('+30 days'),
                'duration' => ContactRestrictionDurationEnum::ONE_MONTH,
                'permanent' => false,
            ],
            [
                'email' => self::USER_RESTRICTED_PERMANENT,
                'nickname' => 'user-restricted-permanent',
                'until' => null,
                'duration' => null,
                'permanent' => true,
            ],
        ];

        foreach ($restrictedUsers as $userData) {
            $user = new User();
            $user->email = $userData['email'];
            $user->nickname = $userData['nickname'];
            $user->createdBy = $user;
            $user->locale = LocaleAllowedEnum::FR->value;
            $user->gender = GenderEnum::MALE;
            $user->lastLogin = new \DateTimeImmutable();
            $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');
            $user->contactRestrictedUntil = $userData['until'];
            $user->contactRestrictedPermanently = $userData['permanent'];
            $user->contactRestrictionDuration = $userData['duration'];

            $manager->persist($user);

            $this->addReference(
                \sprintf('%s%s', self::REFERENCE_PREFIX, $userData['email']),
                $user,
            );
        }
    }

    /**
     * @return array<int, array{email: string, nickname: string}>
     */
    private function routineUsers(): array
    {
        return [
            [
                'email' => self::USER_ROUTINE_OWNER,
                'nickname' => 'user-routine-owner',
            ],
            [
                'email' => self::USER_ROUTINE_OTHER,
                'nickname' => 'user-routine-other',
            ],
        ];
    }
}
