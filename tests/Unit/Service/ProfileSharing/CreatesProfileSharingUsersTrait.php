<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\User;
use Symfony\Component\Uid\Uuid;

trait CreatesProfileSharingUsersTrait
{
    private function createSearchableUser(string $nickname = 'Misou', string $shareCode = 'A7K2XM'): User
    {
        $user = new User();
        $user->id = Uuid::v4();
        $user->email = strtolower($nickname) . '@test.com';
        $user->nickname = $nickname;
        $user->isVerified = true;
        $user->isDiscoverable = true;
        $user->shareCode = $shareCode;

        return $user;
    }
}
