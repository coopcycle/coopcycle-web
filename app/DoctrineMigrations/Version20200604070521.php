<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200604070521 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant_fulfillment_method ADD minimum_amount INT DEFAULT NULL');

        $stmts = [];
        $stmts['contract'] =
            $this->connection->prepare('SELECT restaurant_id, minimum_cart_amount FROM contract');

        $stmts['contract']->execute();

        while ($contract = $stmts['contract']->fetch()) {
            $this->addSql('UPDATE restaurant_fulfillment_method SET minimum_amount = :minimum_amount WHERE restaurant_id = :restaurant_id', [
                'minimum_amount' => $contract['minimum_cart_amount'],
                'restaurant_id'  => $contract['restaurant_id'],
            ]);
        }

        $this->addSql('ALTER TABLE restaurant_fulfillment_method ALTER minimum_amount SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant_fulfillment_method DROP minimum_amount');
    }
}
