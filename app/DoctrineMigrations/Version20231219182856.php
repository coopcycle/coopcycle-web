<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231219182856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE business_restaurant_group (id SERIAL NOT NULL, contract_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, shipping_options_days INT DEFAULT 2 NOT NULL, delivery_perimeter_expression VARCHAR(255) DEFAULT \'distance < 3000\' NOT NULL, enabled BOOLEAN NOT NULL, cutoff_time VARCHAR(5) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AA077D532576E0FD ON business_restaurant_group (contract_id)');
        $this->addSql('CREATE TABLE business_restaurant_group_restaurant_menu (business_restaurant_group_id INT NOT NULL, restaurant_id INT NOT NULL, menu_id INT NOT NULL, PRIMARY KEY(business_restaurant_group_id, restaurant_id, menu_id))');
        $this->addSql('CREATE INDEX IDX_958D3B29C4EC76B ON business_restaurant_group_restaurant_menu (business_restaurant_group_id)');
        $this->addSql('CREATE INDEX IDX_958D3B29B1E7706E ON business_restaurant_group_restaurant_menu (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_958D3B29CCD7E912 ON business_restaurant_group_restaurant_menu (menu_id)');
        $this->addSql('CREATE TABLE business_restaurant_group_closing_rule (business_restaurant_group_id INT NOT NULL, rule_id INT NOT NULL, PRIMARY KEY(business_restaurant_group_id, rule_id))');
        $this->addSql('CREATE INDEX IDX_88A97212C4EC76B ON business_restaurant_group_closing_rule (business_restaurant_group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88A97212744E0351 ON business_restaurant_group_closing_rule (rule_id)');
        $this->addSql('CREATE TABLE business_restaurant_group_fulfillment_method (business_restaurant_group_id INT NOT NULL, method_id INT NOT NULL, PRIMARY KEY(business_restaurant_group_id, method_id))');
        $this->addSql('CREATE INDEX IDX_A942AABEC4EC76B ON business_restaurant_group_fulfillment_method (business_restaurant_group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A942AABE19883967 ON business_restaurant_group_fulfillment_method (method_id)');
        $this->addSql('ALTER TABLE business_restaurant_group ADD CONSTRAINT FK_AA077D532576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_restaurant_menu ADD CONSTRAINT FK_958D3B29C4EC76B FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_restaurant_menu ADD CONSTRAINT FK_958D3B29B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_restaurant_menu ADD CONSTRAINT FK_958D3B29CCD7E912 FOREIGN KEY (menu_id) REFERENCES sylius_taxon (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_closing_rule ADD CONSTRAINT FK_88A97212C4EC76B FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_closing_rule ADD CONSTRAINT FK_88A97212744E0351 FOREIGN KEY (rule_id) REFERENCES closing_rule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_fulfillment_method ADD CONSTRAINT FK_A942AABEC4EC76B FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_restaurant_group_fulfillment_method ADD CONSTRAINT FK_A942AABE19883967 FOREIGN KEY (method_id) REFERENCES restaurant_fulfillment_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE business_account DROP CONSTRAINT fk_2005ede96c786081');
        $this->addSql('DROP INDEX uniq_2005ede96c786081');
        $this->addSql('ALTER TABLE business_account RENAME COLUMN hub_id TO business_restaurant_group_id');
        $this->addSql('ALTER TABLE business_account ADD CONSTRAINT FK_2005EDE9C4EC76B FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2005EDE9C4EC76B ON business_account (business_restaurant_group_id)');
        $this->addSql('ALTER TABLE hub DROP CONSTRAINT fk_4871ce4d5bc85711');
        $this->addSql('DROP INDEX uniq_4871ce4d5bc85711');
        $this->addSql('ALTER TABLE hub DROP business_account_id');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT fk_eb95123f5bc85711');
        $this->addSql('DROP INDEX idx_eb95123f5bc85711');
        $this->addSql('ALTER TABLE restaurant DROP COLUMN business_account_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE business_account DROP CONSTRAINT FK_2005EDE9C4EC76B');
        $this->addSql('ALTER TABLE business_restaurant_group_closing_rule DROP CONSTRAINT FK_88A97212C4EC76B');
        $this->addSql('ALTER TABLE business_restaurant_group_fulfillment_method DROP CONSTRAINT FK_A942AABEC4EC76B');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123FC4EC76B');
        $this->addSql('DROP TABLE business_restaurant_group');
        $this->addSql('DROP TABLE business_restaurant_group_restaurant_menu');
        $this->addSql('DROP TABLE business_restaurant_group_closing_rule');
        $this->addSql('DROP TABLE business_restaurant_group_fulfillment_method');
        $this->addSql('ALTER TABLE hub ADD business_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE hub ADD CONSTRAINT fk_4871ce4d5bc85711 FOREIGN KEY (business_account_id) REFERENCES business_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_4871ce4d5bc85711 ON hub (business_account_id)');
        $this->addSql('DROP INDEX IDX_EB95123FC4EC76B');
        $this->addSql('ALTER TABLE restaurant RENAME COLUMN business_restaurant_group_id TO business_account_id');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT fk_eb95123f5bc85711 FOREIGN KEY (business_account_id) REFERENCES business_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_eb95123f5bc85711 ON restaurant (business_account_id)');
        $this->addSql('DROP INDEX IDX_2005EDE9C4EC76B');
        $this->addSql('ALTER TABLE business_account RENAME COLUMN business_restaurant_group_id TO hub_id');
        $this->addSql('ALTER TABLE business_account ADD CONSTRAINT fk_2005ede96c786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_2005ede96c786081 ON business_account (hub_id)');
    }
}
