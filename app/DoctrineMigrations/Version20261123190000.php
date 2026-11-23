<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a weight (in grams) to products & product option values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product ADD weight INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option_value ADD weight INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product DROP weight');
        $this->addSql('ALTER TABLE sylius_product_option_value DROP weight');
    }
}
