<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ProfileSharing\ShareCodeAssigner;
use App\Service\ProfileSharing\ShareCodeGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class ShareCodeAssignerTest extends TestCase
{
    public function testAssignsGeneratedCodeWhenItIsAvailable(): void
    {
        $generator = $this->createStub(ShareCodeGeneratorInterface::class);
        $generator->method('generate')->willReturn('A7K2XM');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('existsByShareCode')->willReturn(false);

        $user = new User();

        new ShareCodeAssigner($generator, $userRepository)->assign($user);

        self::assertSame('A7K2XM', $user->shareCode);
    }

    public function testGeneratesAnotherCodeWhenTheFirstOneIsAlreadyTaken(): void
    {
        $generator = $this->createStub(ShareCodeGeneratorInterface::class);
        $generator->method('generate')->willReturnOnConsecutiveCalls('TAKEN2', 'FREE23');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('existsByShareCode')->willReturnCallback(
            static fn (string $code): bool => 'TAKEN2' === $code,
        );

        $user = new User();

        new ShareCodeAssigner($generator, $userRepository)->assign($user);

        self::assertSame('FREE23', $user->shareCode);
    }

    public function testThrowsWhenNoAvailableCodeIsFoundWithinMaxAttempts(): void
    {
        $generator = $this->createMock(ShareCodeGeneratorInterface::class);
        $generator->expects(self::exactly(ShareCodeAssigner::MAX_ATTEMPTS))
            ->method('generate')
            ->willReturn('TAKEN2');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('existsByShareCode')->willReturn(true);

        $user = new User();

        $this->expectException(\LogicException::class);

        try {
            new ShareCodeAssigner($generator, $userRepository)->assign($user);
        } finally {
            self::assertNull($user->shareCode);
        }
    }

    public function testReplacesTheExistingCodeWhenAssigningAgain(): void
    {
        $generator = $this->createStub(ShareCodeGeneratorInterface::class);
        $generator->method('generate')->willReturn('NEW234');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('existsByShareCode')->willReturn(false);

        $user = new User();
        $user->shareCode = 'OLD234';

        new ShareCodeAssigner($generator, $userRepository)->assign($user);

        self::assertSame('NEW234', $user->shareCode);
    }
}
