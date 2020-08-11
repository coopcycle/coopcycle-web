<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200810135535 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $stmt = $this->connection->prepare('SELECT id, floor, description FROM address WHERE floor IS NOT NULL AND floor != \'\'');

        $stmt->execute();

        while ($address = $stmt->fetch()) {
            $this->addSql('UPDATE address SET description = :description WHERE id = :id', [
                'description' => sprintf('%s - %s', $address['description'], $address['floor']),
                'id' => $address['id'],
            ]);
        }

        $this->addSql('ALTER TABLE address DROP floor');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE address ADD floor VARCHAR(255) DEFAULT NULL');
    }
}
