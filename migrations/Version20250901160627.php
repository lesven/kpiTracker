<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250901160627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor DecimalValue implementation: Update KPI target column for embedded DecimalValue objects';
    }

    public function up(Schema $schema): void
    {
        // Update KPI target column for DecimalValue embedding with correct nullability
        $this->addSql('ALTER TABLE kpi ADD target_value NUMERIC(10, 2) DEFAULT NULL, DROP target');
    }

    public function down(Schema $schema): void
    {
        // Revert to previous target column structure
        $this->addSql('ALTER TABLE kpi ADD target NUMERIC(10, 2) DEFAULT NULL, DROP target_value');
    }
}
