<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171207033054 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        $this->addSql("UPDATE sylius_tax_rate SET amount = :amount, calculator = :calculator WHERE code = :code", [
            'amount' => 0.055,
            'calculator' => 'float',
            'code' => 'tva_5_5',
        ]);
        $this->addSql("UPDATE sylius_tax_rate SET amount = :amount, calculator = :calculator WHERE code = :code", [
            'amount' => 0.10,
            'calculator' => 'float',
            'code' => 'tva_10',
        ]);
        $this->addSql("UPDATE sylius_tax_rate SET amount = :amount, calculator = :calculator WHERE code = :code", [
            'amount' => 0.20,
            'calculator' => 'float',
            'code' => 'tva_20',
        ]);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
    }
}
