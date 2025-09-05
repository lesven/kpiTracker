<?php

namespace App\Tests\Repository;

use App\Entity\KPIFile;
use App\Entity\KPIValue;
use App\Repository\KPIFileRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class KPIFileRepositoryTest extends TestCase
{
    private $registry;
    private $em;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->registry->method('getManagerForClass')->willReturn($this->em);
        // Doctrine's ServiceEntityRepository expects the EntityManager to provide
        // ClassMetadata; mock it to avoid TypeError when instantiating the repo.
        $this->em->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));
    }

    private function createRepository(): KPIFileRepository
    {
        // Return a real repository instance; tests will stub the EntityManager
        return new KPIFileRepository($this->registry);
    }

    /**
     * Create a tiny fluent stub that mimics the parts of Doctrine\QueryBuilder used
     * by the repository and returns the provided AbstractQuery from getQuery().
     */
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

            public function getQuery()
            {
                return $this->query;
            }
        };
    }

    public function testFindByKpiValueReturnsArray()
    {
        $kpiValue = new KPIValue();

        $expected = [new KPIFile(), new KPIFile()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();

        $result = $repo->findByKpiValue($kpiValue);

        $this->assertSame($expected, $result);
    }

    public function testFindByFilenameReturnsEntityOrNull()
    {
        $filename = 'test.pdf';
        $expected = new KPIFile();

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getOneOrNullResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();

        $found = $repo->findByFilename($filename);
        $this->assertSame($expected, $found);
    }

    public function testFindByMimeTypeReturnsArray()
    {
        $mime = 'application/pdf';
        $expected = [new KPIFile()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $res = $repo->findByMimeType($mime);

        $this->assertSame($expected, $res);
    }

    public function testFindImagesReturnsArray()
    {
        $expected = [new KPIFile()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $res = $repo->findImages();

        $this->assertSame($expected, $res);
    }

    public function testFindLargerThanReturnsArray()
    {
        $expected = [new KPIFile()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $res = $repo->findLargerThan(1024);

        $this->assertSame($expected, $res);
    }

    public function testGetTotalFileSizeReturnsInt()
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn('12345');

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $total = $repo->getTotalFileSize();

        $this->assertSame(12345, $total);
    }

    public function testCountByMimeTypeReturnsCounts()
    {
        $rows = [
            ['mimeType' => 'image/png', 'file_count' => '2'],
            ['mimeType' => 'application/pdf', 'file_count' => '1'],
        ];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $counts = $repo->countByMimeType();

        $this->assertArrayHasKey('image/png', $counts);
        $this->assertSame(2, $counts['image/png']);
        $this->assertSame(1, $counts['application/pdf']);
    }

    public function testFindOrphanedReturnsArray()
    {
        $expected = [new KPIFile()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $res = $repo->findOrphaned();

        $this->assertSame($expected, $res);
    }

    public function testFindOlderThanReturnsArray()
    {
        $date = new \DateTimeImmutable('-1 day');
        $expected = [new KPIFile()];

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($expected);

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $res = $repo->findOlderThan($date);

        $this->assertSame($expected, $res);
    }

    public function testCountAllReturnsInt()
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn('7');

        $qb = $this->createFluentQueryBuilder($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);
        $repo = $this->createRepository();
        $count = $repo->countAll();

        $this->assertSame(7, $count);
    }
}
