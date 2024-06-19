<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240531144617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_quote ALTER payload TYPE TEXT');
        $this->addSql('ALTER TABLE delivery_quote ALTER payload DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_quote ALTER payload TYPE JSON');
        $this->addSql('ALTER TABLE delivery_quote ALTER payload DROP DEFAULT');
    }
}
