<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210225085809 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Copy restaurant phone number to address';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE address a SET telephone = r.telephone FROM restaurant r WHERE r.address_id = a.id AND r.telephone IS NOT NULL AND a.telephone IS NULL');

    }

    public function down(Schema $schema) : void
    {
    }
}
