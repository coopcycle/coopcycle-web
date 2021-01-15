<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200418061342 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_promotion (restaurant_id INT NOT NULL, promotion_id INT NOT NULL, PRIMARY KEY(restaurant_id, promotion_id))');
        $this->addSql('CREATE INDEX IDX_F18827F6B1E7706E ON restaurant_promotion (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F18827F6139DF194 ON restaurant_promotion (promotion_id)');
        $this->addSql('ALTER TABLE restaurant_promotion ADD CONSTRAINT FK_F18827F6B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_promotion ADD CONSTRAINT FK_F18827F6139DF194 FOREIGN KEY (promotion_id) REFERENCES sylius_promotion (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE restaurant_promotion');
    }
}
