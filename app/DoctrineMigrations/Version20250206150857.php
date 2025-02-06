<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250206150857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // TODO Move sylius_order_loopeat_credentials.order_id to sylius_order.loopeat_credentials_id

        $this->addSql('ALTER TABLE sylius_order ADD loopeat_credentials_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F9BD145500 FOREIGN KEY (loopeat_credentials_id) REFERENCES sylius_order_loopeat_credentials (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6196A1F9BD145500 ON sylius_order (loopeat_credentials_id)');
        $this->addSql('ALTER TABLE sylius_order_loopeat_credentials DROP CONSTRAINT fk_dec04c298d9f6d38');
        $this->addSql('DROP INDEX uniq_dec04c298d9f6d38');
        $this->addSql('ALTER TABLE sylius_order_loopeat_credentials DROP order_id');
    }

    public function down(Schema $schema): void
    {
        // TODO Move sylius_order.loopeat_credentials_id to sylius_order_loopeat_credentials.order_id

        $this->addSql('ALTER TABLE sylius_order_loopeat_credentials ADD order_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order_loopeat_credentials ADD CONSTRAINT fk_dec04c298d9f6d38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_dec04c298d9f6d38 ON sylius_order_loopeat_credentials (order_id)');
        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F9BD145500');
        $this->addSql('DROP INDEX UNIQ_6196A1F9BD145500');
        $this->addSql('ALTER TABLE sylius_order DROP loopeat_credentials_id');
    }
}
