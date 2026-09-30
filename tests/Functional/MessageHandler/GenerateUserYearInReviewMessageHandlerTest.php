<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\YearInReview;
use App\Message\GenerateUserYearInReviewMessage;
use App\MessageHandler\GenerateUserYearInReviewMessageHandler;
use App\Tests\Functional\Helper\YearInReviewTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class GenerateUserYearInReviewMessageHandlerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testReviewIsFrozenOncePublished(): void
    {
        self::mockTime('2026-12-16 00:05:00 Europe/Paris');
        self::bootKernel();
        $em = $this->entityManager();
        $user = YearInReviewTestHelper::createVerifiedUser($em);

        $this->handler()(new GenerateUserYearInReviewMessage((string) $user->id, 2026));

        self::assertCount(1, $em->getRepository(YearInReview::class)->findBy([
            'owner' => $user,
            'year' => 2026,
        ]));
    }

    public function testNothingIsFrozenBeforePublication(): void
    {
        self::mockTime('2026-11-10 12:00:00 Europe/Paris');
        self::bootKernel();
        $em = $this->entityManager();
        $user = YearInReviewTestHelper::createVerifiedUser($em);

        $this->handler()(new GenerateUserYearInReviewMessage((string) $user->id, 2026));

        self::assertCount(0, $em->getRepository(YearInReview::class)->findBy([
            'owner' => $user,
        ]));
    }

    public function testDeletedUserIsSkipped(): void
    {
        self::mockTime('2026-12-16 00:05:00 Europe/Paris');
        self::bootKernel();

        $this->handler()(new GenerateUserYearInReviewMessage((string) Uuid::v7(), 2026));

        $this->expectNotToPerformAssertions();
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function handler(): GenerateUserYearInReviewMessageHandler
    {
        /** @var GenerateUserYearInReviewMessageHandler */
        return static::getContainer()->get(GenerateUserYearInReviewMessageHandler::class);
    }
}
