<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231002184446 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE business_account (id SERIAL NOT NULL, address_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2005EDE9F5B7AF75 ON business_account (address_id)');
        $this->addSql('ALTER TABLE business_account ADD CONSTRAINT FK_2005EDE9F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user ADD business_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD CONSTRAINT FK_AC64A0BA5BC85711 FOREIGN KEY (business_account_id) REFERENCES business_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AC64A0BA5BC85711 ON api_user (business_account_id)');
        $this->addSql('ALTER TABLE restaurant ADD business_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F5BC85711 FOREIGN KEY (business_account_id) REFERENCES business_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123F5BC85711 ON restaurant (business_account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_user DROP CONSTRAINT FK_AC64A0BA5BC85711');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F5BC85711');
        $this->addSql('DROP TABLE business_account');
        $this->addSql('DROP INDEX IDX_AC64A0BA5BC85711');
        $this->addSql('ALTER TABLE api_user DROP business_account_id');
        $this->addSql('DROP INDEX IDX_EB95123F5BC85711');
        $this->addSql('ALTER TABLE restaurant DROP business_account_id');
    }
}
