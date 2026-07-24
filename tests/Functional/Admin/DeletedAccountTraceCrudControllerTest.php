<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DeletedAccountTraceCrudController;
use App\Entity\DeletedAccountTrace;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class DeletedAccountTraceCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testIndexListsTraceHashWithoutLeakingEmail(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $trace = new DeletedAccountTrace();
        $trace->emailHash = hash('sha256', 'trace-test@test.com');
        $trace->deletedAt = new \DateTimeImmutable();
        $entityManager->persist($trace);
        $entityManager->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $indexUrl = $adminUrlGenerator->setController(DeletedAccountTraceCrudController::class)->setAction('index')->generateUrl();

        $client->request(Request::METHOD_GET, $indexUrl);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $trace->emailHash);
        self::assertSelectorTextNotContains('body', 'trace-test@test.com');

        $entityManager->remove($trace);
        $entityManager->flush();
    }
}
