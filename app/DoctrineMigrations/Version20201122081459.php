<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201122081459 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD pledge_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F65EFFCCF FOREIGN KEY (pledge_id) REFERENCES restaurant_pledge (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB95123F65EFFCCF ON restaurant (pledge_id)');

        $this->addSql('UPDATE restaurant r SET pledge_id = p.id FROM restaurant_pledge p WHERE r.id = p.restaurant_id');

        $this->addSql('ALTER TABLE restaurant_pledge DROP CONSTRAINT fk_43fec5a5b1e7706e');
        $this->addSql('DROP INDEX uniq_43fec5a5b1e7706e');
        $this->addSql('ALTER TABLE restaurant_pledge DROP restaurant_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant_pledge ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant_pledge ADD CONSTRAINT fk_43fec5a5b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_43fec5a5b1e7706e ON restaurant_pledge (restaurant_id)');

        $this->addSql('UPDATE restaurant_pledge p SET restaurant_id = r.id FROM restaurant r WHERE p.id = r.pledge_id');

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F65EFFCCF');
        $this->addSql('DROP INDEX UNIQ_EB95123F65EFFCCF');
        $this->addSql('ALTER TABLE restaurant DROP pledge_id');
    }
}
