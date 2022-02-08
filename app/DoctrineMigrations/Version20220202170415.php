<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220202170415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order_vendor DROP CONSTRAINT FK_F26B2BE28D9F6D38');
        $this->addSql('ALTER TABLE sylius_order_vendor ADD CONSTRAINT FK_F26B2BE28D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order_vendor DROP CONSTRAINT fk_f26b2be28d9f6d38');
        $this->addSql('ALTER TABLE sylius_order_vendor ADD CONSTRAINT fk_f26b2be28d9f6d38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
