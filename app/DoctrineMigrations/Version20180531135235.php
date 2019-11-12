<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180531135235 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $stmt = $this->connection->prepare("SELECT * FROM store where telephone is not null");
        $stmt->execute();

        $phoneNumberUtil = $this->container->get('libphonenumber\PhoneNumberUtil');

        while ($restaurant = $stmt->fetch()) {
            try {
                $phoneNumber = $phoneNumberUtil->parse($restaurant['telephone'], strtoupper($this->container->getParameter('country_iso')));
            } catch (NumberParseException $e) {
                $this->addSql("UPDATE store SET telephone = NULL WHERE id = :id", $restaurant);
                break;
            }

            $phoneNumber = $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164);
            $this->addSql(
                "UPDATE store SET telephone = :telephone WHERE id = :id",
                ['id' => $restaurant['id'], 'telephone' => $phoneNumber]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
