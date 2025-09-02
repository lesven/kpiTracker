<?php

namespace App\DTO;

use App\Entity\KPI;
use DateTimeInterface;

class DashboardKpiEntry
{
    public function __construct(
        public KPI $kpi,
        public string $status,
        public mixed $latestValue,
        public bool $isDueSoon,
        public bool $isOverdue,
        public ?DateTimeInterface $nextDueDate,
    ) {
    }
}
