<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902071525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change MailSettings username from string to EmailAddress value object';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kpi CHANGE target_value target_value NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE mail_settings ADD username_email VARCHAR(180) DEFAULT NULL, DROP username');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3892618D64C802C8 ON mail_settings (username_email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_3892618D64C802C8 ON mail_settings');
        $this->addSql('ALTER TABLE mail_settings ADD username VARCHAR(255) DEFAULT NULL, DROP username_email');
        $this->addSql('ALTER TABLE kpi CHANGE target_value target_value NUMERIC(10, 2) DEFAULT NULL');
    }
}
