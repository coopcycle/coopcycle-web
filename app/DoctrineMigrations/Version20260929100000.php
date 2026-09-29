<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the customer details and the product/service split to order receipts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order_receipt ADD customer_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order_receipt ADD customer_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order_receipt ADD customer_phone VARCHAR(255) DEFAULT NULL');

        // Left NULL on existing rows, which the entities read back as a
        // product line item and a "totals" footer item, i.e. exactly how
        // receipts issued before this change were laid out.
        $this->addSql('ALTER TABLE sylius_order_receipt_line_item ADD type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order_receipt_footer_item ADD section VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order_receipt DROP customer_name');
        $this->addSql('ALTER TABLE sylius_order_receipt DROP customer_email');
        $this->addSql('ALTER TABLE sylius_order_receipt DROP customer_phone');
        $this->addSql('ALTER TABLE sylius_order_receipt_line_item DROP type');
        $this->addSql('ALTER TABLE sylius_order_receipt_footer_item DROP section');
    }
}
