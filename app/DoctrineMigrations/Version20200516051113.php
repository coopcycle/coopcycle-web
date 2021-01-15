<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200516051113 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $stmts = [];
        $stmts['fulfillment_methods'] =
            $this->connection->prepare('SELECT restaurant_id, type, opening_hours, opening_hours_behavior FROM restaurant_fulfillment_method');

        $stmts['fulfillment_methods']->execute();

        $restaurantsFulfillmentMethods = [];
        while ($fulfillmentMethod = $stmts['fulfillment_methods']->fetch()) {
            $restaurantsFulfillmentMethods[$fulfillmentMethod['restaurant_id']][$fulfillmentMethod['type']]
                = $fulfillmentMethod;
        }

        foreach ($restaurantsFulfillmentMethods as $id => $restaurantFulfillmentMethods) {
            if (!isset($restaurantFulfillmentMethods['collection'])) {
                $this->addSql('INSERT INTO restaurant_fulfillment_method (restaurant_id, type, opening_hours, opening_hours_behavior, enabled) VALUES (:restaurant_id, \'collection\', :opening_hours, :opening_hours_behavior, \'false\')', [
                    'restaurant_id' => $id,
                    'opening_hours' => $restaurantFulfillmentMethods['delivery']['opening_hours'],
                    'opening_hours_behavior' => $restaurantFulfillmentMethods['delivery']['opening_hours_behavior'],
                ]);
            } else {
                $openingHours = json_decode($restaurantFulfillmentMethods['collection']['opening_hours'], true);
                if (count($openingHours) === 0) {
                    $this->addSql('UPDATE restaurant_fulfillment_method SET opening_hours = :opening_hours WHERE restaurant_id = :restaurant_id AND type = :type', [
                        'opening_hours' => $restaurantFulfillmentMethods['delivery']['opening_hours'],
                        'restaurant_id' => $id,
                        'type'          => 'collection',
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
