<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ResetPasswordRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ResetPasswordTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testRequestPasswordResetPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/reinitialiser-mot-de-passe');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists(
            'input#reset_password_request_form__token[type="hidden"]',
            'le champ caché n\'existe pas ou son type est incorrect'
        );
    }

    public function testRequestPasswordResetWithValidEmail(): void
    {
        $this->fillEmailRequestPasswordResetForm('user-fixture-0@test.com', 1, 1);
    }

    public function testRequestPasswordResetWithUnknownValidEmail(): void
    {
        $this->fillEmailRequestPasswordResetForm('unknown-email@test.com', 0, 0);
    }

    private function fillEmailRequestPasswordResetForm(string $email, int $countRequest, int $countMessage): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get('doctrine');
        $crawler = $client->request(Request::METHOD_GET, '/fr/reinitialiser-mot-de-passe');
        $buttonCrawlerNode = $crawler->selectButton('Envoyer le lien de réinitialisation');
        $form = $buttonCrawlerNode->form();

        $resetPasswordRequestRepository = $entityManager->getRepository(ResetPasswordRequest::class);

        $this->assertSame(0, $resetPasswordRequestRepository->count());

        $client->submit($form, [
            'reset_password_request_form[email]' => $email,
        ]);

        $this->assertResponseRedirects('/fr/reinitialiser-mot-de-passe/verifier-email');

        /**
         * Vérifié avant `followRedirect()` : celui-ci reboote le kernel de test, ce qui vide le
         * listener mailer en mémoire (`mailer.message_logger_listener`, tag `kernel.reset`) — les
         * événements de la requête POST seraient perdus si on les lisait après.
         */
        $this->assertQueuedEmailCount($countMessage);

        $client->followRedirect();

        $this->assertSame($countRequest, $resetPasswordRequestRepository->count());

        $this->assertSelectorExists('a[href="/fr/reinitialiser-mot-de-passe"]');
    }
}
