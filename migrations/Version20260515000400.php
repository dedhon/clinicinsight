<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user language preference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD locale VARCHAR(5) NOT NULL DEFAULT \'es\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP locale');
    }
}
