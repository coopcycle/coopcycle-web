<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240703124328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fill new vehicle fields';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("UPDATE vehicle SET color = '#0000ff', is_electric = false;");
        $this->addSql("ALTER TABLE vehicle RENAME COLUMN volume_units TO max_volume_units;");
        $this->addSql("ALTER TABLE vehicle ALTER COLUMN is_electric SET NOT NULL;");
        $this->addSql("ALTER TABLE vehicle ALTER COLUMN color SET NOT NULL;");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
