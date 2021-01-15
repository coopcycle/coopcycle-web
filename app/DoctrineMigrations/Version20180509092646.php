<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180509092646 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('UPDATE contract SET minimum_cart_amount = minimum_cart_amount * 100');
        $this->addSql('UPDATE contract SET flat_delivery_price = flat_delivery_price * 100');

        $this->addSql('ALTER TABLE contract ALTER minimum_cart_amount TYPE INT');
        $this->addSql('ALTER TABLE contract ALTER minimum_cart_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE contract ALTER flat_delivery_price TYPE INT');
        $this->addSql('ALTER TABLE contract ALTER flat_delivery_price DROP DEFAULT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract ALTER minimum_cart_amount TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE contract ALTER minimum_cart_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE contract ALTER flat_delivery_price TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE contract ALTER flat_delivery_price DROP DEFAULT');

        $this->addSql('UPDATE contract SET minimum_cart_amount = minimum_cart_amount / 100');
        $this->addSql('UPDATE contract SET flat_delivery_price = flat_delivery_price / 100');
    }
}
