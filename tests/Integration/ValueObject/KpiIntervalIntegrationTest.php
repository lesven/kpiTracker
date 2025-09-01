<?php

namespace App\Tests\Integration\ValueObject;

use App\Domain\ValueObject\KpiInterval;
use App\Entity\KPI;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration Tests für das KpiInterval Value Object mit der KPI Entity.
 *
 * Testet die Integration zwischen KpiInterval und der KPI Entity in realen Szenarien.
 */
class KpiIntervalIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
    }

    /**
     * Testet das Setzen und Abrufen von KpiInterval in der KPI Entity.
     */
    public function testKpiIntervalInEntity(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::WEEKLY);

        $this->assertSame(KpiInterval::WEEKLY, $kpi->getInterval());
        $this->assertSame('weekly', $kpi->getInterval()->value);
        $this->assertSame('Wöchentlich', $kpi->getInterval()->label());
    }

    /**
     * Testet die verschiedenen Interval-Types in der KPI Entity.
     */
    public function testAllIntervalTypesInEntity(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $intervals = [
            KpiInterval::WEEKLY,
            KpiInterval::MONTHLY,
            KpiInterval::QUARTERLY,
        ];

        foreach ($intervals as $interval) {
            $kpi = new KPI();
            $kpi->setName('Test KPI '.$interval->value);
            $kpi->setUser($user);
            $kpi->setInterval($interval);

            $this->assertSame($interval, $kpi->getInterval());
            $this->assertIsString($kpi->getInterval()->label());
        }
    }

    /**
     * Testet die getCurrentPeriod() Methode mit verschiedenen Intervallen.
     */
    public function testGetCurrentPeriodWithDifferentIntervals(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        // Weekly KPI
        $weeklyKpi = new KPI();
        $weeklyKpi->setName('Weekly KPI');
        $weeklyKpi->setUser($user);
        $weeklyKpi->setInterval(KpiInterval::WEEKLY);

        $weeklyPeriod = $weeklyKpi->getCurrentPeriod();
        $this->assertMatchesRegularExpression('/^\d{4}-W\d{2}$/', (string) $weeklyPeriod); // Format: YYYY-WXX

        // Monthly KPI
        $monthlyKpi = new KPI();
        $monthlyKpi->setName('Monthly KPI');
        $monthlyKpi->setUser($user);
        $monthlyKpi->setInterval(KpiInterval::MONTHLY);

        $monthlyPeriod = $monthlyKpi->getCurrentPeriod();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $monthlyPeriod);

        // Quarterly KPI
        $quarterlyKpi = new KPI();
        $quarterlyKpi->setName('Quarterly KPI');
        $quarterlyKpi->setUser($user);
        $quarterlyKpi->setInterval(KpiInterval::QUARTERLY);

        $quarterlyPeriod = $quarterlyKpi->getCurrentPeriod();
        $this->assertMatchesRegularExpression('/^\d{4}-Q[1-4]$/', $quarterlyPeriod);
    }

    /**
     * Testet die getNextDueDate() Methode mit verschiedenen Intervallen.
     */
    public function testGetNextDueDateWithDifferentIntervals(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $intervals = [
            KpiInterval::WEEKLY,
            KpiInterval::MONTHLY,
            KpiInterval::QUARTERLY,
        ];

        foreach ($intervals as $interval) {
            $kpi = new KPI();
            $kpi->setName('Test KPI '.$interval->value);
            $kpi->setUser($user);
            $kpi->setInterval($interval);

            $dueDate = $kpi->getNextDueDate();

            $this->assertInstanceOf(\DateTimeImmutable::class, $dueDate);
            $this->assertGreaterThan(new \DateTimeImmutable(), $dueDate);
        }
    }

    /**
     * Testet die Serialisierung für JSON-Responses.
     */
    public function testJsonSerialization(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI');
        $kpi->setUser($user);
        $kpi->setInterval(KpiInterval::MONTHLY);

        $data = [
            'name' => $kpi->getName(),
            'interval' => $kpi->getInterval()->value,
            'interval_label' => $kpi->getInterval()->label(),
        ];

        $json = json_encode($data);
        $decoded = json_decode($json, true);

        $this->assertSame('Test KPI', $decoded['name']);
        $this->assertSame('monthly', $decoded['interval']);
        $this->assertSame('Monatlich', $decoded['interval_label']);
    }

    /**
     * Testet die Konsistenz zwischen Enum-Werten und den erwarteten Formaten.
     */
    public function testIntervalConsistency(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        // Teste, dass weekly tatsächlich wöchentliche Formate erzeugt (Format: YYYY-WXX)
        $weeklyKpi = new KPI();
        $weeklyKpi->setName('Weekly KPI');
        $weeklyKpi->setUser($user);
        $weeklyKpi->setInterval(KpiInterval::WEEKLY);
        $weeklyPeriod = $weeklyKpi->getCurrentPeriod();
        $this->assertMatchesRegularExpression('/^\d{4}-W\d{2}$/', (string) $weeklyPeriod);

        // Teste, dass monthly monatliche Formate erzeugt (Format: YYYY-MM)
        $monthlyKpi = new KPI();
        $monthlyKpi->setName('Monthly KPI');
        $monthlyKpi->setUser($user);
        $monthlyKpi->setInterval(KpiInterval::MONTHLY);
        $monthlyPeriod = $monthlyKpi->getCurrentPeriod();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{1,2}$/', (string) $monthlyPeriod);

        // Teste, dass quarterly quartalsweise Formate erzeugt (Format: YYYY-QX)
        $quarterlyKpi = new KPI();
        $quarterlyKpi->setName('Quarterly KPI');
        $quarterlyKpi->setUser($user);
        $quarterlyKpi->setInterval(KpiInterval::QUARTERLY);
        $quarterlyPeriod = $quarterlyKpi->getCurrentPeriod();
        $this->assertStringContainsString('Q', (string) $quarterlyPeriod);
        $this->assertMatchesRegularExpression('/^\d{4}-Q[1-4]$/', (string) $quarterlyPeriod);
    }

    /**
     * Testet Edge Cases bei der Period-Berechnung.
     */
    public function testPeriodCalculationEdgeCases(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $kpi = new KPI();
        $kpi->setName('Test KPI');
        $kpi->setUser($user);

        // Test mit verschiedenen Intervallen
        foreach (KpiInterval::cases() as $interval) {
            $kpi->setInterval($interval);
            $period = $kpi->getCurrentPeriod();

            // Periode sollte nie leer sein
            $this->assertNotEmpty($period);

            // Periode sollte das aktuelle Jahr enthalten
            $currentYear = date('Y');
            $this->assertStringStartsWith($currentYear, $period);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
