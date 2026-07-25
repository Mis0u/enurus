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

        $this->assertCount(0, $crawler->filter('input[name="reset_password_form[currentPassword]"]'));
        $this->assertCount(1, $crawler->filter('button[data-password-validator-target="submitButton"]'));

        $form = $crawler->filter('form[name="reset_password_form"]')->form([
            'reset_password_form[plainPassword][first]' => 'NewValidPass123!',
            'reset_password_form[plainPassword][second]' => 'NewValidPass123!',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/fr/');
    }
}
