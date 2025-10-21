<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021142643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_promotion_coupon ADD featured BOOLEAN DEFAULT NULL');
        $this->addSql('UPDATE sylius_promotion_coupon SET featured = \'f\'');
        $this->addSql('ALTER TABLE sylius_promotion_coupon ALTER COLUMN featured SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_promotion_coupon DROP featured');
    }
}
