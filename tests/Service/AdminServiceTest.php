<?php

namespace App\Tests\Service;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\MailSettings;
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

    public function testUpdateUserWithPassword(): void
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
            ->with($user, 'newPassword')
            ->willReturn('hashedNewPassword');
        
        $user->expects($this->once())
            ->method('setPassword')
            ->with('hashedNewPassword');
        
        $em->expects($this->once())
            ->method('flush');

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $service->updateUser($user, 'newPassword');
    }

    public function testUpdateUserWithoutPassword(): void
    {
        $user = $this->createMock(User::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $hasher->expects($this->never())
            ->method('hashPassword');
        
        $user->expects($this->never())
            ->method('setPassword');
        
        $em->expects($this->once())
            ->method('flush');

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $service->updateUser($user, null);
    }

    public function testGetKpisWithLastValues(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $kpi1 = $this->createMock(KPI::class);
        $kpi1->method('getId')->willReturn(1);
        $kpi2 = $this->createMock(KPI::class);
        $kpi2->method('getId')->willReturn(2);
        
        $kpis = [$kpi1, $kpi2];
        $value1 = $this->createMock(KPIValue::class);
        $value2 = $this->createMock(KPIValue::class);

        $kpiRepo->expects($this->once())
            ->method('findAllWithUser')
            ->willReturn($kpis);
        
        $kpiValueRepo->expects($this->exactly(2))
            ->method('findLatestValueForKpi')
            ->willReturnMap([
                [$kpi1, $value1],
                [$kpi2, $value2]
            ]);

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        [$resultKpis, $lastValues] = $service->getKpisWithLastValues();

        $this->assertSame($kpis, $resultKpis);
        $this->assertArrayHasKey(1, $lastValues);
        $this->assertArrayHasKey(2, $lastValues);
        $this->assertSame($value1, $lastValues[1]);
        $this->assertSame($value2, $lastValues[2]);
    }

    public function testSaveMailSettings(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $settings = $this->createMock(MailSettings::class);
        
        $em->expects($this->once())
            ->method('persist')
            ->with($settings);
        
        $em->expects($this->once())
            ->method('flush');

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $service->saveMailSettings($settings);
    }

    public function testSendTestReminder(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $email = 'test@example.com';
        
        $reminder->expects($this->once())
            ->method('sendTestEmail')
            ->with($email)
            ->willReturn(true);

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $result = $service->sendTestReminder($email);

        $this->assertTrue($result);
    }

    public function testSendAllReminders(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $expectedStats = ['sent' => 5, 'failed' => 1];
        
        $reminder->expects($this->once())
            ->method('sendDueReminders')
            ->willReturn($expectedStats);

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $result = $service->sendAllReminders();

        $this->assertSame($expectedStats, $result);
    }

    public function testGetMailSettingsReturnsExisting(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);

        $settings = $this->createMock(MailSettings::class);
        
        $mailRepo->expects($this->once())
            ->method('findOneBy')
            ->with([])
            ->willReturn($settings);

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $result = $service->getMailSettings();

        $this->assertSame($settings, $result);
    }

    public function testGetMailSettingsReturnsNewWhenNoneExists(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $kpiRepo = $this->createMock(KPIRepository::class);
        $kpiValueRepo = $this->createMock(KPIValueRepository::class);
        $mailRepo = $this->createMock(MailSettingsRepository::class);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $reminder = $this->createMock(ReminderService::class);
        
        $mailRepo->expects($this->once())
            ->method('findOneBy')
            ->with([])
            ->willReturn(null);

        $service = new AdminService($em, $userRepo, $kpiRepo, $kpiValueRepo, $mailRepo, $hasher, $reminder);
        $result = $service->getMailSettings();

        $this->assertInstanceOf(MailSettings::class, $result);
    }
}
