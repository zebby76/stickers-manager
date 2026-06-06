<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserCheckerTest extends TestCase
{
    public function testApprovedActiveUserPasses(): void
    {
        $user = (new User())->setApproved(true)->setActive(true);
        (new UserChecker())->checkPreAuth($user);
        $this->expectNotToPerformAssertions();
    }

    public function testPendingUserIsBlocked(): void
    {
        $user = (new User())->setApproved(false)->setActive(true);
        $this->expectException(CustomUserMessageAccountStatusException::class);
        (new UserChecker())->checkPreAuth($user);
    }

    public function testBannedUserIsBlocked(): void
    {
        $user = (new User())->setApproved(true)->setActive(false);
        $this->expectException(CustomUserMessageAccountStatusException::class);
        (new UserChecker())->checkPreAuth($user);
    }
}
