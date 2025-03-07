<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240703123801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE trailer (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, max_volume_units INT NOT NULL, max_weight INT NOT NULL, color VARCHAR(7) NOT NULL, is_electric BOOLEAN NOT NULL, electric_range INT, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE vehicle_trailer (id SERIAL NOT NULL, trailer_id INT NOT NULL, vehicle_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D9E28121B6C04CFD ON vehicle_trailer (trailer_id)');
        $this->addSql('CREATE INDEX IDX_D9E28121545317D1 ON vehicle_trailer (vehicle_id)');
        $this->addSql('CREATE TABLE warehouse (id SERIAL NOT NULL, address_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ECB38BFCF5B7AF75 ON warehouse (address_id)');
        $this->addSql('ALTER TABLE vehicle_trailer ADD CONSTRAINT FK_D9E28121B6C04CFD FOREIGN KEY (trailer_id) REFERENCES trailer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vehicle_trailer ADD CONSTRAINT FK_D9E28121545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE warehouse ADD CONSTRAINT FK_ECB38BFCF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vehicle ADD warehouse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicle ADD color VARCHAR(7)');
        $this->addSql('ALTER TABLE vehicle ADD is_electric BOOLEAN');
        $this->addSql('ALTER TABLE vehicle ADD electric_range INT');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E4865080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1B80E4865080ECDE ON vehicle (warehouse_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vehicle DROP CONSTRAINT FK_1B80E4865080ECDE');
        $this->addSql('ALTER TABLE vehicle_trailer DROP CONSTRAINT FK_D9E28121B6C04CFD');
        $this->addSql('ALTER TABLE vehicle_trailer DROP CONSTRAINT FK_D9E28121545317D1');
        $this->addSql('ALTER TABLE warehouse DROP CONSTRAINT FK_ECB38BFCF5B7AF75');
        $this->addSql('DROP TABLE trailer');
        $this->addSql('DROP TABLE vehicle_trailer');
        $this->addSql('DROP TABLE warehouse');
        $this->addSql('DROP INDEX IDX_1B80E4865080ECDE');
        $this->addSql('ALTER TABLE vehicle DROP warehouse_id');
        $this->addSql('ALTER TABLE vehicle DROP color');
        $this->addSql('ALTER TABLE vehicle DROP is_electric');
        $this->addSql('ALTER TABLE vehicle DROP electric_range');
    }
}
