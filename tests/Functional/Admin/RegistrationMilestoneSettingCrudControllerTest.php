<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\RegistrationMilestoneSettingCrudController;
use App\Entity\RegistrationMilestoneSetting;
use App\Repository\RegistrationMilestoneSettingRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class RegistrationMilestoneSettingCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testIndexShowsCurrentStep(): void
    {
        $client = $this->login(self::ADMIN);
        $step = $this->repository()->getSingleton()->step;

        $client->request(Request::METHOD_GET, $this->indexUrl());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', (string) $step);
    }

    public function testEditUpdatesStep(): void
    {
        $client = $this->login(self::ADMIN);
        $setting = $this->repository()->getSingleton();
        $originalStep = $setting->step;

        $crawler = $client->request(Request::METHOD_GET, $this->editUrl($setting));
        $form = $crawler->selectButton('Save changes')->form([
            'RegistrationMilestoneSetting[step]' => '750',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseRedirects();
        self::assertSame(750, $this->repository()->getSingleton()->step);

        $this->restoreStep($originalStep);
    }

    public function testNewIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $newUrl = $adminUrlGenerator
            ->setController(RegistrationMilestoneSettingCrudController::class)
            ->setAction('new')
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $newUrl);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);
        $setting = $this->repository()->getSingleton();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $deleteUrl = $adminUrlGenerator
            ->setController(RegistrationMilestoneSettingCrudController::class)
            ->setAction('delete')
            ->setEntityId($setting->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_POST, $deleteUrl, [
            'token' => 'irrelevant-since-action-is-disabled',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function indexUrl(): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator->setController(RegistrationMilestoneSettingCrudController::class)->setAction('index')->generateUrl();
    }

    private function editUrl(RegistrationMilestoneSetting $setting): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator
            ->setController(RegistrationMilestoneSettingCrudController::class)
            ->setAction('edit')
            ->setEntityId($setting->id)
            ->generateUrl()
        ;
    }

    private function repository(): RegistrationMilestoneSettingRepository
    {
        /** @var RegistrationMilestoneSettingRepository $repository */
        $repository = static::getContainer()->get(RegistrationMilestoneSettingRepository::class);

        return $repository;
    }

    private function restoreStep(int $originalStep): void
    {
        $setting = $this->repository()->getSingleton();
        $setting->step = $originalStep;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
    }
}
