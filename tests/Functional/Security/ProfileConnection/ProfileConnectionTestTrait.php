<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\ProfileConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Outils communs aux tests fonctionnels de « Mes connexions » : création de connexions et lecture
 * des jetons CSRF dans la page déjà rendue (jamais régénérés hors requête).
 */
trait ProfileConnectionTestTrait
{
    public const string ACTOR = 'user-fixture-11-workout@test.com';

    public const string OTHER = 'user-fixture-26-workout@test.com';

    public const string THIRD = 'user-fixture-51-workout@test.com';

    public const string FOURTH = 'user-fixture-0@test.com';

    public const string LIST_URL = '/fr/connexions';

    private function createConnection(User $requester, User $addressee, ProfileConnectionStatusEnum $status = ProfileConnectionStatusEnum::PENDING): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = $status;

        $this->entityManager()->persist($connection);
        $this->entityManager()->flush();

        return $connection;
    }

    private function findConnection(ProfileConnection $connection): ?ProfileConnection
    {
        $this->entityManager()->clear();

        return $this->entityManager()->find(ProfileConnection::class, $connection->id);
    }

    private function findBetween(User $first, User $second): ?ProfileConnection
    {
        $this->entityManager()->clear();

        /** @var ProfileConnectionRepository $repository */
        $repository = static::getContainer()->get(ProfileConnectionRepository::class);

        return $repository->findBetween($first, $second);
    }

    private function makeDiscoverable(User $user, string $shareCode): void
    {
        $user->isDiscoverable = true;
        $user->shareCode = $shareCode;
        $this->entityManager()->flush();
    }

    /**
     * Jeton CSRF du formulaire dont l'action se termine par `$actionSuffix`, lu dans la page rendue.
     */
    private function tokenOfForm(Crawler $crawler, string $actionSuffix): string
    {
        $token = $crawler->filter(\sprintf('form[action$="%s"] input[name="_token"]', $actionSuffix))->first()->attr('value');

        return $token ?? throw new \LogicException(\sprintf('No CSRF token found for the form "%s".', $actionSuffix));
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
