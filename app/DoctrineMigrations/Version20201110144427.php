<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201110144427 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant_fulfillment_method ADD ordering_delay_minutes INT DEFAULT 0 NOT NULL');

        $stmt = $this->connection->prepare('SELECT r.ordering_delay_minutes, rfm.method_id FROM restaurant r JOIN restaurant_fulfillment_methods rfm ON r.id = rfm.restaurant_id');
        $result = $stmt->execute();

        while ($restaurant = $result->fetchAssociative()) {
            $this->addSql('UPDATE restaurant_fulfillment_method SET ordering_delay_minutes = :ordering_delay_minutes WHERE id = :method_id', [
                'ordering_delay_minutes' => $restaurant['ordering_delay_minutes'],
                'method_id' => $restaurant['method_id'],
            ]);
        }

        $this->addSql('ALTER TABLE restaurant DROP ordering_delay_minutes');

        $stmt = $this->connection->prepare('SELECT h.ordering_delay_minutes, hfm.method_id FROM hub h JOIN hub_fulfillment_method hfm ON h.id = hfm.hub_id');
        $result = $stmt->execute();

        while ($hub = $result->fetchAssociative()) {
            $this->addSql('UPDATE restaurant_fulfillment_method SET ordering_delay_minutes = :ordering_delay_minutes WHERE id = :method_id', [
                'ordering_delay_minutes' => $hub['ordering_delay_minutes'],
                'method_id' => $hub['method_id'],
            ]);
        }

        $this->addSql('ALTER TABLE hub DROP ordering_delay_minutes');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD ordering_delay_minutes INT DEFAULT 0 NOT NULL');

        $stmt = $this->connection->prepare('SELECT ordering_delay_minutes, rfm.restaurant_id FROM restaurant r JOIN restaurant_fulfillment_methods rfm ON r.id = rfm.restaurant_id JOIN restaurant_fulfillment_method fm ON rfm.method_id = fm.id WHERE fm.type = \'delivery\'');
        $result = $stmt->execute();

        while ($restaurant = $result->fetchAssociative()) {
            $this->addSql('UPDATE restaurant SET ordering_delay_minutes = :ordering_delay_minutes WHERE id = :restaurant_id', [
                'ordering_delay_minutes' => $restaurant['ordering_delay_minutes'],
                'restaurant_id' => $restaurant['restaurant_id'],
            ]);
        }

        $this->addSql('ALTER TABLE hub ADD ordering_delay_minutes INT DEFAULT 0 NOT NULL');

        $stmt = $this->connection->prepare('SELECT ordering_delay_minutes, hfm.hub_id FROM hub h JOIN hub_fulfillment_method hfm ON h.id = hfm.hub_id JOIN restaurant_fulfillment_method fm ON hfm.method_id = fm.id WHERE fm.type = \'delivery\'');
        $result = $stmt->execute();

        while ($hub = $result->fetchAssociative()) {
            $this->addSql('UPDATE hub SET ordering_delay_minutes = :ordering_delay_minutes WHERE id = :hub_id', [
                'ordering_delay_minutes' => $hub['ordering_delay_minutes'],
                'hub_id' => $hub['hub_id'],
            ]);
        }

        $this->addSql('ALTER TABLE restaurant_fulfillment_method DROP ordering_delay_minutes');
    }
}
