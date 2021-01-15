<?php declare(strict_types = 1);

namespace Application\Migrations;

use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180327083035 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private function findAllOrders()
    {
        $stmt = $this->connection->prepare('SELECT * FROM sylius_order');
        $stmt->execute();

        $orders = [];
        while ($order = $stmt->fetch()) {
            $orders[] = $order;
        }

        return $orders;
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $settingsManager       = $this->container->get('coopcycle.settings_manager');
        $taxCategoryRepository = $this->container->get('sylius.repository.tax_category');
        $taxCalculator         = $this->container->get('sylius.tax_calculator');
        $adjustmentFactory     = $this->container->get('sylius.factory.adjustment');

        $defaultTaxCategoryCode = $settingsManager->get('default_tax_category');
        if (!$defaultTaxCategoryCode) {
            $this->write('<comment>Default tax category is not configured</comment>');
            return;
        }

        $taxCategory = $taxCategoryRepository->findOneBy([
            'code' => $defaultTaxCategoryCode
        ]);
        $taxRate = $taxCategory->getRates()->get(0);

        $stmt = $this->connection->prepare('SELECT id AS order_item_id, total FROM sylius_order_item WHERE order_id = :order_id');

        foreach ($this->findAllOrders() as $order) {

            $stmt->bindParam('order_id', $order['id']);
            $stmt->execute();

            while ($row = $stmt->fetch()) {

                $taxAdjustment = $adjustmentFactory->createWithData(
                    AdjustmentInterface::TAX_ADJUSTMENT,
                    $taxRate->getName(),
                    (int) $taxCalculator->calculate($row['total'], $taxRate),
                    $neutral = true
                );

                $this->addSql('INSERT INTO sylius_adjustment (order_item_id, type, label, amount, is_neutral, is_locked, created_at, updated_at) VALUES (:order_item_id, :type, :label, :amount, :is_neutral, :is_locked, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                    'order_item_id' => $row['order_item_id'],
                    'type' => $taxAdjustment->getType(),
                    'label' => $taxAdjustment->getLabel(),
                    'amount' => $taxAdjustment->getAmount(),
                    'is_neutral' => $taxAdjustment->isNeutral() ? 't' : 'f',
                    'is_locked' => $taxAdjustment->isLocked() ? 't' : 'f',
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
