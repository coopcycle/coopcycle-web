<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200304204323 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE sylius_product SET reusable_packaging_unit = 0 WHERE reusable_packaging_unit IS NULL');
    }

    public function down(Schema $schema) : void
    {
    }
}
