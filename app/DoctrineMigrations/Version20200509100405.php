<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200509100405 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE restaurant_fulfillment_method ADD opening_hours_behavior VARCHAR(16) NOT NULL');
        $this->addSql('ALTER TABLE restaurant_fulfillment_method ADD enabled BOOLEAN DEFAULT \'true\' NOT NULL');

        $stmts = [];
        $stmts['restaurants'] = $this->connection->prepare('SELECT id, opening_hours, opening_hours_behavior, takeaway_enabled FROM restaurant');

        $stmts['restaurants']->execute();
        while ($restaurant = $stmts['restaurants']->fetch()) {

            $fulfillmentMethods = ['delivery'];
            if ($restaurant['takeaway_enabled']) {
                $fulfillmentMethods[] = 'collection';
            }

            foreach ($fulfillmentMethods as $fulfillmentMethod) {
                $this->addSql('INSERT INTO restaurant_fulfillment_method (restaurant_id, type, opening_hours, opening_hours_behavior, enabled) VALUES (:restaurant_id, :type, :opening_hours, :opening_hours_behavior, \'true\')' , [
                    'restaurant_id' => $restaurant['id'],
                    'type' => $fulfillmentMethod,
                    'opening_hours' => $restaurant['opening_hours'],
                    'opening_hours_behavior' => $restaurant['opening_hours_behavior'],
                ]);
            }
        }

        // TODO Keep opening hours
        $this->addSql('ALTER TABLE restaurant DROP opening_hours');
        $this->addSql('ALTER TABLE restaurant DROP opening_hours_behavior');
        $this->addSql('ALTER TABLE restaurant DROP takeaway_enabled');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE restaurant ADD opening_hours JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD opening_hours_behavior VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD takeaway_enabled BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.opening_hours IS \'(DC2Type:json_array)\'');

        $stmts = [];
        $stmts['restaurants'] = $this->connection->prepare('SELECT * FROM restaurant');
        $stmts['fulfillment_methods'] = $this->connection->prepare('SELECT * FROM restaurant_fulfillment_method WHERE restaurant_id = :restaurant_id');

        $stmts['restaurants']->execute();
        while ($restaurant = $stmts['restaurants']->fetch()) {

            $stmts['fulfillment_methods']->bindParam('restaurant_id', $restaurant['id']);
            $stmts['fulfillment_methods']->execute();

            $fulfillmentMethods = [];
            $takeawayEnabled = false;
            while ($fulfillmentMethod = $stmts['fulfillment_methods']->fetch()) {
                $fulfillmentMethods[] = $fulfillmentMethod;
                if ($fulfillmentMethod['type'] === 'collection') {
                    $takeawayEnabled = true;
                }
            }

            foreach ($fulfillmentMethods as $fulfillmentMethod) {
                if ($fulfillmentMethod['type'] === 'delivery') {
                    $this->addSql('UPDATE restaurant SET opening_hours = :opening_hours, opening_hours_behavior = :opening_hours_behavior, takeaway_enabled = :takeaway_enabled WHERE id = :restaurant_id', [
                        'opening_hours' => $fulfillmentMethod['opening_hours'],
                        'opening_hours_behavior' => $fulfillmentMethod['opening_hours_behavior'],
                        'takeaway_enabled' => $takeawayEnabled,
                        'restaurant_id' => $restaurant['id'],
                    ]);
                }
            }
        }

        $this->addSql('ALTER TABLE restaurant ALTER COLUMN opening_hours_behavior SET NOT NULL');

        $this->addSql('ALTER TABLE restaurant_fulfillment_method DROP opening_hours_behavior');
        $this->addSql('ALTER TABLE restaurant_fulfillment_method DROP enabled');
    }
}
