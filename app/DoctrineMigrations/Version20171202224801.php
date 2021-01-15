<?php

namespace Application\Migrations;

use AppBundle\Service\Taxation\FloatCalculator;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Sylius\Component\Taxation\Model\TaxRate;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171202224801 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ ADD total_excluding_tax DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE order_ ADD total_tax DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE order_ ADD total_including_tax DOUBLE PRECISION DEFAULT NULL');

        $this->addSql('ALTER TABLE delivery ADD tax_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD total_excluding_tax DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD total_tax DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD total_including_tax DOUBLE PRECISION DEFAULT NULL');

        // Create taxation for deliveries

        $this->addSql("INSERT INTO sylius_tax_category (code, name, created_at, updated_at) VALUES (:code, :name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)", [
                'code' => 'tva_livraison',
                'name' => 'TVA Livraison'
            ]);
        $this->addSql("INSERT INTO sylius_tax_rate (category_id, code, name, amount, included_in_price, calculator, created_at, updated_at) SELECT sylius_tax_category.id, :code, :name, :amount, TRUE, 'default', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM sylius_tax_category WHERE sylius_tax_category.code = :category", [
                'code' => 'tva_20',
                'name' => 'TVA 20%',
                'amount' => 0.20,
                'category' => 'tva_livraison',
            ]);


        $calculator = new FloatCalculator();

        // Migrate orders

        $stmts = [];
        $stmts['find_all_orders'] = $this->connection->prepare('SELECT * FROM order_');
        $stmts['find_all_deliveries'] = $this->connection->prepare('SELECT * FROM delivery');
        $stmts['order_items_total'] = $this->connection->prepare('SELECT SUM(price * quantity) FROM order_item WHERE order_id = :order_id');

        // Let's suppose everything uses a rate with amount 0.10
        $taxRate = new TaxRate();
        $taxRate->setAmount(0.10);
        $taxRate->setIncludedInPrice(true);

        $stmts['find_all_orders']->execute();
        while ($order = $stmts['find_all_orders']->fetch()) {

            $stmts['order_items_total']->bindParam('order_id', $order['id']);
            $stmts['order_items_total']->execute();

            $totalIncludingTax = $stmts['order_items_total']->fetchColumn(0);
            $totalTax = $calculator->calculate($totalIncludingTax, $taxRate);
            $totalExcludingTax = $totalIncludingTax - $totalTax;

            $this->addSql('UPDATE order_ SET total_excluding_tax = :total_excluding_tax, total_tax = :total_tax, total_including_tax = :total_including_tax WHERE id = :order_id', [
                'total_excluding_tax' => $totalExcludingTax,
                'total_tax' => $totalTax,
                'total_including_tax' => $totalIncludingTax,
                'order_id' => $order['id']
            ]);
        }

        // Migrate deliveries

        $deliveryTaxRate = new TaxRate();
        $deliveryTaxRate->setAmount(0.20);
        $deliveryTaxRate->setIncludedInPrice(true);

        $stmts['find_all_deliveries']->execute();
        while ($delivery = $stmts['find_all_deliveries']->fetch()) {

            $totalIncludingTax = $delivery['price'];
            $totalTax = $calculator->calculate($totalIncludingTax, $deliveryTaxRate);
            $totalExcludingTax = $totalIncludingTax - $totalTax;

            $this->addSql('UPDATE delivery SET tax_category_id = sylius_tax_category.id, total_excluding_tax = :total_excluding_tax, total_tax = :total_tax, total_including_tax = :total_including_tax FROM sylius_tax_category WHERE delivery.id = :delivery_id AND sylius_tax_category.code = :tax_category_code', [
                'total_excluding_tax' => $totalExcludingTax,
                'total_tax' => $totalTax,
                'total_including_tax' => $totalIncludingTax,
                'delivery_id' => $delivery['id'],
                'tax_category_code' => 'tva_livraison',
            ]);
        }

        $this->addSql('ALTER TABLE order_ ALTER COLUMN total_including_tax SET NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER COLUMN total_excluding_tax SET NOT NULL');
        $this->addSql('ALTER TABLE order_ ALTER COLUMN total_tax SET NOT NULL');

        $this->addSql('ALTER TABLE delivery ALTER COLUMN tax_category_id SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER COLUMN total_including_tax SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER COLUMN total_excluding_tax SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER COLUMN total_tax SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC109DF894ED FOREIGN KEY (tax_category_id) REFERENCES sylius_tax_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3781EC109DF894ED ON delivery (tax_category_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ DROP total_excluding_tax');
        $this->addSql('ALTER TABLE order_ DROP total_tax');
        $this->addSql('ALTER TABLE order_ DROP total_including_tax');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC109DF894ED');
        $this->addSql('DROP INDEX IDX_3781EC109DF894ED');
        $this->addSql('ALTER TABLE delivery DROP tax_category_id');
        $this->addSql('ALTER TABLE delivery DROP total_excluding_tax');
        $this->addSql('ALTER TABLE delivery DROP total_tax');
        $this->addSql('ALTER TABLE delivery DROP total_including_tax');

        $this->addSql("DELETE FROM sylius_tax_rate WHERE code = :code", [
            'code' => 'tva_20',
        ]);
        $this->addSql("DELETE FROM sylius_tax_category WHERE code = :code", [
            'code' => 'tva_livraison',
        ]);
    }
}
