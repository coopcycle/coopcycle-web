<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220615192623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD mercadopago_connect_roles JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.mercadopago_connect_roles IS \'(DC2Type:json_array)\'');
        $this->addSql('UPDATE restaurant SET mercadopago_connect_roles = :mercadopago_connect_roles', [
            'mercadopago_connect_roles' => json_encode(['ROLE_ADMIN'])
        ]);
        $this->addSql('ALTER TABLE restaurant ALTER mercadopago_connect_roles SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant DROP mercadopago_connect_roles');
    }
}
