<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171202223908 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql("UPDATE sylius_tax_rate SET amount = :amount WHERE code = :code", [
            'amount' => 0.10,
            'code' => 'tva_10',
        ]);
        $this->addSql("UPDATE sylius_tax_rate SET amount = :amount WHERE code = :code", [
            'amount' => 0.055,
            'code' => 'tva_5_5',
        ]);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql("UPDATE sylius_tax_rate SET amount = :amount WHERE code = :code", [
            'amount' => 10,
            'code' => 'tva_10',
        ]);
        $this->addSql("UPDATE sylius_tax_rate SET amount = :amount WHERE code = :code", [
            'amount' => 5.5,
            'code' => 'tva_5_5',
        ]);
    }
}
