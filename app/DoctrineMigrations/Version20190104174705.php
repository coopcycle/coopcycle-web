<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190104174705 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $stmt = $this->connection->prepare('SELECT * FROM notification WHERE route_name = \'profile_restaurant_dashboard_order\'');

        $result = $stmt->execute();
        while ($notification = $result->fetchAssociative()) {

            $routeParameters = json_decode($notification['route_parameters'], true);

            $routeParameters['order'] = $routeParameters['orderId'];
            unset($routeParameters['orderId']);

            $this->addSql('UPDATE notification SET route_name = :route_name, route_parameters = :route_parameters WHERE id = :id', [
                'route_name' => 'profile_restaurant_dashboard',
                'route_parameters' => json_encode($routeParameters),
                'id' => $notification['id'],
            ]);
        }
    }

    public function down(Schema $schema) : void
    {

    }
}
