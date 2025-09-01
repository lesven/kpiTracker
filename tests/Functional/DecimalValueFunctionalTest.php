<?php

namespace App\Tests\Functional;

use App\Domain\ValueObject\DecimalValue;
use App\Domain\ValueObject\KpiInterval;
use App\Domain\ValueObject\Period;
use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Repository\KPIValueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test to verify DecimalValue implementation works end-to-end.
 */
class DecimalValueFunctionalTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private KPIValueRepository $kpiValueRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->kpiValueRepository = static::getContainer()->get(KPIValueRepository::class);
    }

    public function testCompleteDecimalValueWorkflow(): void
    {
        // Create test user
        $user = new User();
        $user->setEmail('functional-test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Functional');
        $user->setLastName('Test');

        // Create KPI with DecimalValue target
        $kpi = new KPI();
        $kpi->setName('Functional Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);
        $kpi->setTarget(new DecimalValue('5000,00'));

        // Create KPIValue with DecimalValue
        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);
        $kpiValue->setValue(new DecimalValue('4750,25'));
        $kpiValue->setPeriod(new Period('2024-09'));
        $kpiValue->setComment('Functional test value');

        // Persist all entities
        $this->entityManager->persist($user);
        $this->entityManager->persist($kpi);
        $this->entityManager->persist($kpiValue);
        $this->entityManager->flush();

        // Test repository queries with embedded objects
        $foundValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, new Period('2024-09'));
        $this->assertNotNull($foundValue);
        $this->assertSame(4750.25, $foundValue->getValueAsFloat());
        $this->assertSame('4750,25', $foundValue->getValue()->format());

        // Test KPI target functionality
        $this->assertSame(5000.00, $kpi->getTargetAsFloat());
        $this->assertSame('5000,00', $kpi->getTarget()->format());

        // Test repository queries
        $allValues = $this->kpiValueRepository->findByKPI($kpi);
        $this->assertCount(1, $allValues);

        $latestValue = $this->kpiValueRepository->findLatestValueForKpi($kpi);
        $this->assertSame($foundValue->getId(), $latestValue->getId());

        // Test string representation
        $stringRepresentation = (string) $kpiValue;
        $this->assertStringContainsString('4750,25', $stringRepresentation);
        $this->assertStringContainsString('September 2024', $stringRepresentation);

        // Cleanup
        $this->entityManager->remove($kpiValue);
        $this->entityManager->remove($kpi);
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function testRepositoryAverageCalculation(): void
    {
        // Create test user and KPI
        $user = new User();
        $user->setEmail('avg-test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Average');
        $user->setLastName('Test');

        $kpi = new KPI();
        $kpi->setName('Average Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);

        // Create multiple values
        $value1 = new KPIValue();
        $value1->setKpi($kpi);
        $value1->setValue(new DecimalValue('100,00'));
        $value1->setPeriod(new Period('2024-07'));

        $value2 = new KPIValue();
        $value2->setKpi($kpi);
        $value2->setValue(new DecimalValue('200,00'));
        $value2->setPeriod(new Period('2024-08'));

        $value3 = new KPIValue();
        $value3->setKpi($kpi);
        $value3->setValue(new DecimalValue('300,00'));
        $value3->setPeriod(new Period('2024-09'));

        // Persist entities
        $this->entityManager->persist($user);
        $this->entityManager->persist($kpi);
        $this->entityManager->persist($value1);
        $this->entityManager->persist($value2);
        $this->entityManager->persist($value3);
        $this->entityManager->flush();

        // Test average calculation
        $average = $this->kpiValueRepository->calculateAverageForKpi($kpi);
        $this->assertSame(200.0, $average);

        // Test max value finding
        $maxValue = $this->kpiValueRepository->findMaxValueForKpi($kpi);
        $this->assertNotNull($maxValue);
        $this->assertSame(300.0, $maxValue->getValueAsFloat());

        // Cleanup
        $this->entityManager->remove($value1);
        $this->entityManager->remove($value2);
        $this->entityManager->remove($value3);
        $this->entityManager->remove($kpi);
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

    }
}
