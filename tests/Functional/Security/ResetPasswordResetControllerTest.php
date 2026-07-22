<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\SessionInvalidationService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ResetPasswordResetControllerTest extends WebTestCase
{
    private const string USER = 'user-fixture-0@test.com';

    public function testResetPasswordInvalidatesAllSessions(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneByEmail(self::USER);

        if (! $user instanceof User || null === $user->id) {
            throw new \LogicException('Fixture user not found.');
        }

        $userId = $user->id;

        /** @var ResetPasswordHelperInterface $resetPasswordHelper */
        $resetPasswordHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $resetToken = $resetPasswordHelper->generateResetToken($user);

        /** @var MockObject&SessionInvalidationService $sessionInvalidationService */
        $sessionInvalidationService = $this->createMock(SessionInvalidationService::class);
        $sessionInvalidationService->expects(self::once())
            ->method('invalidateAllSessions')
            ->with(self::callback(static fn (User $u): bool => $u->id?->equals($userId) ?? false));

        static::getContainer()->set(SessionInvalidationService::class, $sessionInvalidationService);

        $client->request(Request::METHOD_GET, '/fr/reinitialiser-mot-de-passe/reinitialiser/' . $resetToken->getToken());
        $this->assertResponseRedirects('/fr/reinitialiser-mot-de-passe/reinitialiser');
        $crawler = $client->followRedirect();

        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[currentPassword]' => 'irrelevant-for-reset',
            'change_password_form[plainPassword][first]' => 'NewValidPass123!',
            'change_password_form[plainPassword][second]' => 'NewValidPass123!',
        ]);

        // NB: ChangePasswordFormType::configureOptions() fixe en dur l'action sur l'endpoint des
        // réglages (bug préexistant, hors périmètre ici) — on poste donc explicitement vers la
        // route de reset plutôt que de suivre $form->getUri().
        $client->request($form->getMethod(), '/fr/reinitialiser-mot-de-passe/reinitialiser', $form->getPhpValues(), $form->getPhpFiles());

        $this->assertResponseRedirects('/fr/');
    }
}
