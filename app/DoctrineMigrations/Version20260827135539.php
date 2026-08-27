<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827135539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the Shopify refresh token and access token expiry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_shop ADD refresh_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE shopify_shop ADD access_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_shop DROP refresh_token');
        $this->addSql('ALTER TABLE shopify_shop DROP access_token_expires_at');
    }
}
