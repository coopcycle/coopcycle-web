<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210104162109 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE sylius_tax_rate SET amount = 0.19 WHERE country = \'de\' AND amount = 0.16');
        $this->addSql('UPDATE sylius_tax_rate SET amount = 0.07 WHERE country = \'de\' AND amount = 0.05');

    }

    public function down(Schema $schema) : void
    {
    }
}
