<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\ExerciseCrudController;
use App\Entity\Exercise;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ExerciseCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testIndexListsCustomExerciseButExcludesPublicOnes(): void
    {
        $client = $this->login(self::ADMIN);
        $owner = $this->createTestUser('exercise-crud-owner@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $custom = new Exercise();
        $custom->name = 'Développé couché personnalisé';
        $custom->owner = $owner;
        $entityManager->persist($custom);

        $public = new Exercise();
        $public->name = 'Squat public';
        $public->isPublic = true;
        $entityManager->persist($public);

        $entityManager->flush();

        $client->request(Request::METHOD_GET, $this->indexUrl());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Développé couché personnalisé');
        self::assertSelectorTextNotContains('body', 'Squat public');

        $entityManager->remove($custom);
        $entityManager->remove($public);
        $entityManager->flush();
        $this->deleteTestUser($owner);
    }

    public function testOwnerFilterNarrowsIndexToMatchingOwner(): void
    {
        $client = $this->login(self::ADMIN);
        $owner = $this->createTestUser('exercise-crud-filter@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->name = 'Traction lestée';
        $exercise->owner = $owner;
        $entityManager->persist($exercise);
        $entityManager->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
            ->setController(ExerciseCrudController::class)
            ->setAction('index')
            ->set('filters[owner][comparison]', '=')
            ->set('filters[owner][value]', (string) $owner->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'Traction lestée');

        $entityManager->remove($exercise);
        $entityManager->flush();
        $this->deleteTestUser($owner);
    }

    public function testEditIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);
        $owner = $this->createTestUser('exercise-crud-edit@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->name = 'Exercice non éditable';
        $exercise->owner = $owner;
        $entityManager->persist($exercise);
        $entityManager->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $editUrl = $adminUrlGenerator
            ->setController(ExerciseCrudController::class)
            ->setAction('edit')
            ->setEntityId($exercise->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $editUrl);

        self::assertResponseStatusCodeSame(403);

        $entityManager->remove($exercise);
        $entityManager->flush();
        $this->deleteTestUser($owner);
    }

    public function testDeleteRemovesExercise(): void
    {
        $client = $this->login(self::ADMIN);
        $owner = $this->createTestUser('exercise-crud-delete@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->name = 'Exercice à supprimer';
        $exercise->owner = $owner;
        $entityManager->persist($exercise);
        $entityManager->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $detailUrl = $adminUrlGenerator
            ->setController(ExerciseCrudController::class)
            ->setAction('detail')
            ->setEntityId($exercise->id)
            ->generateUrl()
        ;
        $deleteUrl = $adminUrlGenerator
            ->setController(ExerciseCrudController::class)
            ->setAction('delete')
            ->setEntityId($exercise->id)
            ->generateUrl()
        ;

        $token = $this->csrfTokenFromPage($client, $detailUrl, 'input[name="token"]', 'value');

        $client->request(Request::METHOD_POST, $deleteUrl, [
            'token' => $token,
        ]);

        self::assertResponseRedirects();
        self::assertNull($entityManager->getRepository(Exercise::class)->find($exercise->id));

        $this->deleteTestUser($owner);
    }

    private function indexUrl(): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator->setController(ExerciseCrudController::class)->setAction('index')->generateUrl();
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

        $entityManager->remove($reloaded);
        $entityManager->flush();
    }
}
