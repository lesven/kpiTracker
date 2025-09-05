<?php

namespace App\Tests\Repository;

use App\Domain\ValueObject\KpiInterval;
use App\Entity\KPI;
use App\Entity\User;
use App\Repository\KPIRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;

class KPIRepositoryTest extends TestCase
{
    private $registry;
    private $em;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->registry->method('getManagerForClass')->willReturn($this->em);
        $this->em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));
    }

    private function createRepository(): KPIRepository
    {
        return new KPIRepository($this->registry);
    }

    private function createFluentQueryBuilder($query = null)
    {
        return new class($query) {
            private $query;

            public function __construct($query)
            {
                $this->query = $query;
            }

            public function select()
            {
                return $this;
            }

            public function andWhere()
            {
                return $this;
            }

            public function setParameter()
            {
                return $this;
            }

            public function orderBy()
            {
                return $this;
            }

            public function addOrderBy()
            {
                return $this;
            }

            public function join()
            {
                return $this;
            }

            public function addSelect()
            {
                return $this;
            }

            public function groupBy()
            {
                return $this;
            }

            public function leftJoin()
            {
                return $this;
            }

            public function from()
            {
                return $this;
            }

            public function setMaxResults()
            {
                return $this;
            }

            public function getQuery()
            {
                return $this->query;
            }
        };
    }

    public function testFindByUserUsesSorts()
    {
        $user = new User();
        $expected = [new KPI()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $res = $repo->findByUser($user, 'name');

        $this->assertSame($expected, $res);
    }

    public function testFindByIntervalReturnsArray()
    {
        $interval = KpiInterval::MONTHLY;
        $expected = [new KPI()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $res = $repo->findByInterval($interval);

        $this->assertSame($expected, $res);
    }

    public function testFindByNameLikeReturnsArray()
    {
        $term = 'foo';
        $expected = [new KPI()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $res = $repo->findByNameLike($term);

        $this->assertSame($expected, $res);
    }

    public function testFindAllWithUserReturnsArray()
    {
        $expected = [new KPI()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $res = $repo->findAllWithUser();

        $this->assertSame($expected, $res);
    }

    public function testCountKpisByUserReturnsArray()
    {
        $rows = [[ 'user' => new User(), 'kpi_count' => 3 ]];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($rows);

        // countKpisByUser uses getEntityManager()->createQueryBuilder()
        $this->em->method('createQueryBuilder')->willReturn($this->createFluentQueryBuilder($query));
        $this->registry->method('getManagerForClass')->willReturn($this->em);

        $repo = $this->createRepository();
        $res = $repo->countKpisByUser();

        $this->assertSame($rows, $res);
    }

    public function testFindCreatedBetweenReturnsArray()
    {
        $start = new \DateTimeImmutable('-2 days');
        $end = new \DateTimeImmutable('now');
        $expected = [new KPI()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $res = $repo->findCreatedBetween($start, $end);

        $this->assertSame($expected, $res);
    }

    public function testFindDueForReminderReturnsArray()
    {
        $expected = [new KPI()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $res = $repo->findDueForReminder();

        $this->assertSame($expected, $res);
    }

    public function testCountAllReturnsInt()
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn('5');

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $count = $repo->countAll();

        $this->assertSame(5, $count);
    }

    public function testFindMostActiveReturnsArray()
    {
        $expected = [new KPI()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createRepository();
        $res = $repo->findMostActive(3);

        $this->assertSame($expected, $res);
    }
}
