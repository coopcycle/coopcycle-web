<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200701105003 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // Roughly update tax rates in Germany,
        // as we are not capable yet of handling changes in time
        $this->addSql('UPDATE sylius_tax_rate SET amount = 0.16 WHERE country = \'de\' AND amount = 0.19');
        $this->addSql('UPDATE sylius_tax_rate SET amount = 0.05 WHERE country = \'de\' AND amount = 0.07');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
