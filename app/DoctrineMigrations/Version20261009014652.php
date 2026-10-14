<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009014652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_suppression, mirroring the addresses Postmark will no longer deliver to';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_suppression (id SERIAL NOT NULL, email VARCHAR(255) NOT NULL, message_stream VARCHAR(64) NOT NULL, reason VARCHAR(32) NOT NULL, suppressed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        // Audience filtering looks an address up across every stream.
        $this->addSql('CREATE INDEX idx_email_suppression_email ON email_suppression (email)');
        // Suppressions are per stream at Postmark, and so here: unsubscribing
        // from marketing must not stop someone's order receipts.
        $this->addSql('CREATE UNIQUE INDEX uniq_email_suppression_email_stream ON email_suppression (email, message_stream)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_suppression');
    }
}
