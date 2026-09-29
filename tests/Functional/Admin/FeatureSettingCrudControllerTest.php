<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\FeatureSettingCrudController;
use App\Repository\FeatureSettingRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class FeatureSettingCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testIndexShowsToggleReflectingCurrentValue(): void
    {
        $client = $this->login(self::ADMIN);
        $this->setEnabled(true);

        $crawler = $client->request(Request::METHOD_GET, $this->indexUrl());

        self::assertResponseIsSuccessful();
        self::assertNotNull($crawler->filter('.form-check-input')->attr('checked'));
    }

    public function testToggleSwitchEnablesWorkoutPhotoUpload(): void
    {
        $client = $this->login(self::ADMIN);
        $this->setEnabled(false);

        $crawler = $client->request(Request::METHOD_GET, $this->indexUrl());
        $toggleUrl = $crawler->filter('.form-check-input')->attr('data-toggle-url');

        if (null === $toggleUrl) {
            throw new \LogicException('data-toggle-url attribute not found on the switch input.');
        }

        $client->request(
            Request::METHOD_PATCH,
            $toggleUrl . '&newValue=true',
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertTrue($this->repository()->getSingleton()->workoutPhotoUploadEnabled);
    }

    public function testWorkoutPhotoUploadIsDisabledByDefault(): void
    {
        $this->login(self::ADMIN);

        self::assertFalse($this->repository()->getSingleton()->workoutPhotoUploadEnabled);
    }

    public function testNewIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $newUrl = $adminUrlGenerator
            ->setController(FeatureSettingCrudController::class)
            ->setAction('new')
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $newUrl);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDetailIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);
        $setting = $this->repository()->getSingleton();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $detailUrl = $adminUrlGenerator
            ->setController(FeatureSettingCrudController::class)
            ->setAction('detail')
            ->setEntityId($setting->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $detailUrl);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);
        $setting = $this->repository()->getSingleton();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $deleteUrl = $adminUrlGenerator
            ->setController(FeatureSettingCrudController::class)
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

        return $adminUrlGenerator->setController(FeatureSettingCrudController::class)->setAction('index')->generateUrl();
    }

    private function repository(): FeatureSettingRepository
    {
        /** @var FeatureSettingRepository $repository */
        $repository = static::getContainer()->get(FeatureSettingRepository::class);

        return $repository;
    }

    private function setEnabled(bool $enabled): void
    {
        $setting = $this->repository()->getSingleton();
        $setting->workoutPhotoUploadEnabled = $enabled;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
    }
}
