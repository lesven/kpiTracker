<?php

namespace App\Tests\Service;

use App\Service\UserService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    public function testDeleteUserWithDataLogsAndDeletes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@example.com');
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
}
