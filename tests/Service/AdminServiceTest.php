<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Repository\MailSettingsRepository;
use App\Repository\UserRepository;
use App\Service\AdminService;
use App\Service\ReminderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminServiceTest extends TestCase
{
    public function testCreateUserHashesPasswordAndPersistsUser(): void
    {
        $user = $this->createMock(User::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $hasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'plain')
            ->willReturn('hashed');
        $user->expects($this->once())
            ->method('setPassword')
            ->with('hashed');
        $em->expects($this->once())
            ->method('persist')
            ->with($user);
        $em->expects($this->once())
            ->method('flush');

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $service->createUser($user, 'plain');
    }

    public function testGetDashboardStatsReturnsArrayWithExpectedKeys(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $userRepo->method('countUsers')->willReturn(10);
        $userRepo->method('countAdmins')->willReturn(2);
        $kpiRepo->method('countAll')->willReturn(25);
        $userRepo->method('findCreatedBetween')->willReturn([]);
        $kpiRepo->method('countKpisByUser')->willReturn([]);

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $stats = $service->getDashboardStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('total_admins', $stats);
        $this->assertArrayHasKey('total_kpis', $stats);
        $this->assertSame(10, $stats['total_users']);
        $this->assertSame(2, $stats['total_admins']);
        $this->assertSame(25, $stats['total_kpis']);
    }
}
