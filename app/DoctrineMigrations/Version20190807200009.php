<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190807200009 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE package (id SERIAL NOT NULL, package_set_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, volume_units INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DE6867952E007EC4 ON package (package_set_id)');
        $this->addSql('CREATE TABLE package_set (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE package ADD CONSTRAINT FK_DE6867952E007EC4 FOREIGN KEY (package_set_id) REFERENCES package_set (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO package_set (name, created_at, updated_at) VALUES (:name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
            'name' => 'Default'
        ]);

        $this->addSql('INSERT INTO package (package_set_id, name, volume_units, created_at, updated_at) SELECT id, :name, :volume_units, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM package_set WHERE name = :package_set_name', [
            'package_set_name' => 'Default',
            'name' => 'S',
            'volume_units' => 1,
        ]);
        $this->addSql('INSERT INTO package (package_set_id, name, volume_units, created_at, updated_at) SELECT id, :name, :volume_units, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM package_set WHERE name = :package_set_name', [
            'package_set_name' => 'Default',
            'name' => 'M',
            'volume_units' => 2
        ]);
        $this->addSql('INSERT INTO package (package_set_id, name, volume_units, created_at, updated_at) SELECT id, :name, :volume_units, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM package_set WHERE name = :package_set_name', [
            'package_set_name' => 'Default',
            'name' => 'L',
            'volume_units' => 4
        ]);
        $this->addSql('INSERT INTO package (package_set_id, name, volume_units, created_at, updated_at) SELECT id, :name, :volume_units, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM package_set WHERE name = :package_set_name', [
            'package_set_name' => 'Default',
            'name' => 'XL',
            'volume_units' => 6
        ]);
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE package DROP CONSTRAINT FK_DE6867952E007EC4');
        $this->addSql('DROP TABLE package');
        $this->addSql('DROP TABLE package_set');
    }
}
