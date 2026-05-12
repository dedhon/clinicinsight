<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial ClinicInsight MVP schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(160) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2FB3D0EEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE uploaded_file (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, status VARCHAR(40) NOT NULL, row_count INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', error_message LONGTEXT DEFAULT NULL, INDEX IDX_9E65075C166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dataset_column (id INT AUTO_INCREMENT NOT NULL, uploaded_file_id INT NOT NULL, original_name VARCHAR(255) NOT NULL, detected_type VARCHAR(80) DEFAULT NULL, mapped_field VARCHAR(80) DEFAULT NULL, confidence DOUBLE PRECISION DEFAULT NULL, example_values JSON DEFAULT NULL, ignored TINYINT(1) NOT NULL, reason LONGTEXT DEFAULT NULL, INDEX IDX_D41B58AA6E0E220C (uploaded_file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dataset_row (id INT AUTO_INCREMENT NOT NULL, uploaded_file_id INT NOT NULL, row_number INT NOT NULL, raw_data JSON NOT NULL, normalized_data JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8C5F72F66E0E220C (uploaded_file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE insight_report (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, uploaded_file_id INT NOT NULL, summary LONGTEXT DEFAULT NULL, kpis JSON NOT NULL, charts JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2D23948B166D1F9C (project_id), INDEX IDX_2D23948B6E0E220C (uploaded_file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE uploaded_file ADD CONSTRAINT FK_9E65075C166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE dataset_column ADD CONSTRAINT FK_D41B58AA6E0E220C FOREIGN KEY (uploaded_file_id) REFERENCES uploaded_file (id)');
        $this->addSql('ALTER TABLE dataset_row ADD CONSTRAINT FK_8C5F72F66E0E220C FOREIGN KEY (uploaded_file_id) REFERENCES uploaded_file (id)');
        $this->addSql('ALTER TABLE insight_report ADD CONSTRAINT FK_2D23948B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE insight_report ADD CONSTRAINT FK_2D23948B6E0E220C FOREIGN KEY (uploaded_file_id) REFERENCES uploaded_file (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEA76ED395');
        $this->addSql('ALTER TABLE uploaded_file DROP FOREIGN KEY FK_9E65075C166D1F9C');
        $this->addSql('ALTER TABLE dataset_column DROP FOREIGN KEY FK_D41B58AA6E0E220C');
        $this->addSql('ALTER TABLE dataset_row DROP FOREIGN KEY FK_8C5F72F66E0E220C');
        $this->addSql('ALTER TABLE insight_report DROP FOREIGN KEY FK_2D23948B166D1F9C');
        $this->addSql('ALTER TABLE insight_report DROP FOREIGN KEY FK_2D23948B6E0E220C');
        $this->addSql('DROP TABLE insight_report');
        $this->addSql('DROP TABLE dataset_row');
        $this->addSql('DROP TABLE dataset_column');
        $this->addSql('DROP TABLE uploaded_file');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE `user`');
    }
}

