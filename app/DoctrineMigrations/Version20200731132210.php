<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200731132210 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE organization_config (id SERIAL NOT NULL, group_id INT DEFAULT NULL, organization_id INT DEFAULT NULL, address_id INT DEFAULT NULL, logo TEXT DEFAULT NULL, order_lead_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, limit_hour_order TEXT NOT NULL, start_hour_order TEXT NOT NULL, day_of_order_available TEXT NOT NULL, number_of_order_available TEXT NOT NULL, amount_of_subsidy_per_employee_and_order TEXT NOT NULL, coverage_of_delivery_costs_by_the_company_or_the_employee TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D104FF89FE54D947 ON organization_config (group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D104FF8932C8A3DE ON organization_config (organization_id)');
        $this->addSql('CREATE INDEX IDX_D104FF89F5B7AF75 ON organization_config (address_id)');
        $this->addSql('CREATE TABLE organization (id SERIAL NOT NULL, name TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE organization_config ADD CONSTRAINT FK_D104FF89FE54D947 FOREIGN KEY (group_id) REFERENCES sylius_customer_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_config ADD CONSTRAINT FK_D104FF8932C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_config ADD CONSTRAINT FK_D104FF89F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE organization_config DROP CONSTRAINT FK_D104FF8932C8A3DE');
        $this->addSql('DROP TABLE organization_config');
        $this->addSql('DROP TABLE organization');
    }
}
