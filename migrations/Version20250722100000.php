<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250722100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add api_token to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD api_token VARCHAR(80) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6497BA2F5EB ON `user` (api_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D6497BA2F5EB ON `user`');
        $this->addSql('ALTER TABLE `user` DROP api_token');
    }
}
