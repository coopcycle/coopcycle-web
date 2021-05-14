<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210225170556 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE sylius_payment SET method_id = (SELECT id FROM sylius_payment_method WHERE code = \'GIROPAY\') WHERE details->>\'source_type\' = \'giropay\' OR JSONB_EXISTS((details->>\'payment_method_types\')::jsonb, \'giropay\')');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
