<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use libphonenumber\NumberParseException;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180530092507 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $stmt = $this->connection->prepare("SELECT * FROM restaurant where telephone is not null");
        $result = $stmt->execute();

        $phoneNumberUtil = $this->container->get('libphonenumber\PhoneNumberUtil');

        while ($restaurant = $result->fetchAssociative()) {
            try {
                $phoneNumberUtil->parse($restaurant['telephone'], strtoupper($this->container->getParameter('country_iso')));
            } catch (NumberParseException $e) {
                $this->addSql("UPDATE restaurant SET telephone = NULL WHERE id = :id", $restaurant);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
