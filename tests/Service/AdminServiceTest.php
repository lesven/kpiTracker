<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AdminService;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Repository\MailSettingsRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\ReminderService;

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
}
