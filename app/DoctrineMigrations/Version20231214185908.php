<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231214185908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE local_business_group_vendor (id SERIAL NOT NULL, hub_id INT DEFAULT NULL, business_restaurant_group_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9AB614906C786081 ON local_business_group_vendor (hub_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9AB61490FCFCB6C2 ON local_business_group_vendor (business_restaurant_group_id)');
        $this->addSql('CREATE TABLE business_restaurant_group (id SERIAL NOT NULL, contract_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, shipping_options_days INT DEFAULT 2 NOT NULL, delivery_perimeter_expression VARCHAR(255) DEFAULT \'distance < 3000\' NOT NULL, enabled BOOLEAN NOT NULL, cutoff_time VARCHAR(5) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B003856A2576E0FD ON business_restaurant_group (contract_id)');
        $this->addSql('CREATE TABLE business_restaurant_group_closing_rule (business_restaurant_group_id INT NOT NULL, rule_id INT NOT NULL, PRIMARY KEY(business_restaurant_group_id, rule_id))');
        $this->addSql('CREATE INDEX IDX_CA916F9CFCFCB6C2 ON business_restaurant_group_closing_rule (business_restaurant_group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CA916F9C744E0351 ON business_restaurant_group_closing_rule (rule_id)');
        $this->addSql('CREATE TABLE business_restaurant_group_fulfillment_method (business_restaurant_group_id INT NOT NULL, method_id INT NOT NULL, PRIMARY KEY(business_restaurant_group_id, method_id))');
        $this->addSql('CREATE INDEX IDX_530905C5FCFCB6C2 ON business_restaurant_group_fulfillment_method (business_restaurant_group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_530905C519883967 ON business_restaurant_group_fulfillment_method (method_id)');
        $this->addSql('ALTER TABLE local_business_group_vendor ADD CONSTRAINT FK_9AB614906C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE local_business_group_vendor ADD CONSTRAINT FK_9AB61490FCFCB6C2 FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group ADD CONSTRAINT FK_B003856A2576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_closing_rule ADD CONSTRAINT FK_CA916F9CFCFCB6C2 FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_closing_rule ADD CONSTRAINT FK_CA916F9C744E0351 FOREIGN KEY (rule_id) REFERENCES closing_rule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_fulfillment_method ADD CONSTRAINT FK_530905C5FCFCB6C2 FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_fulfillment_method ADD CONSTRAINT FK_530905C519883967 FOREIGN KEY (method_id) REFERENCES restaurant_fulfillment_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant ADD business_restaurant_group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F8B531F19 FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123F8B531F19 ON restaurant (business_restaurant_group_id)');
        $this->addSql('ALTER TABLE sylius_order ADD COLUMN local_business_group_vendor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F997FEBCB6 FOREIGN KEY (local_business_group_vendor_id) REFERENCES local_business_group_vendor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6196A1F997FEBCB6 ON sylius_order (local_business_group_vendor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE local_business_group_vendor DROP CONSTRAINT FK_9AB61490FCFCB6C2');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F8B531F19');
        $this->addSql('ALTER TABLE business_restaurant_group_closing_rule DROP CONSTRAINT FK_CA916F9CFCFCB6C2');
        $this->addSql('ALTER TABLE business_restaurant_group_fulfillment_method DROP CONSTRAINT FK_530905C5FCFCB6C2');
        $this->addSql('ALTER TABLE sylius_order DROP COLUMN local_business_group_vendor_id');
        $this->addSql('DROP TABLE local_business_group_vendor');
        $this->addSql('DROP TABLE business_restaurant_group');
        $this->addSql('DROP TABLE business_restaurant_group_closing_rule');
        $this->addSql('DROP TABLE business_restaurant_group_fulfillment_method');
        $this->addSql('DROP INDEX IDX_EB95123F8B531F19');
        $this->addSql('ALTER TABLE restaurant DROP COLUMN business_restaurant_group_id');
    }
}
