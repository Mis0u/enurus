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
            'nickname' => 'user-workout-11',
            'count' => 11,
            'spreadDays' => 42,
        ],
        [
            'email' => 'user-fixture-26-workout@test.com',
            'nickname' => 'user-workout-26',
            'count' => 26,
            'spreadDays' => 90,
        ],
        [
            'email' => 'user-fixture-51-workout@test.com',
            'nickname' => 'user-workout-51',
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
        LocaleAllowedEnum::EN->value => 'user-fixture-english@test.com',
        LocaleAllowedEnum::ES->value => 'user-fixture-spanish@test.com',
        LocaleAllowedEnum::DE->value => 'user-fixture-german@test.com',
        LocaleAllowedEnum::PT->value => 'user-fixture-portuguese@test.com',
        LocaleAllowedEnum::PL->value => 'user-fixture-polish@test.com',
        LocaleAllowedEnum::NL->value => 'user-fixture-dutch@test.com',
        LocaleAllowedEnum::IT->value => 'user-fixture-italian@test.com',
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

    /**
     * Construit un User avec les champs communs à toutes les fixtures (mot de passe, alias créé
     * par lui-même, dernière connexion). Les appelants n'ajustent que ce qui les distingue.
     */
    private function createUser(
        string $email,
        string $nickname,
        string $locale = LocaleAllowedEnum::FR->value,
        GenderEnum $gender = GenderEnum::MALE,
        ?\DateTimeImmutable $lastLogin = null,
    ): User {
        $user = new User();
        $user->email = $email;
        $user->nickname = $nickname;
        $user->createdBy = $user;
        $user->locale = $locale;
        $user->gender = $gender;
        $user->lastLogin = $lastLogin ?? new \DateTimeImmutable();
        $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');
        $user->isVerified = true;

        return $user;
    }

    private function addUserReference(User $user, string $email): void
    {
        $this->addReference(\sprintf('%s%s', self::REFERENCE_PREFIX, $email), $user);
    }

    private function loadLocaleUsers(ObjectManager $manager): void
    {
        $lastLogin = now(\sprintf('+%d day +%d hours', 1, 1));

        foreach (self::USERS_ETHNIES as $key => $userEmail) {
            $user = $this->createUser($userEmail, \sprintf('user-country-%s', $key), $key, lastLogin: $lastLogin);

            $manager->persist($user);
        }
    }

    private function loadIndexedUsers(ObjectManager $manager): void
    {
        for ($i = 0; 10 > $i; ++$i) {
            $gender = 0 === $i % 5 ? GenderEnum::FEMALE : GenderEnum::MALE;
            $lastLogin = now(\sprintf('+%d day +%d hours', $i, $i));

            $user = $this->createUser(
                \sprintf('user-fixture-%s@test.com', $i),
                \sprintf('user-fixture-%s', $i),
                gender: $gender,
                lastLogin: $lastLogin,
            );

            $manager->persist($user);
        }
    }

    private function loadWorkoutUsers(ObjectManager $manager): void
    {
        foreach (self::WORKOUT_USERS as $userData) {
            $gender = 'user-fixture-26-workout@test.com' === $userData['email']
                ? GenderEnum::FEMALE
                : GenderEnum::MALE;

            $user = $this->createUser($userData['email'], $userData['nickname'], gender: $gender);

            if ('user-fixture-51-workout@test.com' === $userData['email']) {
                $user->unitOfMeasure = UnitOfMeasureEnum::LBS;
            }

            $manager->persist($user);
            $this->addUserReference($user, $userData['email']);
        }
    }

    private function loadExerciseUsers(ObjectManager $manager): void
    {
        foreach ($this->exerciseUsers() as $userData) {
            $user = $this->createUser($userData['email'], $userData['nickname']);

            $manager->persist($user);
            $this->addUserReference($user, $userData['email']);
        }
    }

    private function loadRoutineUsers(ObjectManager $manager): void
    {
        foreach ($this->routineUsers() as $userData) {
            $user = $this->createUser($userData['email'], $userData['nickname']);

            $manager->persist($user);
            $this->addUserReference($user, $userData['email']);
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
                'nickname' => 'user-tirage-sup',
            ],
        ];
    }

    private function loadDashboardUsers(ObjectManager $manager): void
    {
        $user = $this->createUser(self::USER_DASHBOARD_SINGLE, 'user-dashboard-1');

        $manager->persist($user);
        $this->addUserReference($user, self::USER_DASHBOARD_SINGLE);
    }

    private function loadAdminUser(ObjectManager $manager): void
    {
        $user = $this->createUser(self::USER_ADMIN, 'admin-fixture');
        $user->setRoles(['ROLE_ADMIN']);

        $manager->persist($user);
        $this->addUserReference($user, self::USER_ADMIN);
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
                'nickname' => 'user-restrict-1w',
                'until' => new \DateTimeImmutable('+7 days'),
                'duration' => ContactRestrictionDurationEnum::ONE_WEEK,
                'permanent' => false,
            ],
            [
                'email' => self::USER_RESTRICTED_ONE_MONTH,
                'nickname' => 'user-restrict-1m',
                'until' => new \DateTimeImmutable('+30 days'),
                'duration' => ContactRestrictionDurationEnum::ONE_MONTH,
                'permanent' => false,
            ],
            [
                'email' => self::USER_RESTRICTED_PERMANENT,
                'nickname' => 'user-restrict-perm',
                'until' => null,
                'duration' => null,
                'permanent' => true,
            ],
        ];

        foreach ($restrictedUsers as $userData) {
            $user = $this->createUser($userData['email'], $userData['nickname']);
            $user->contactRestrictedUntil = $userData['until'];
            $user->contactRestrictedPermanently = $userData['permanent'];
            $user->contactRestrictionDuration = $userData['duration'];

            $manager->persist($user);
            $this->addUserReference($user, $userData['email']);
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
