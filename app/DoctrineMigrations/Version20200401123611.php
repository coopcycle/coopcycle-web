<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200401123611 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD stripe_payment_methods JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.stripe_payment_methods IS \'(DC2Type:json_array)\'');
        $this->addSql('UPDATE restaurant SET stripe_payment_methods = :stripe_payment_methods', [
            'stripe_payment_methods' => json_encode([])
        ]);
        $this->addSql('ALTER TABLE restaurant ALTER stripe_payment_methods SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant DROP stripe_payment_methods');
    }
}
