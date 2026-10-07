<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase sylius_product_option_value_translation.value length, generated pricing rule names can exceed 255 chars';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_product_option_value_translation ALTER value TYPE VARCHAR(1020)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sylius_product_option_value_translation ALTER value TYPE VARCHAR(255)
        SQL);
    }
}
