<?php

namespace App\Tests\Repository;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Repository\KPIValueRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für KPIValueRepository.
 *
 * Wir verwenden ein kleines Fluent-QueryBuilder-Stub und mocken das EntityManager/Registry.
 */
class KPIValueRepositoryTest extends TestCase
{
    private function createFluentQueryBuilderStub(array $result = []): object
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($result);
        $query->method('getOneOrNullResult')->willReturn($result[0] ?? null);
        $query->method('getSingleScalarResult')->willReturn($result[0] ?? 0);

        $qb = new class($query) {
            private $q;
            public function __construct($q) { $this->q = $q; }
            public function andWhere() { return $this; }
            public function from() { return $this; }
            public function setParameter() { return $this; }
            public function orderBy() { return $this; }
            public function addOrderBy() { return $this; }
            public function join() { return $this; }
            public function addSelect() { return $this; }
            public function setMaxResults() { return $this; }
            public function select() { return $this; }
            public function groupBy() { return $this; }
            public function getQuery() { return $this->q; }
        };

        return $qb;
    }

    private function createRepositoryWithResult(array $result = []): KPIValueRepository
    {
        $qb = $this->createFluentQueryBuilderStub($result);

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $em->method('createQueryBuilder')->willReturn($qb);
    $em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return new KPIValueRepository($registry);
    }

    public function testFindByKPIReturnsArray(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $repo = $this->createRepositoryWithResult([$kpiValue]);

        $kpi = $this->createMock(KPI::class);
        $res = $repo->findByKPI($kpi);

        $this->assertIsArray($res);
        $this->assertSame([$kpiValue], $res);
    }

    public function testFindByKpiAndPeriodReturnsOneOrNull(): void
    {
        $kpiValue = $this->createMock(KPIValue::class);
        $repo = $this->createRepositoryWithResult([$kpiValue]);

        $kpi = $this->createMock(KPI::class);
        $period = new \DateTimeImmutable();
        $res = $repo->findByKpiAndPeriod($kpi, new \App\Domain\ValueObject\Period('2024-01'));

        $this->assertInstanceOf(KPIValue::class, $res);
    }

    public function testCountValuesByKpiReturnsArray(): void
    {
        // Simulate scalar result rows
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn([
            ['kpi_id' => '1', 'value_count' => '5'],
            ['kpi_id' => '2', 'value_count' => '3'],
        ]);

        $qb = new class($query) {
            private $q; public function __construct($q){$this->q=$q;} public function select(){return $this;} public function groupBy(){return $this;} public function from(){return $this;} public function getQuery(){return $this->q;}
        };

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $em->method('createQueryBuilder')->willReturn($qb);
    $em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repo = new KPIValueRepository($registry);

        $counts = $repo->countValuesByKpi();
        $this->assertIsArray($counts);
        $this->assertSame(5, $counts[1]);
        $this->assertSame(3, $counts[2]);
    }

    public function testCalculateAverageForKpiReturnsFloatOrNull(): void
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn('123.45');

    $qb = new class($query) { private $q; public function __construct($q){$this->q=$q;} public function select(){return $this;} public function andWhere(){return $this;} public function setParameter(){return $this;} public function from(){return $this;} public function getQuery(){return $this->q;}};

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $em->method('createQueryBuilder')->willReturn($qb);
    $em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repo = new KPIValueRepository($registry);
        $kpi = $this->createMock(KPI::class);

        $avg = $repo->calculateAverageForKpi($kpi);
        $this->assertIsFloat($avg);
        $this->assertEquals(123.45, $avg);
    }
}
