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
        return 'Fill legacy vehicle fields';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trailer ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicle ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // at the moment we write the feature we assume legacy vehicles are not useful so let's soft delete them
        // also they misses the "warehouse" field so they would need manual user-update to be usable
        $this->addSql("UPDATE vehicle SET color = '#0000ff', is_electric = false, deleted_at = NOW();");

        $this->addSql("ALTER TABLE vehicle RENAME COLUMN volume_units TO max_volume_units;");
        $this->addSql("ALTER TABLE vehicle ALTER COLUMN is_electric SET NOT NULL;");
        $this->addSql("ALTER TABLE vehicle ALTER COLUMN color SET NOT NULL;");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trailer DROP deleted_at');
        $this->addSql('ALTER TABLE vehicle DROP deleted_at');
        $this->addSql("ALTER TABLE vehicle ALTER COLUMN is_electric DROP NOT NULL;");
        $this->addSql("ALTER TABLE vehicle ALTER COLUMN color DROP NOT NULL;");

    }
}
