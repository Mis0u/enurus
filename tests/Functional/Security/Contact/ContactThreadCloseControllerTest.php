<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContactThreadCloseControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = 'user-fixture-2@test.com';

    /**
     * Le succès (admin authentifié + token CSRF valide) est couvert par
     * ContactThreadCloseServiceTest — aucune page ne rend encore ce token (pas d'interface admin),
     * donc pas de DOM à scraper ici (voir CLAUDE.md : jamais régénérer un token CSRF hors requête).
     * Ce test couvre uniquement l'autorisation, indépendante du token.
     */
    public function testRegularUserCannotCloseThread(): void
    {
        $client = $this->login(self::OWNER);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::OWNER);

        $thread = ContactThreadTestHelper::createThread($entityManager, $owner);

        $client->request(Request::METHOD_POST, \sprintf('/fr/messagerie/%s/cloturer', $thread->id), [
            '_token' => 'irrelevant-blocked-before-csrf-check',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
