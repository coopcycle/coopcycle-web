<?php

declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\Utils\DateUtils;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200409145804 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $stmt = $this->connection->prepare("SELECT * FROM sylius_order");
        $stmt->execute();

        while ($order = $stmt->fetch()) {

            if (null !== $order['shipped_at']) {
                $shippingTimeRange =
                    DateUtils::dateTimeToTsRange(new \DateTime($order['shipped_at']));

                $this->addSql('UPDATE sylius_order SET shipping_time_range = CAST(:shipping_time_range AS tsrange) WHERE id = :id', [
                    'shipping_time_range' => sprintf('[%s, %s]',
                        $shippingTimeRange->getLower()->format('Y-m-d H:i:s'),
                        $shippingTimeRange->getUpper()->format('Y-m-d H:i:s')
                    ),
                    'id' => $order['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {

    }
}
