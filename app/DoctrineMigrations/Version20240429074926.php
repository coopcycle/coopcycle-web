<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240429074926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'ALTER TABLE "delivery_form_submission"  DROP CONSTRAINT "fk_6404364a55e824a3", ADD CONSTRAINT "fk_6404364a55e824a3" FOREIGN KEY ("delivery_form_id") REFERENCES "delivery_form"(id) ON DELETE CASCADE;'
        );
    }

    public function down(Schema $schema): void
    {}
}
