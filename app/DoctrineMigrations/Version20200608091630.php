<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200608091630 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    private function getDefaultTaxCategory()
    {
        $stmt =
            $this->connection->prepare('SELECT c.code, r.code AS rate_code, r.amount FROM sylius_tax_category c JOIN sylius_tax_rate r ON c.id = r.category_id WHERE c.code = ('.
                'SELECT value FROM craue_config_setting WHERE name = \'default_tax_category\''.
            ')');

        $result = $stmt->execute();

        $defaultTaxCategory = $result->fetchAssociative();

        if (!$defaultTaxCategory) {

            return null;
        }

        return $defaultTaxCategory;
    }

    public function up(Schema $schema) : void
    {
        $defaultTaxCategory = $this->getDefaultTaxCategory();
        $amount = (float) $defaultTaxCategory['amount'];

        if ($defaultTaxCategory) {
            $taxExempt = 0.0 == $defaultTaxCategory['amount'];
        } else {
            $taxExempt = false;
        }

        $subjectToVat = !$taxExempt;

        $this->addSql('INSERT INTO craue_config_setting (name, section, value) VALUES (:name, :section, :value)', [
            'name' => 'subject_to_vat',
            'section'  => 'general',
            'value' => $subjectToVat ? '1' : '0',
        ]);
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DELETE FROM craue_config_setting WHERE name = :name', [
            'name' => 'subject_to_vat',
        ]);
    }
}
