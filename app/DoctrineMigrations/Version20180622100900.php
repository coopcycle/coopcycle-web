<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180622100900 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $stmt = $this->connection->prepare("SELECT id, expression FROM pricing_rule");
        $result = $stmt->execute();

        while ($pricingRule = $result->fetchAssociative()) {
            if (false !== strpos($pricingRule['expression'], 'deliveryAddress')) {
                $expression = str_replace('deliveryAddress', 'dropoff.address', $pricingRule['expression']);
                $this->addSql('UPDATE pricing_rule SET expression = :expression WHERE id = :id', [
                    'id' => $pricingRule['id'],
                    'expression' => $expression
                ]);
            }
        }

        $stmt = $this->connection->prepare("SELECT id, delivery_perimeter_expression FROM restaurant");
        $result = $stmt->execute();

        while ($restaurant = $result->fetchAssociative()) {
            if (false !== strpos($restaurant['delivery_perimeter_expression'], 'deliveryAddress')) {
                $expression = str_replace('deliveryAddress', 'dropoff.address', $restaurant['delivery_perimeter_expression']);
                $this->addSql('UPDATE restaurant SET delivery_perimeter_expression = :expression WHERE id = :id', [
                    'id' => $restaurant['id'],
                    'expression' => $expression
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        $stmt = $this->connection->prepare("SELECT id, expression FROM pricing_rule");
        $result = $stmt->execute();

        while ($pricingRule = $result->fetchAssociative()) {
            if (false !== strpos($pricingRule['expression'], 'dropoff.address')) {
                $expression = str_replace('dropoff.address', 'deliveryAddress', $pricingRule['expression']);
                $this->addSql('UPDATE pricing_rule SET expression = :expression WHERE id = :id', [
                    'id' => $pricingRule['id'],
                    'expression' => $expression
                ]);
            }
        }

        $stmt = $this->connection->prepare("SELECT id, delivery_perimeter_expression FROM restaurant");
        $result = $stmt->execute();

        while ($restaurant = $result->fetchAssociative()) {
            if (false !== strpos($restaurant['delivery_perimeter_expression'], 'dropoff.address')) {
                $expression = str_replace('dropoff.address', 'deliveryAddress', $restaurant['delivery_perimeter_expression']);
                $this->addSql('UPDATE restaurant SET delivery_perimeter_expression = :expression WHERE id = :id', [
                    'id' => $restaurant['id'],
                    'expression' => $expression
                ]);
            }
        }

    }
}
