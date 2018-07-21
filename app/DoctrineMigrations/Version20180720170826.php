<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180720170826 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_currency (id SERIAL NOT NULL, code VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_96EDD3D077153098 ON sylius_currency (code)');
        $this->addSql('CREATE TABLE sylius_exchange_rate (id SERIAL NOT NULL, source_currency INT NOT NULL, target_currency INT NOT NULL, ratio NUMERIC(10, 5) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5F52B852A76BEED ON sylius_exchange_rate (source_currency)');
        $this->addSql('CREATE INDEX IDX_5F52B85B3FD5856 ON sylius_exchange_rate (target_currency)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5F52B852A76BEEDB3FD5856 ON sylius_exchange_rate (source_currency, target_currency)');
        $this->addSql('ALTER TABLE sylius_exchange_rate ADD CONSTRAINT FK_5F52B852A76BEED FOREIGN KEY (source_currency) REFERENCES sylius_currency (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_exchange_rate ADD CONSTRAINT FK_5F52B85B3FD5856 FOREIGN KEY (target_currency) REFERENCES sylius_currency (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_exchange_rate DROP CONSTRAINT FK_5F52B852A76BEED');
        $this->addSql('ALTER TABLE sylius_exchange_rate DROP CONSTRAINT FK_5F52B85B3FD5856');
        $this->addSql('DROP TABLE sylius_currency');
        $this->addSql('DROP TABLE sylius_exchange_rate');
    }
}
