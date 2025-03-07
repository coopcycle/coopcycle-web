<?php

declare(strict_types=1);

namespace Application\Migrations;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210125112408 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order_timeline ADD preparation_time VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order_timeline ADD shipping_time VARCHAR(255) DEFAULT NULL');

        $getTimelines = $this->connection->prepare('SELECT * FROM sylius_order_timeline');
        $result = $getTimelines->execute();

        while ($timeline = $result->fetchAssociative()) {

            $preparation = new \DateTime($timeline['preparation_expected_at']);
            $pickup = new \DateTime($timeline['pickup_expected_at']);

            $seconds = Carbon::instance($pickup)->diffInSeconds(Carbon::instance($preparation));
            $preparationTime = CarbonInterval::seconds($seconds)->cascade()->forHumans();

            $shippingTime = null;
            if (!empty($timeline['dropoff_expected_at'])) {
                $dropoff = new \DateTime($timeline['dropoff_expected_at']);

                $seconds = Carbon::instance($dropoff)->diffInSeconds(Carbon::instance($pickup));
                $shippingTime = CarbonInterval::seconds($seconds)->cascade()->forHumans();
            }


            if ($shippingTime) {
                $this->addSql('UPDATE sylius_order_timeline SET preparation_time = :preparation_time, shipping_time = :shipping_time WHERE id = :id', [
                    'preparation_time' => $preparationTime,
                    'shipping_time' => $shippingTime,
                    'id' => $timeline['id']
                ]);
            } else {
                $this->addSql('UPDATE sylius_order_timeline SET preparation_time = :preparation_time WHERE id = :id', [
                    'preparation_time' => $preparationTime,
                    'id' => $timeline['id']
                ]);
            }
        }

        $this->addSql('ALTER TABLE sylius_order_timeline ALTER COLUMN preparation_time SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order_timeline DROP preparation_time');
        $this->addSql('ALTER TABLE sylius_order_timeline DROP shipping_time');
    }
}
