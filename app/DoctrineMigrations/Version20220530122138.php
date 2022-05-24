<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220530122138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE woopit_quote_request ADD state VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE woopit_quote_request ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE woopit_quote_request ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE woopit_quote_request DROP state');
        $this->addSql('ALTER TABLE woopit_quote_request DROP created_at');
        $this->addSql('ALTER TABLE woopit_quote_request DROP updated_at');
    }
}
