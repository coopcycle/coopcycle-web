<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009020838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add marketing campaigns and the record of who each one was sent to';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE marketing_campaign (id SERIAL NOT NULL, promotion_coupon_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, segment VARCHAR(64) NOT NULL, subject VARCHAR(255) DEFAULT NULL, body_mjml TEXT DEFAULT NULL, body_html TEXT DEFAULT NULL, status VARCHAR(16) DEFAULT \'draft\' NOT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sender_name VARCHAR(255) DEFAULT NULL, sender_email VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_62B9613717B24436 ON marketing_campaign (promotion_coupon_id)');
        // The scheduler asks for due campaigns by status and time.
        $this->addSql('CREATE INDEX idx_marketing_campaign_due ON marketing_campaign (status, scheduled_at)');

        $this->addSql('CREATE TABLE marketing_campaign_recipient (id SERIAL NOT NULL, campaign_id INT NOT NULL, customer_id INT DEFAULT NULL, email VARCHAR(255) NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, message_id VARCHAR(255) DEFAULT NULL, error VARCHAR(255) DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_313FC6B2F639F774 ON marketing_campaign_recipient (campaign_id)');
        $this->addSql('CREATE INDEX IDX_313FC6B29395C3F3 ON marketing_campaign_recipient (customer_id)');
        // What the frequency cap reads.
        $this->addSql('CREATE INDEX idx_campaign_recipient_email_sent_at ON marketing_campaign_recipient (email, sent_at)');
        // One row per address per campaign, so a retried send updates the
        // attempt rather than counting as another campaign against the cap.
        $this->addSql('CREATE UNIQUE INDEX uniq_campaign_recipient ON marketing_campaign_recipient (campaign_id, email)');

        $this->addSql('ALTER TABLE marketing_campaign ADD CONSTRAINT FK_62B9613717B24436 FOREIGN KEY (promotion_coupon_id) REFERENCES sylius_promotion_coupon (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE marketing_campaign_recipient ADD CONSTRAINT FK_313FC6B2F639F774 FOREIGN KEY (campaign_id) REFERENCES marketing_campaign (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Deleting a customer must not erase the fact a campaign went to them.
        $this->addSql('ALTER TABLE marketing_campaign_recipient ADD CONSTRAINT FK_313FC6B29395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE marketing_campaign_recipient DROP CONSTRAINT FK_313FC6B2F639F774');
        $this->addSql('ALTER TABLE marketing_campaign_recipient DROP CONSTRAINT FK_313FC6B29395C3F3');
        $this->addSql('ALTER TABLE marketing_campaign DROP CONSTRAINT FK_62B9613717B24436');
        $this->addSql('DROP TABLE marketing_campaign_recipient');
        $this->addSql('DROP TABLE marketing_campaign');
    }
}
