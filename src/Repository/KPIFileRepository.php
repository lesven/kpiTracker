<?php

namespace App\Repository;

use App\Entity\KPIFile;
use App\Entity\KPIValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für KPIFile-Entity.
 *
 * @extends ServiceEntityRepository<KPIFile>
 */
class KPIFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KPIFile::class);
    }

    /**
     * Findet alle Dateien zu einem bestimmten KPI-Wert.
     *
     * @return KPIFile[]
     */
    public function findByKpiValue(KPIValue $kpiValue): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.kpiValue = :kpiValue')
            ->setParameter('kpiValue', $kpiValue)
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet eine Datei anhand des Dateinamens.
     */
    public function findByFilename(string $filename): ?KPIFile
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.filename = :filename')
            ->setParameter('filename', $filename)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Findet Dateien anhand des MIME-Types.
     *
     * @return KPIFile[]
     */
    public function findByMimeType(string $mimeType): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.mimeType = :mimeType')
            ->setParameter('mimeType', $mimeType)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Bilddateien.
     *
     * @return KPIFile[]
     */
    public function findImages(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.mimeType LIKE :imageType')
            ->setParameter('imageType', 'image/%')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet Dateien größer als eine bestimmte Größe.
     *
     * @return KPIFile[]
     */
    public function findLargerThan(int $sizeInBytes): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.fileSize > :size')
            ->setParameter('size', $sizeInBytes)
            ->orderBy('f.fileSize', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Berechnet die Gesamtgröße aller Dateien.
     */
    public function getTotalFileSize(): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('SUM(f.fileSize) as total_size')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }

    /**
     * Zählt Dateien nach MIME-Type.
     *
     * @return array<string, int> MIME-Type => Anzahl
     */
    public function countByMimeType(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('f.mimeType, COUNT(f.id) as file_count')
            ->groupBy('f.mimeType')
            ->orderBy('file_count', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['mimeType'] ?? 'unknown'] = (int) $row['file_count'];
        }

        return $counts;
    }

    /**
     * Findet verwaiste Dateien (ohne zugehörigen KPI-Wert).
     *
     * @return KPIFile[]
     */
    public function findOrphaned(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.kpiValue', 'kv')
            ->andWhere('kv.id IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alte Dateien für Cleanup.
     *
     * @return KPIFile[]
     */
    public function findOlderThan(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.createdAt < :date')
            ->setParameter('date', $date)
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Zählt die Gesamtanzahl aller Dateien.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
