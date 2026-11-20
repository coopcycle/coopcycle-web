<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008134502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sylius_customer.referral_canonical_email, so the referral program can spot a signup that merely aliases an existing account';
    }

    public function up(Schema $schema): void
    {
        // Deliberately not unique: registering under an alias of an existing
        // account stays allowed, it just must not earn a referral reward.
        $this->addSql('ALTER TABLE sylius_customer ADD referral_canonical_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_customer_referral_canonical_email ON sylius_customer (referral_canonical_email)');

        // Existing rows are backfilled out of band, by
        // coopcycle:referral:backfill-canonical-emails, so the canonical form
        // is produced by the one implementation that owns it (EmailCanonizer)
        // rather than a second copy of those rules written in SQL.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_customer_referral_canonical_email');
        $this->addSql('ALTER TABLE sylius_customer DROP referral_canonical_email');
    }
}
