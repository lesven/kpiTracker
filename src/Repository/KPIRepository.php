<?php

namespace App\Repository;

use App\Entity\KPI;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für KPI-Entity.
 *
 * @extends ServiceEntityRepository<KPI>
 */
class KPIRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KPI::class);
    }

    /**
     * Findet alle KPIs eines bestimmten Benutzers.
     *
     * @return KPI[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.user = :user')
            ->setParameter('user', $user)
            ->orderBy('k.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet KPIs mit einem bestimmten Intervall.
     *
     * @return KPI[]
     */
    public function findByInterval(string $interval): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.interval = :interval')
            ->setParameter('interval', $interval)
            ->orderBy('k.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sucht KPIs anhand des Namens (für Admins).
     *
     * @return KPI[]
     */
    public function findByNameLike(string $searchTerm): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.user', 'u')
            ->andWhere('k.name LIKE :term')
            ->setParameter('term', '%'.$searchTerm.'%')
            ->orderBy('k.name', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle KPIs für Admin-Übersicht mit Benutzerinformationen.
     *
     * @return KPI[]
     */
    public function findAllWithUser(): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.user', 'u')
            ->addSelect('u')
            ->orderBy('u.email', 'ASC')
            ->addOrderBy('k.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Zählt KPIs pro Benutzer.
     *
     * @return array<int, array{user: User, kpi_count: int}>
     */
    public function countKpisByUser(): array
    {
        return $this->createQueryBuilder('k')
            ->select('u AS user, COUNT(k.id) AS kpi_count')
            ->join('k.user', 'u')
            ->groupBy('u.id')
            ->orderBy('kpi_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet KPIs die in einem bestimmten Zeitraum erstellt wurden.
     *
     * @return KPI[]
     */
    public function findCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.user', 'u')
            ->addSelect('u')
            ->andWhere('k.createdAt >= :start')
            ->andWhere('k.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet KPIs die möglicherweise überfällig sind (für Erinnerungen).
     *
     * @return KPI[]
     */
    public function findDueForReminder(): array
    {
        // Hier würde komplexere Logik für Fälligkeitsprüfung stehen
        // Die implementieren wir im KPI-Service
        return $this->createQueryBuilder('k')
            ->join('k.user', 'u')
            ->addSelect('u')
            ->orderBy('k.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Zählt die Gesamtanzahl aller KPIs.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Findet KPIs mit den meisten Werten (für Statistiken).
     *
     * @return KPI[]
     */
    public function findMostActive(int $limit = 10): array
    {
        return $this->createQueryBuilder('k')
            ->join('k.values', 'v')
            ->join('k.user', 'u')
            ->addSelect('u')
            ->select('k, u, COUNT(v.id) as value_count')
            ->groupBy('k.id')
            ->orderBy('value_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
