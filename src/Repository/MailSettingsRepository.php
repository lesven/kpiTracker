<?php

namespace App\Repository;

use App\Entity\MailSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository-Klasse für die Verwaltung von MailSettings-Entities.
 *
 * Bietet Methoden zum Finden und Verwalten von Mailserver-Konfigurationen.
 *
 * @extends ServiceEntityRepository<MailSettings>
 */
class MailSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailSettings::class);
    }
}
