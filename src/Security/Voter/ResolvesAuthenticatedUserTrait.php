<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

trait ResolvesAuthenticatedUserTrait
{
    private function resolveUser(TokenInterface $token): ?User
    {
        $user = $token->getUser();

        return $user instanceof User ? $user : null;
    }
}
