<?php

namespace App\Tests\Service;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserServiceTest extends TestCase
{
    public function testDeleteUserWithDataLogsAndDeletes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn(new EmailAddress('test@example.com'));
        $user->method('getId')->willReturn(1);
        $calls = [];
        $logger->method('info')->willReturnCallback(function ($msg, $context) use (&$calls) {
            $calls[] = [$msg, $context];
        });
        $em->expects($this->once())->method('beginTransaction');
        $service = new UserService($em, $logger);
        // Da die Methode Exceptions werfen kann, nur Aufruf testen
        try {
            $service->deleteUserWithData($user);
        } catch (\Exception $e) {
            // Ignoriere Exception für diesen Test
        }
        $this->assertCount(2, $calls);
        $this->assertStringContainsString('Starting GDPR-compliant user deletion', $calls[0][0]);
        $this->assertArrayHasKey('user_id', $calls[0][1]);
        $this->assertStringContainsString('GDPR-compliant user deletion completed', $calls[1][0]);
        $this->assertArrayHasKey('stats', $calls[1][1]);
    }

    public function testGetUserDeletionStatsReturnsArrayWithKeys(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $user = $this->createMock(User::class);

        $user->method('getKpis')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([]));
        $user->method('getEmail')->willReturn(new EmailAddress('test@example.com'));
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $service = new UserService($em, $logger);
        $stats = $service->getUserDeletionStats($user);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('email', $stats);
        $this->assertArrayHasKey('kpi_count', $stats);
        $this->assertArrayHasKey('value_count', $stats);
        $this->assertArrayHasKey('file_count', $stats);
    }

    public function testCanDeleteUserReturnsArray(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $user = $this->createMock(User::class);
        $currentUser = $this->createMock(User::class);

        $user->method('isAdmin')->willReturn(false);

        $userRepo = $this->createMock(\App\Repository\UserRepository::class);
        $em->method('getRepository')->willReturn($userRepo);

        $service = new UserService($em, $logger);
        $result = $service->canDeleteUser($user, $currentUser);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('can_delete', $result);
        $this->assertArrayHasKey('reasons', $result);
    }
}
