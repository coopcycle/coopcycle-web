<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240103181510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_variant ADD business_restaurant_group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_variant ADD CONSTRAINT FK_A29B523C4EC76B FOREIGN KEY (business_restaurant_group_id) REFERENCES business_restaurant_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A29B523C4EC76B ON sylius_product_variant (business_restaurant_group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_variant DROP CONSTRAINT FK_A29B523C4EC76B');
        $this->addSql('DROP INDEX IDX_A29B523C4EC76B');
        $this->addSql('ALTER TABLE sylius_product_variant DROP business_restaurant_group_id');
    }
}
