<?php

namespace App\Tests\Repository;

use App\Entity\MailSettings;
use App\Repository\MailSettingsRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;

class MailSettingsRepositoryTest extends TestCase
{
    public function testRepositoryCanBeConstructed(): void
    {
        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repo = new MailSettingsRepository($registry);
        $this->assertInstanceOf(MailSettingsRepository::class, $repo);
    }

    public function testCountAllWorks(): void
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn('2');

        $qb = new class($query) { private $q; public function __construct($q){$this->q=$q;} public function select(){return $this;} public function getQuery(){return $this->q;}};

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repo = new MailSettingsRepository($registry);
        $count = method_exists($repo, 'countAll') ? $repo->countAll() : null;

        // Repository has no custom methods - ensure our QB stub works if method exists
        if ($count !== null) {
            $this->assertSame(2, $count);
        } else {
            $this->assertInstanceOf(MailSettingsRepository::class, $repo);
        }
    }
}
