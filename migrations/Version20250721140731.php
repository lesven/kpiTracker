<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250721140731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kpi (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, `interval` VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A0925DD9A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kpifile (id INT AUTO_INCREMENT NOT NULL, kpi_value_id INT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, file_size INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_26063690C02C994C (kpi_value_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kpivalue (id INT AUTO_INCREMENT NOT NULL, kpi_id INT NOT NULL, value NUMERIC(10, 2) NOT NULL, period VARCHAR(20) NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F0654214F50D1A5E (kpi_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kpi ADD CONSTRAINT FK_A0925DD9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE kpifile ADD CONSTRAINT FK_26063690C02C994C FOREIGN KEY (kpi_value_id) REFERENCES kpivalue (id)');
        $this->addSql('ALTER TABLE kpivalue ADD CONSTRAINT FK_F0654214F50D1A5E FOREIGN KEY (kpi_id) REFERENCES kpi (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kpi DROP FOREIGN KEY FK_A0925DD9A76ED395');
        $this->addSql('ALTER TABLE kpifile DROP FOREIGN KEY FK_26063690C02C994C');
        $this->addSql('ALTER TABLE kpivalue DROP FOREIGN KEY FK_F0654214F50D1A5E');
        $this->addSql('DROP TABLE kpi');
        $this->addSql('DROP TABLE kpifile');
        $this->addSql('DROP TABLE kpivalue');
        $this->addSql('DROP TABLE `user`');
    }
}
