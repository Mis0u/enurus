<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\UserCrudController;
use App\Entity\ResetPasswordRequest;
use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\EventListener\SessionLinkListener;
use App\Repository\UserRepository;
use App\Service\Security\SessionInvalidationService;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class UserCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testIndexListsUsers(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-index@test.com');

        $client->request(Request::METHOD_GET, $this->indexUrl());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="' . $this->actionUrl($client, $user, 'detail') . '"]');

        $this->deleteTestUser($user);
    }

    public function testEditUpdatesScalarFields(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-edit@test.com');

        $crawler = $client->request(Request::METHOD_GET, $this->actionUrl($client, $user, 'edit'));
        $form = $crawler->selectButton('Save changes')->form([
            'User[email]' => 'user-crud-edit@test.com',
            'User[nickname]' => 'EditedNickname',
            'User[gender]' => $this->optionValueByLabel($crawler, 'User[gender]', 'FEMALE'),
            'User[locale]' => 'it',
            'User[unitOfMeasure]' => $this->optionValueByLabel($crawler, 'User[unitOfMeasure]', 'LBS'),
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();

        $reloaded = $this->reloadUser($user);
        self::assertSame('EditedNickname', $reloaded->nickname);
        self::assertSame('it', $reloaded->locale);
        self::assertSame('lbs', $reloaded->unitOfMeasure->value);

        $this->deleteTestUser($reloaded);
    }

    public function testCancelDeletionClearsDeletionRequest(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-cancel@test.com');
        $user->deletionRequestedAt = new \DateTimeImmutable();
        $this->flush();

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $user, 'cancelDeletion'),
            'input[name="_token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $user, 'cancelDeletion'), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        $reloaded = $this->reloadUser($user);
        self::assertNull($reloaded->deletionRequestedAt);

        $this->deleteTestUser($reloaded);
    }

    public function testBlockPermanentlyRestrictsUser(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-block@test.com');

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $user, 'restrictContact'));
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Bloquer')->form([
            'contact_restriction_form[duration]' => 'permanent',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();

        $reloaded = $this->reloadUser($user);
        self::assertTrue($reloaded->isContactRestricted);

        $this->deleteTestUser($reloaded);
    }

    public function testUnblockLiftsRestriction(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-unblock@test.com');
        $user->contactRestrictedPermanently = true;
        $this->flush();

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $user, 'liftContactRestriction'),
            'input[name="_token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $user, 'liftContactRestriction'), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        $reloaded = $this->reloadUser($user);
        self::assertFalse($reloaded->isContactRestricted);

        $this->deleteTestUser($reloaded);
    }

    public function testBlockAccountInvalidatesSessionsAndPreventsLogin(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-block-account@test.com');

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $user, 'blockAccount'),
            'input[name="_token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $user, 'blockAccount'), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        $reloaded = $this->reloadUser($user);
        self::assertTrue($reloaded->isAccountBlocked);

        $this->deleteTestUser($reloaded);
    }

    public function testUnblockAccountAllowsLoginAgain(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-unblock-account@test.com');
        $user->accountBlockedAt = new \DateTimeImmutable();
        $this->flush();

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $user, 'unblockAccount'),
            'input[name="_token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $user, 'unblockAccount'), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        $reloaded = $this->reloadUser($user);
        self::assertFalse($reloaded->isAccountBlocked);

        $this->deleteTestUser($reloaded);
    }

    public function testForceLogoutInvalidatesSessions(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-logout@test.com');

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $user, 'forceLogout'),
            'input[name="_token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $user, 'forceLogout'), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        $this->deleteTestUser($user);
    }

    /**
     * Reproduces the exact race `SessionLinkListener` is guarding against: on a real login,
     * `LoginSuccessEvent` fires mid-request, well before
     * `App\Security\Session\DoctrineSessionHandler::write()` ever persists the session row (PHP
     * only flushes session storage at request shutdown) — a plain UPDATE used to silently match 0
     * rows, leaving `user_id` unlinked and the session unreachable to
     * `SessionInvalidationService::invalidateAllSessions()` for its entire lifetime. Dispatches the
     * event directly (rather than a second `static::createClient()`, which this project's test
     * convention forbids — one client per test) against a session id known to have no row yet.
     */
    public function testSessionLinkListenerLinksASessionRowThatDoesNotExistYet(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-session-link@test.com');
        $sessionId = 'session-link-test-' . bin2hex(random_bytes(8));

        $storage = new MockArraySessionStorage();
        $storage->setId($sessionId);
        $storage->start();
        $session = new Session($storage);

        $request = Request::create('/');
        $request->setSession($session);

        $event = new LoginSuccessEvent(
            self::createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge($user->email, static fn (): User => $user)),
            new UsernamePasswordToken($user, 'main', $user->getRoles()),
            $request,
            null,
            'main',
        );

        /** @var SessionLinkListener $listener */
        $listener = static::getContainer()->get(SessionLinkListener::class);
        $listener->onLoginSuccessEvent($event);

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $linkedUserId = $connection->fetchOne('SELECT user_id FROM sessions WHERE session_id = :id', [
            'id' => $sessionId,
        ]);
        self::assertIsString($linkedUserId);
        self::assertSame($user->id?->toRfc4122(), $linkedUserId);

        /** @var SessionInvalidationService $sessionInvalidationService */
        $sessionInvalidationService = static::getContainer()->get(SessionInvalidationService::class);
        $sessionInvalidationService->invalidateAllSessions($user);

        $remainingRow = $connection->fetchOne('SELECT 1 FROM sessions WHERE session_id = :id', [
            'id' => $sessionId,
        ]);
        self::assertFalse($remainingRow);

        $this->deleteTestUser($user);
    }

    public function testResendResetEmailClearsExistingRequestAndGeneratesNewToken(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-resend@test.com');

        /** @var ResetPasswordHelperInterface $resetPasswordHelper */
        $resetPasswordHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetPasswordHelper->generateResetToken($user);

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $user, 'resendResetEmail'),
            'input[name="_token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $user, 'resendResetEmail'), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $requests = $entityManager->getRepository(ResetPasswordRequest::class)->findBy([
            'user' => $user,
        ]);
        self::assertCount(1, $requests);

        $this->deleteTestUser($user);
    }

    public function testForceDeleteWithCorrectPasswordRemovesUser(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-force-delete@test.com');

        $crawler = $client->request(Request::METHOD_GET, $this->actionUrl($client, $user, 'forceDelete'));
        $form = $crawler->selectButton('Supprimer définitivement')->form([
            'user_force_delete_form[password]' => 'pass_1234',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        self::assertNull($userRepository->findOneByEmail('user-crud-force-delete@test.com'));
    }

    public function testForceDeleteWithWrongPasswordKeepsUser(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-force-delete-wrong@test.com');

        $crawler = $client->request(Request::METHOD_GET, $this->actionUrl($client, $user, 'forceDelete'));
        $form = $crawler->selectButton('Supprimer définitivement')->form([
            'user_force_delete_form[password]' => 'wrong-password',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);

        $reloaded = $this->reloadUser($user);
        self::assertInstanceOf(User::class, $reloaded);

        $this->deleteTestUser($reloaded);
    }

    public function testAdminAccountIsExcludedFromIndex(): void
    {
        $client = $this->login(self::ADMIN);

        $client->request(Request::METHOD_GET, $this->indexUrl());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('table.datagrid', self::ADMIN);
    }

    public function testAdminAccountDetailAndActionsAreNotFound(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $admin = $userRepository->findOneByEmail(self::ADMIN);
        self::assertInstanceOf(User::class, $admin);

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $admin, 'detail'));
        self::assertResponseStatusCodeSame(404);

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $admin, 'forceDelete'));
        self::assertResponseStatusCodeSame(404);

        self::assertInstanceOf(User::class, $userRepository->findOneByEmail(self::ADMIN));
    }

    public function testEmailFilterNarrowsIndexToMatchingUser(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-filter-email@test.com');

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction('index')
            ->set('filters[email][comparison]', '=')
            ->set('filters[email][value]', 'user-crud-filter-email@test.com')
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'user-crud-filter-email@test.com');
        self::assertSelectorTextNotContains('table.datagrid', 'user-fixture-0@test.com');

        $this->deleteTestUser($user);
    }

    public function testEmailFilterSelectorOnlyListsExistingEmails(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-filter-selector@test.com');

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator->setController(UserCrudController::class)->setAction('renderFilters')->generateUrl();

        $crawler = $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        $emailOptions = $crawler->filter('select[name="filters[email][value]"] option')->each(
            static fn (Crawler $node): string => trim($node->text()),
        );

        self::assertContains('user-crud-filter-selector@test.com', $emailOptions);

        $this->deleteTestUser($user);
    }

    public function testGenderFilterNarrowsIndexResults(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $unfilteredUrl = $adminUrlGenerator->setController(UserCrudController::class)->setAction('index')->generateUrl();
        $crawler = $client->request(Request::METHOD_GET, $unfilteredUrl);
        $unfilteredCount = $crawler->filter('table.datagrid tbody tr')->count();

        // "1" et non "female" : ChoiceFilter sur un enum PHP rend des <option value="0|1"> indexées
        // (comme le formulaire d'édition), pas les valeurs de l'enum — "1" correspond à FEMALE,
        // second cas déclaré dans GenderEnum::cases().
        $filteredUrl = $adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction('index')
            ->set('filters[gender][comparison]', '=')
            ->set('filters[gender][value]', '1')
            ->generateUrl()
        ;
        $crawler = $client->request(Request::METHOD_GET, $filteredUrl);
        $filteredCount = $crawler->filter('table.datagrid tbody tr')->count();

        self::assertResponseIsSuccessful();
        self::assertLessThan($unfilteredCount, $filteredCount);
    }

    public function testLastLoginDayFilterMatchesRegardlessOfTimeOfDay(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-filter-day@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        // Heure très tardive dans la journée — ne matcherait jamais une comparaison exacte contre
        // une date sans heure (implicitement minuit), seulement un filtre "jour entier".
        $user->lastLogin = (new \DateTimeImmutable())->setTime(23, 45);
        $entityManager->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction('index')
            ->set('filters[lastLogin][value]', (new \DateTimeImmutable())->format('Y-m-d'))
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'user-crud-filter-day@test.com');

        $this->deleteTestUser($user);
    }

    public function testDeletionRequestedFilterNarrowsIndexResults(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-filter-deletion@test.com');
        $user->deletionRequestedAt = new \DateTimeImmutable();
        $this->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction('index')
            ->set('filters[deletionRequestedAt]', 'not_null')
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'user-crud-filter-deletion@test.com');
        self::assertSelectorTextNotContains('table.datagrid', 'user-fixture-0@test.com');

        $this->deleteTestUser($user);
    }

    public function testDetailShowsRoutineAndWorkoutCounts(): void
    {
        $client = $this->login(self::ADMIN);
        $user = $this->createTestUser('user-crud-detail@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $routine = new Routine();
        $routine->owner = $user;
        $routine->name = 'Routine de test';
        $entityManager->persist($routine);

        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = new \DateTimeImmutable('2026-01-15');
        $entityManager->persist($workout);

        $entityManager->flush();
        $routineId = $routine->id;
        $workoutId = $workout->id;

        /**
         * `clear()` avant la requête : le client de test ne rebootant pas systématiquement le
         * kernel entre deux appels, `$user` resterait sinon le même objet PHP dont les collections
         * `routines`/`workouts` sont l'`ArrayCollection` vide posée par le constructeur — jamais
         * réhydratée depuis la base tant que l'entité n'est pas rechargée via une vraie requête.
         * En usage réel (deux vraies requêtes HTTP séparées), ce problème ne se pose pas.
         */
        $entityManager->clear();

        $crawler = $client->request(Request::METHOD_GET, $this->actionUrl($client, $user, 'detail'));

        self::assertResponseIsSuccessful();
        self::assertSame('1', $this->fieldValueByLabel($crawler, 'Routines'));
        self::assertSame('1', $this->fieldValueByLabel($crawler, 'Séances'));

        /** @var Routine $reloadedRoutine */
        $reloadedRoutine = $entityManager->getRepository(Routine::class)->find($routineId);
        /** @var Workout $reloadedWorkout */
        $reloadedWorkout = $entityManager->getRepository(Workout::class)->find($workoutId);
        $entityManager->remove($reloadedRoutine);
        $entityManager->remove($reloadedWorkout);
        $entityManager->flush();
        $this->deleteTestUser($user);
    }

    private function indexUrl(): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator->setController(UserCrudController::class)->setAction('index')->generateUrl();
    }

    private function actionUrl(KernelBrowser $client, User $user, string $action): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction($action)
            ->setEntityId($user->id)
            ->generateUrl()
        ;
    }

    private function createTestUser(string $email): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'T' . substr(bin2hex(random_bytes(8)), 0, 16);
        $user->lastLogin = new \DateTimeImmutable();
        $user->locale = 'fr';

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function deleteTestUser(User $user): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $reloaded = $userRepository->findOneByEmail($user->email);

        if (! $reloaded instanceof User) {
            return;
        }

        foreach ($entityManager->getRepository(ResetPasswordRequest::class)->findBy([
            'user' => $reloaded,
        ]) as $resetPasswordRequest) {
            $entityManager->remove($resetPasswordRequest);
        }

        $entityManager->remove($reloaded);
        $entityManager->flush();
    }

    private function optionValueByLabel(Crawler $crawler, string $selectName, string $label): string
    {
        /** @var string */
        return $crawler
            ->filter(\sprintf('select[name="%s"] option', $selectName))
            ->reduce(static fn (Crawler $option): bool => $label === trim($option->text()))
            ->first()
            ->attr('value')
        ;
    }

    private function fieldValueByLabel(Crawler $crawler, string $label): string
    {
        return trim(
            $crawler
                ->filter('.field-group')
                ->reduce(static fn (Crawler $group): bool => $label === trim($group->filter('.field-label')->text()))
                ->first()
                ->filter('.field-value')
                ->text()
        );
    }

    private function reloadUser(User $user): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /** @var User $reloaded */
        $reloaded = $userRepository->findOneByEmail($user->email);

        return $reloaded;
    }

    private function flush(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
    }
}
