<?php

namespace App\DTO;

use App\Entity\KPI;

/**
 * DTO für die Anzeige eines KPI-Eintrags im Dashboard.
 *
 * Enthält Status, letzte Werte und Fälligkeitsinformationen für ein KPI.
 */
class DashboardKpiEntry
{
    /**
     * @param KPI                     $kpi         Das zugehörige KPI-Objekt
     * @param string                  $status      Status des KPI (z.B. "green", "yellow", "red")
     * @param mixed                   $latestValue Der zuletzt gemeldete Wert
     * @param bool                    $isDueSoon   Gibt an, ob das KPI demnächst fällig ist
     * @param bool                    $isOverdue   Gibt an, ob das KPI überfällig ist
     * @param \DateTimeInterface|null $nextDueDate Das nächste Fälligkeitsdatum, falls vorhanden
     */
    public function __construct(
        public KPI $kpi,
        public string $status,
        public mixed $latestValue,
        public bool $isDueSoon,
        public bool $isOverdue,
        public ?\DateTimeInterface $nextDueDate,
    ) {
    }
}
