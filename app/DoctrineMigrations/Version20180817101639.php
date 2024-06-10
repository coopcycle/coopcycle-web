<?php declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\Entity\Address;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\GeoUtils;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180817101639 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private function createOrder($data)
    {
        $orderFactory = $this->container->get('sylius.factory.order');

        $restaurantAddress = new Address();
        $restaurantAddress->setGeo(
            GeoUtils::asGeoCoordinates($data['restaurant']['address']['latlng'])
        );

        $shippingAddress = new Address();
        $shippingAddress->setGeo(
            GeoUtils::asGeoCoordinates($data['shipping_address']['latlng'])
        );

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);

        $order = $orderFactory->createForRestaurant($restaurant);
        $order->setShippingAddress($shippingAddress);
        $order->setShippedAt(new \DateTime($data['shipped_at']));

        return $order;
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_order_timeline (id SERIAL NOT NULL, order_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, dropoff_expected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, pickup_expected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, preparation_expected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7B3C8E2C8D9F6D38 ON sylius_order_timeline (order_id)');
        $this->addSql('ALTER TABLE sylius_order_timeline ADD CONSTRAINT FK_7B3C8E2C8D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $orderTimelineCalculator = $this->container->get('coopcycle.order_timeline_calculator');

        $stmt = [];

        $stmt['orders'] = $this->connection->prepare('SELECT id, restaurant_id, shipping_address_id, shipped_at FROM sylius_order WHERE restaurant_id IS NOT NULL AND state != \'cart\'');
        $stmt['address'] = $this->connection->prepare('SELECT ST_AsText(geo) AS latlng FROM address WHERE id = :address_id');
        $stmt['restaurant_address'] =
            $this->connection->prepare('SELECT ST_AsText(address.geo) AS latlng FROM restaurant JOIN address ON restaurant.address_id = address.id WHERE restaurant.id = :restaurant_id');

        $result = $stmt['orders']->execute();

        while ($data = $result->fetchAssociative()) {

            $stmt['restaurant_address']->bindParam('restaurant_id', $data['restaurant_id']);
            $result2 = $stmt['restaurant_address']->execute();

            $stmt['address']->bindParam('address_id', $data['shipping_address_id']);
            $result3 = $stmt['address']->execute();

            $restaurantAddressData = $result2->fetch();
            $shippingAddressData = $result3->fetch();

            $data['shipping_address'] = [
                'latlng' => $shippingAddressData['latlng']
            ];
            $data['restaurant'] = [
                'address' => [
                    'latlng' => $restaurantAddressData['latlng']
                ]
            ];

            $order = $this->createOrder($data);

            $timeline = $orderTimelineCalculator->calculate($order);

            $this->addSql('INSERT INTO sylius_order_timeline (order_id, created_at, updated_at, dropoff_expected_at, pickup_expected_at, preparation_expected_at) VALUES (:order_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :dropoff_expected_at, :pickup_expected_at, :preparation_expected_at)', [
                'order_id' => $data['id'],
                'dropoff_expected_at' => $timeline->getDropoffExpectedAt()->format('Y-m-d H:i:s'),
                'pickup_expected_at' => $timeline->getPickupExpectedAt()->format('Y-m-d H:i:s'),
                'preparation_expected_at' => $timeline->getPreparationExpectedAt()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE sylius_order_timeline');
    }
}
