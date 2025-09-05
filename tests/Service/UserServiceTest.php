<?php

namespace App\Tests\Service;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\KPI;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private UserRepository $userRepository;
    private UserService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->service = new UserService($this->entityManager, $this->logger);
    }

    public function testDeleteUserWithDataLogsAndDeletes(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn(new EmailAddress('test@example.com'));
        $user->method('getId')->willReturn(1);
        $user->method('getKpis')->willReturn(new ArrayCollection([]));
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $calls = [];
        $this->logger->method('info')->willReturnCallback(function ($msg, $context) use (&$calls) {
            $calls[] = [$msg, $context];
        });

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('remove')->with($user);
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        $this->service->deleteUserWithData($user);

        $this->assertCount(2, $calls);
        $this->assertStringContainsString('Starting GDPR-compliant user deletion', $calls[0][0]);
        $this->assertArrayHasKey('user_id', $calls[0][1]);
        $this->assertStringContainsString('GDPR-compliant user deletion completed', $calls[1][0]);
        $this->assertArrayHasKey('stats', $calls[1][1]);
    }

    public function testDeleteUserWithDataRollsBackOnException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn(new EmailAddress('test@example.com'));
        $user->method('getId')->willReturn(1);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('remove')->willThrowException(new \Exception('Database error'));
        $this->entityManager->expects($this->once())->method('rollback');
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with('GDPR user deletion failed', $this->isType('array'));

        $this->expectException(\Exception::class);
        $this->service->deleteUserWithData($user);
    }

    public function testGetUserDeletionStatsReturnsArrayWithKeys(): void
    {
        $kpi = new KPI();
        $kpis = new ArrayCollection([$kpi]);

        $user = $this->createMock(User::class);
        $user->method('getKpis')->willReturn($kpis);
        $user->method('getEmail')->willReturn(new EmailAddress('test@example.com'));
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $stats = $this->service->getUserDeletionStats($user);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('email', $stats);
        $this->assertArrayHasKey('kpi_count', $stats);
        $this->assertArrayHasKey('value_count', $stats);
        $this->assertArrayHasKey('file_count', $stats);
        $this->assertSame('test@example.com', $stats['email']);
        $this->assertSame(1, $stats['kpi_count']);
    }

    public function testCanDeleteUserReturnsArray(): void
    {
        $user = $this->createMock(User::class);
        $currentUser = $this->createMock(User::class);

        $user->method('isAdmin')->willReturn(false);
        $currentUser->method('getId')->willReturn(2);
        $user->method('getId')->willReturn(1);

        $this->userRepository->method('count')->willReturn(5); // Mehr als 1 Admin

        $result = $this->service->canDeleteUser($user, $currentUser);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('can_delete', $result);
        $this->assertArrayHasKey('reasons', $result);
    }

    public function testCanDeleteUserPreventsLastAdminDeletion(): void
    {
        $user = $this->createMock(User::class);
        $currentUser = $this->createMock(User::class);

        $user->method('isAdmin')->willReturn(true);
        $this->userRepository->method('count')->willReturn(1); // Nur 1 Admin

        $result = $this->service->canDeleteUser($user, $currentUser);

        $this->assertFalse($result['can_delete']);
        $this->assertContains('Der letzte Administrator kann nicht gelöscht werden.', $result['reasons']);
    }

    public function testCanDeleteUserPreventsSelfDeletion(): void
    {
        // Verwende echte User-Objekte statt Mocks für Objektvergleich
        $user = new User();
        $user->setEmail(new EmailAddress('test@example.com'));
        $currentUser = $user; // Gleiche Objektreferenz = Selbstlöschung

        $this->userRepository->method('count')->willReturn(5);

        $result = $this->service->canDeleteUser($user, $currentUser);

        $this->assertFalse($result['can_delete']);
        $this->assertContains('Ein Benutzer kann sich nicht selbst löschen.', $result['reasons']);
    }

    public function testCanDeleteUserAllowsDeletionWhenValid(): void
    {
        $user = $this->createMock(User::class);
        $currentUser = $this->createMock(User::class);

        $user->method('isAdmin')->willReturn(false);
        $user->method('getId')->willReturn(1);
        $currentUser->method('getId')->willReturn(2);

        $this->userRepository->method('count')->willReturn(5);

        $result = $this->service->canDeleteUser($user, $currentUser);

        $this->assertTrue($result['can_delete']);
        $this->assertEmpty($result['reasons']);
    }

    public function testGetUserDeletionStatsWithKpisAndValues(): void
    {
        $kpi1 = new KPI();
        $kpi2 = new KPI();
        $kpis = new ArrayCollection([$kpi1, $kpi2]);

        $user = $this->createMock(User::class);
        $user->method('getKpis')->willReturn($kpis);
        $user->method('getEmail')->willReturn(new EmailAddress('user@example.com'));
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2023-01-01'));

        $stats = $this->service->getUserDeletionStats($user);

        $this->assertSame('user@example.com', $stats['email']);
        $this->assertSame(2, $stats['kpi_count']);
        $this->assertIsInt($stats['value_count']);
        $this->assertIsInt($stats['file_count']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $stats['created_at']);
    }

    public function testDeleteUserWithDataDeletesKpisAndValues(): void
    {
        $kpi = new KPI();
        $kpis = new ArrayCollection([$kpi]);

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn(new EmailAddress('test@example.com'));
        $user->method('getId')->willReturn(1);
        $user->method('getKpis')->willReturn($kpis);
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->exactly(2))->method('remove'); // KPI + User
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        $this->service->deleteUserWithData($user);
    }
}
