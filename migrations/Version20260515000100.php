<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer accounts and scope users/projects by customer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CUSTOMER_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `user` ADD customer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD customer_id INT DEFAULT NULL');
        $this->addSql('INSERT INTO customer (name, slug, created_at) SELECT COALESCE(NULLIF(name, \'\'), email), CONCAT(\'customer-\', id), NOW() FROM `user`');
        $this->addSql('UPDATE `user` u INNER JOIN customer c ON c.slug = CONCAT(\'customer-\', u.id) SET u.customer_id = c.id');
        $this->addSql('UPDATE project p INNER JOIN `user` u ON u.id = p.user_id SET p.customer_id = u.customer_id');
        $this->addSql('ALTER TABLE `user` CHANGE customer_id customer_id INT NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE customer_id customer_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_8D93D6499395C3F3 ON `user` (customer_id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE9395C3F3 ON project (customer_id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D6499395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D6499395C3F3');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE9395C3F3');
        $this->addSql('DROP INDEX IDX_8D93D6499395C3F3 ON `user`');
        $this->addSql('DROP INDEX IDX_2FB3D0EE9395C3F3 ON project');
        $this->addSql('ALTER TABLE `user` DROP customer_id');
        $this->addSql('ALTER TABLE project DROP customer_id');
        $this->addSql('DROP TABLE customer');
    }
}
