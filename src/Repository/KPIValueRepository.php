<?php

namespace App\Repository;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für KPIValue-Entity.
 *
 * @extends ServiceEntityRepository<KPIValue>
 */
class KPIValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KPIValue::class);
    }

    /**
     * Findet alle Werte einer bestimmten KPI.
     *
     * @return KPIValue[]
     */
    public function findByKPI(KPI $kpi): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.kpi = :kpi')
            ->setParameter('kpi', $kpi)
            ->orderBy('v.period', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet einen Wert für eine bestimmte KPI und Zeitraum.
     */
    public function findByKpiAndPeriod(KPI $kpi, string $period): ?KPIValue
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.kpi = :kpi')
            ->andWhere('v.period = :period')
            ->setParameter('kpi', $kpi)
            ->setParameter('period', $period)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Findet alle Werte eines Benutzers.
     *
     * @return KPIValue[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.kpi', 'k')
            ->andWhere('k.user = :user')
            ->setParameter('user', $user)
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet Werte in einem bestimmten Zeitraum.
     *
     * @return KPIValue[]
     */
    public function findCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.kpi', 'k')
            ->join('k.user', 'u')
            ->addSelect('k', 'u')
            ->andWhere('v.createdAt >= :start')
            ->andWhere('v.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet die neuesten Werte für Dashboard-Anzeige.
     *
     * @return KPIValue[]
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.kpi', 'k')
            ->addSelect('k')
            ->andWhere('k.user = :user')
            ->setParameter('user', $user)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Werte für CSV-Export eines Benutzers.
     *
     * @return KPIValue[]
     */
    public function findForUserExport(User $user): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.kpi', 'k')
            ->addSelect('k')
            ->andWhere('k.user = :user')
            ->setParameter('user', $user)
            ->orderBy('k.name', 'ASC')
            ->addOrderBy('v.period', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Werte für Admin-CSV-Export.
     *
     * @return KPIValue[]
     */
    public function findForAdminExport(): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.kpi', 'k')
            ->join('k.user', 'u')
            ->addSelect('k', 'u')
            ->orderBy('u.email', 'ASC')
            ->addOrderBy('k.name', 'ASC')
            ->addOrderBy('v.period', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Zählt Werte pro KPI.
     *
     * @return array<int, int> KPI-ID => Anzahl Werte
     */
    public function countValuesByKpi(): array
    {
        $result = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.kpi) as kpi_id, COUNT(v.id) as value_count')
            ->groupBy('v.kpi')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[(int) $row['kpi_id']] = (int) $row['value_count'];
        }

        return $counts;
    }

    /**
     * Findet Werte mit Kommentaren.
     *
     * @return KPIValue[]
     */
    public function findWithComments(): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.kpi', 'k')
            ->join('k.user', 'u')
            ->addSelect('k', 'u')
            ->andWhere('v.comment IS NOT NULL')
            ->andWhere('v.comment != :empty')
            ->setParameter('empty', '')
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet Werte mit Datei-Uploads.
     *
     * @return KPIValue[]
     */
    public function findWithFiles(): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.kpi', 'k')
            ->join('k.user', 'u')
            ->join('v.files', 'f')
            ->addSelect('k', 'u', 'f')
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Berechnet Durchschnittswerte für eine KPI.
     */
    public function calculateAverageForKpi(KPI $kpi): ?float
    {
        $result = $this->createQueryBuilder('v')
            ->select('AVG(v.value) as avg_value')
            ->andWhere('v.kpi = :kpi')
            ->setParameter('kpi', $kpi)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : null;
    }

    /**
     * Findet den höchsten Wert für eine KPI.
     */
    public function findMaxValueForKpi(KPI $kpi): ?KPIValue
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.kpi = :kpi')
            ->setParameter('kpi', $kpi)
            ->orderBy('CAST(v.value as DECIMAL)', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Zählt die Gesamtanzahl aller erfassten Werte.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
