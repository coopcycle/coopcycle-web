<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200820130421 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE users_organization (user_id INT NOT NULL, organization_id INT NOT NULL, PRIMARY KEY(user_id, organization_id))');
        $this->addSql('CREATE INDEX IDX_92AA4570A76ED395 ON users_organization (user_id)');
        $this->addSql('CREATE INDEX IDX_92AA457032C8A3DE ON users_organization (organization_id)');
        $this->addSql('ALTER TABLE users_organization ADD CONSTRAINT FK_92AA4570A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users_organization ADD CONSTRAINT FK_92AA457032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE users_organization');
        $this->addSql('ALTER TABLE organization ADD group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD config_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_c1ee637cfe54d947 ON organization (group_id)');
    }
}
