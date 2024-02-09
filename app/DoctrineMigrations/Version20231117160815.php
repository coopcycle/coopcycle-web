<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231117160815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE business_account ADD hub_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE business_account ADD CONSTRAINT FK_2005EDE96C786081 FOREIGN KEY (hub_id) REFERENCES hub (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2005EDE96C786081 ON business_account (hub_id)');
        $this->addSql('ALTER TABLE hub ADD business_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE hub ADD CONSTRAINT FK_4871CE4D5BC85711 FOREIGN KEY (business_account_id) REFERENCES business_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4871CE4D5BC85711 ON hub (business_account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hub DROP CONSTRAINT FK_4871CE4D5BC85711');
        $this->addSql('DROP INDEX UNIQ_4871CE4D5BC85711');
        $this->addSql('ALTER TABLE hub DROP business_account_id');
        $this->addSql('ALTER TABLE restaurant ADD business_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT fk_eb95123f5bc85711 FOREIGN KEY (business_account_id) REFERENCES business_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_eb95123f5bc85711 ON restaurant (business_account_id)');
        $this->addSql('ALTER TABLE business_account DROP CONSTRAINT FK_2005EDE96C786081');
        $this->addSql('DROP INDEX UNIQ_2005EDE96C786081');
        $this->addSql('ALTER TABLE business_account DROP hub_id');
    }
}
