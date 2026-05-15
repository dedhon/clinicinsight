<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user roles and customer subscription metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD plan VARCHAR(40) NOT NULL DEFAULT \'starter\', ADD subscription_status VARCHAR(40) NOT NULL DEFAULT \'trial\', ADD billing_email VARCHAR(180) DEFAULT NULL, ADD stripe_customer_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD roles JSON NOT NULL');
        $this->addSql('UPDATE `user` SET roles = JSON_ARRAY()');
        $this->addSql('UPDATE `user` SET roles = JSON_ARRAY(\'ROLE_SUPER_ADMIN\') WHERE email LIKE \'%@clinicinsight.local\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP plan, DROP subscription_status, DROP billing_email, DROP stripe_customer_id');
        $this->addSql('ALTER TABLE `user` DROP roles');
    }
}
