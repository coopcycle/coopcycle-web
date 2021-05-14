<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210111092056 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order ADD receipt_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F92B5CA896 FOREIGN KEY (receipt_id) REFERENCES sylius_order_receipt (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6196A1F92B5CA896 ON sylius_order (receipt_id)');

        $this->addSql('UPDATE sylius_order o SET receipt_id = r.id FROM sylius_order_receipt r WHERE o.id = r.order_id');

        $this->addSql('ALTER TABLE sylius_order_receipt DROP CONSTRAINT fk_49442b208d9f6d38');
        $this->addSql('DROP INDEX uniq_49442b208d9f6d38');
        $this->addSql('ALTER TABLE sylius_order_receipt DROP order_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order_receipt ADD order_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order_receipt ADD CONSTRAINT fk_49442b208d9f6d38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_49442b208d9f6d38 ON sylius_order_receipt (order_id)');

        $this->addSql('UPDATE sylius_order_receipt r SET order_id = o.id FROM sylius_order o WHERE o.receipt_id = r.id');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F92B5CA896');
        $this->addSql('DROP INDEX UNIQ_6196A1F92B5CA896');
        $this->addSql('ALTER TABLE sylius_order DROP receipt_id');

    }
}
