<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store dataset row JSON payloads as encrypted longtext';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dataset_row CHANGE raw_data raw_data LONGTEXT NOT NULL COMMENT \'(DC2Type:encrypted_json)\', CHANGE normalized_data normalized_data LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:encrypted_json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dataset_row CHANGE raw_data raw_data JSON NOT NULL, CHANGE normalized_data normalized_data JSON DEFAULT NULL');
    }
}

