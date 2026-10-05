<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ProfileSharing\InvitationResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvitationResolverTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    public function testResolvesTheSearchableOwnerOfTheCode(): void
    {
        $inviter = $this->createSearchableUser();

        self::assertSame($inviter, $this->createResolver($inviter)->resolveInviter('A7K2XM'));
    }

    public function testIgnoresTheCaseOfTheCode(): void
    {
        $inviter = $this->createSearchableUser();

        self::assertSame($inviter, $this->createResolver($inviter)->resolveInviter('a7k2xm'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedCodeProvider(): iterable
    {
        yield 'no code' => [null];
        yield 'empty code' => [''];
        yield 'too short' => ['A7K2X'];
        yield 'too long' => ['A7K2XMM'];
        yield 'not a string' => [['A7K2XM']];
    }

    #[DataProvider('malformedCodeProvider')]
    public function testResolvesNobodyFromAMalformedCode(mixed $code): void
    {
        self::assertNull($this->createResolver($this->createSearchableUser())->resolveInviter($code));
    }

    public function testResolvesNobodyFromAnUnknownCode(): void
    {
        self::assertNull($this->createResolver(null)->resolveInviter('A7K2XM'));
    }

    public function testResolvesNobodyWhenTheOwnerNoLongerSharesTheirProfile(): void
    {
        $inviter = $this->createSearchableUser();
        $inviter->isDiscoverable = false;

        self::assertNull($this->createResolver($inviter)->resolveInviter('A7K2XM'));
    }

    private function createResolver(?User $codeOwner): InvitationResolver
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneByShareCode')->willReturnCallback(
            static fn (string $code): ?User => 'A7K2XM' === $code ? $codeOwner : null,
        );

        return new InvitationResolver($userRepository);
    }
}
