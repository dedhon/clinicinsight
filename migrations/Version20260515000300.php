<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user project sharing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_share (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B8D71D7166D1F9C (project_id), INDEX IDX_B8D71D7A76ED395 (user_id), UNIQUE INDEX uniq_project_share_user (project_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project_share ADD CONSTRAINT FK_B8D71D7166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_share ADD CONSTRAINT FK_B8D71D7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_share DROP FOREIGN KEY FK_B8D71D7166D1F9C');
        $this->addSql('ALTER TABLE project_share DROP FOREIGN KEY FK_B8D71D7A76ED395');
        $this->addSql('DROP TABLE project_share');
    }
}
