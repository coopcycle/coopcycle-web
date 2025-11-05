<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020144212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_promotion ADD featured BOOLEAN DEFAULT NULL');
        $this->addSql('UPDATE sylius_promotion SET featured = \'f\'');
        $this->addSql('ALTER TABLE sylius_promotion ALTER COLUMN featured SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_promotion DROP featured');
    }
}
