<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190926110559 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_payment_method_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, instructions TEXT DEFAULT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_966BE3A12C2AC5D3 ON sylius_payment_method_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_payment_method_translation_uniq_trans ON sylius_payment_method_translation (translatable_id, locale)');
        $this->addSql('CREATE TABLE sylius_payment_method (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, environment VARCHAR(255) DEFAULT NULL, is_enabled BOOLEAN NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A75B0B0D77153098 ON sylius_payment_method (code)');
        $this->addSql('CREATE TABLE sylius_payment (id SERIAL NOT NULL, method_id INT DEFAULT NULL, currency_code VARCHAR(3) NOT NULL, amount INT NOT NULL, state VARCHAR(255) NOT NULL, details JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D9191BD419883967 ON sylius_payment (method_id)');
        $this->addSql('COMMENT ON COLUMN sylius_payment.details IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE sylius_payment_method_translation ADD CONSTRAINT FK_966BE3A12C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_payment_method (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_payment ADD CONSTRAINT FK_D9191BD419883967 FOREIGN KEY (method_id) REFERENCES sylius_payment_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_payment_method_translation DROP CONSTRAINT FK_966BE3A12C2AC5D3');
        $this->addSql('ALTER TABLE sylius_payment DROP CONSTRAINT FK_D9191BD419883967');
        $this->addSql('DROP TABLE sylius_payment_method_translation');
        $this->addSql('DROP TABLE sylius_payment_method');
        $this->addSql('DROP TABLE sylius_payment');
    }
}
