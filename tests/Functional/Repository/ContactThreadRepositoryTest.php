<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\DataFixtures\UserFixtures;
use App\Entity\ContactThread;
use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\ContactThreadRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ContactThreadTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ContactThreadRepositoryTest extends KernelTestCase
{
    public function testCountAwaitingAdminReplyExcludesAnsweredClosedAndBroadcastThreads(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $owner = $userRepository->findOneBy([
            'email' => 'user-fixture-0@test.com',
        ]);

        if (! $owner instanceof User) {
            throw new \LogicException('Fixture user "user-fixture-0@test.com" not found.');
        }

        $before = $contactThreadRepository->countAwaitingAdminReply();

        $awaiting = ContactThreadTestHelper::createThread($entityManager, $owner, status: ContactThreadStatusEnum::AWAITING_ADMIN_REPLY);
        $answered = ContactThreadTestHelper::createThread($entityManager, $owner, status: ContactThreadStatusEnum::ANSWERED_BY_ADMIN);
        $closed = ContactThreadTestHelper::createThread($entityManager, $owner, status: ContactThreadStatusEnum::CLOSED);

        self::assertSame($before + 1, $contactThreadRepository->countAwaitingAdminReply());

        $entityManager->remove($awaiting);
        $entityManager->remove($answered);
        $entityManager->remove($closed);
        $entityManager->flush();
    }

    /**
     * `findByOwnerOrderedByActivity` fait un `leftJoin` + `addSelect` sur `messages` (one-to-many)
     * pour pré-charger les messages de chaque fil. Un `setMaxResults` manuel sur cette requête
     * couperait sur les lignes SQL jointes, pas sur les fils (règle documentée dans CLAUDE.md).
     * KnpPaginator doit ici prendre en charge lui-même le comptage/la pagination
     * (`fetchJoinCollection: true`) sans perdre ni dupliquer de fil.
     */
    public function testFindByOwnerOrderedByActivityPaginatesThreadsWithMultipleMessagesWithoutLossOrDuplication(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = static::getContainer()->get(ContactThreadRepository::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var PaginatorInterface $paginator */
        $paginator = static::getContainer()->get(PaginatorInterface::class);

        $owner = $userRepository->findOneBy([
            'email' => 'user-fixture-4@test.com',
        ]);
        $admin = $userRepository->findOneBy([
            'email' => UserFixtures::USER_ADMIN,
        ]);

        if (! $owner instanceof User || ! $admin instanceof User) {
            throw new \LogicException('Fixture users not found.');
        }

        $baselineIds = $this->threadIds($contactThreadRepository, $owner);
        $baselineCount = count($baselineIds);

        // Chaque fil créé a 2 messages (1 initial + 1 réponse admin) : c'est ce qui produit
        // le fanout SQL sur le leftJoin si fetchJoinCollection n'était pas géré correctement.
        $threadOne = ContactThreadTestHelper::createThread($entityManager, $owner, 'Fil pagination Alpha');
        ContactThreadTestHelper::addAdminMessage($entityManager, $threadOne, $admin);
        $threadTwo = ContactThreadTestHelper::createThread($entityManager, $owner, 'Fil pagination Beta');
        ContactThreadTestHelper::addAdminMessage($entityManager, $threadTwo, $admin);
        $threadThree = ContactThreadTestHelper::createThread($entityManager, $owner, 'Fil pagination Gamma');
        ContactThreadTestHelper::addAdminMessage($entityManager, $threadThree, $admin);

        $limit = $baselineCount + 2;

        /** @var PaginationInterface<int, ContactThread> $firstPage */
        $firstPage = $paginator->paginate(
            $contactThreadRepository->findByOwnerOrderedByActivity($owner),
            1,
            $limit,
        );

        self::assertSame($baselineCount + 3, $firstPage->getTotalItemCount());

        $firstPageIds = $this->paginationThreadIds($firstPage);
        self::assertCount($limit, $firstPageIds);
        self::assertCount($limit, array_unique($firstPageIds), 'Un fil ne doit apparaître qu\'une seule fois malgré le JOIN sur ses messages.');

        /** @var PaginationInterface<int, ContactThread> $secondPage */
        $secondPage = $paginator->paginate(
            $contactThreadRepository->findByOwnerOrderedByActivity($owner),
            2,
            $limit,
        );

        $secondPageIds = $this->paginationThreadIds($secondPage);
        self::assertCount(1, $secondPageIds, 'Le dernier fil doit apparaître sur la page suivante, ni perdu ni dupliqué.');
        self::assertCount(0, array_intersect($firstPageIds, $secondPageIds), 'Un même fil ne doit jamais apparaître sur deux pages différentes.');

        $entityManager->remove($threadOne);
        $entityManager->remove($threadTwo);
        $entityManager->remove($threadThree);
        $entityManager->flush();
    }

    /**
     * @return list<string>
     */
    private function threadIds(ContactThreadRepository $contactThreadRepository, User $owner): array
    {
        $threads = $contactThreadRepository->findByOwnerOrderedByActivity($owner)->getQuery()->getResult();
        if (! is_array($threads)) {
            throw new \LogicException('Expected an array of ContactThread.');
        }

        return array_values(array_map(static function (mixed $thread): string {
            if (! $thread instanceof ContactThread || ! $thread->id instanceof Uuid) {
                throw new \LogicException('Expected a persisted ContactThread.');
            }

            return $thread->id->toRfc4122();
        }, $threads));
    }

    /**
     * @param PaginationInterface<int, ContactThread> $pagination
     * @return list<string>
     */
    private function paginationThreadIds(PaginationInterface $pagination): array
    {
        return array_values(array_map(static function (ContactThread $thread): string {
            if (! $thread->id instanceof Uuid) {
                throw new \LogicException('Expected a persisted ContactThread.');
            }

            return $thread->id->toRfc4122();
        }, iterator_to_array($pagination)));
    }
}
