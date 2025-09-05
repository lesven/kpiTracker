<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    private function createEMWithQueryReturning($result)
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($result);
        $query->method('getOneOrNullResult')->willReturn($result[0] ?? null);
        $query->method('getSingleScalarResult')->willReturn($result[0] ?? 0);

        $qb = new class($query) {
            private $q; public function __construct($q){$this->q=$q;} public function andWhere(){return $this;} public function setParameter(){return $this;} public function orderBy(){return $this;} public function setMaxResults(){return $this;} public function select(){return $this;} public function getQuery(){return $this->q;} public function join(){return $this;} public function addSelect(){return $this;} public function from(){return $this;} public function groupBy(){return $this;} public function addOrderBy(){return $this;}
        };

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return [$registry, $em];
    }

    public function testRepositoryConstruction(): void
    {
        [$registry,] = $this->createEMWithQueryReturning([]);
        $repo = new UserRepository($registry);
        $this->assertInstanceOf(UserRepository::class, $repo);
    }

    public function testFindByEmailReturnsUserOrNull(): void
    {
        $user = $this->createMock(User::class);
        [$registry,] = $this->createEMWithQueryReturning([$user]);
        $repo = new UserRepository($registry);

        $res = $repo->findByEmail('user@example.com');
        $this->assertInstanceOf(User::class, $res);
    }

    public function testCountUsersAndAdmins(): void
    {
        [$registry,] = $this->createEMWithQueryReturning([1]);
        $repo = new UserRepository($registry);

        $total = $repo->countUsers();
        $this->assertIsInt($total);

        $admins = $repo->countAdmins();
        $this->assertIsInt($admins);
    }

    public function testFindAdminsAndRegularUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);
        [$registry,] = $this->createEMWithQueryReturning([$user1, $user2]);
        $repo = new UserRepository($registry);

        $admins = $repo->findAdmins();
        $this->assertIsArray($admins);

        $regulars = $repo->findRegularUsers();
        $this->assertIsArray($regulars);
    }

    public function testFindCreatedBetweenAndLike(): void
    {
        $user = $this->createMock(User::class);
        [$registry,] = $this->createEMWithQueryReturning([$user]);
        $repo = new UserRepository($registry);

        $between = $repo->findCreatedBetween(new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('+1 day'));
        $this->assertIsArray($between);

        $like = $repo->findByEmailLike('example');
        $this->assertIsArray($like);
    }

    public function testUpgradePasswordPersistsAndFlushes(): void
    {
        [$registry, $em] = $this->createEMWithQueryReturning([]);
        $repo = new UserRepository($registry);

        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()->getMock();
        $user->expects($this->once())->method('setPassword')->with('new-hash');

        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $repo->upgradePassword($user, 'new-hash');
    }
}
