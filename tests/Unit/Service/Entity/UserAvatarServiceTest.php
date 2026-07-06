<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\User;
use App\Service\Entity\UserAvatarService;
use App\Service\Utils\ImageUploadService;
use App\Tests\Functional\Helper\ImageTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class UserAvatarServiceTest extends TestCase
{
    public function testReplaceThrowsLogicExceptionWhenUserHasNoId(): void
    {
        $user = new User();
        $imageUploadService = $this->createStub(ImageUploadService::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $service = new UserAvatarService($imageUploadService, $em);

        $this->expectException(\LogicException::class);

        $service->replace($user, ImageTestHelper::createFakeImage('avatar.jpg', 'image/jpeg'));
    }
}
