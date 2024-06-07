<?php

declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\Utils\DateUtils;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200408072930 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order ADD shipping_time_range TSRANGE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN sylius_order.shipping_time_range IS \'(DC2Type:tsrange)\'');

        $stmt = $this->connection->prepare("SELECT * FROM sylius_order");
        $result = $stmt->execute();

        while ($order = $result->fetchAssociative()) {

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
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order DROP shipping_time_range');
    }
}
