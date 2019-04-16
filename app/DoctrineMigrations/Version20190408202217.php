<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190408202217 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_promotion (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, priority INT NOT NULL, exclusive BOOLEAN NOT NULL, usage_limit INT DEFAULT NULL, used INT NOT NULL, coupon_based BOOLEAN NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F157396377153098 ON sylius_promotion (code)');
        $this->addSql('CREATE TABLE sylius_promotion_coupon (id SERIAL NOT NULL, promotion_id INT DEFAULT NULL, code VARCHAR(255) NOT NULL, usage_limit INT DEFAULT NULL, used INT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B04EBA8577153098 ON sylius_promotion_coupon (code)');
        $this->addSql('CREATE INDEX IDX_B04EBA85139DF194 ON sylius_promotion_coupon (promotion_id)');
        $this->addSql('CREATE TABLE sylius_promotion_action (id SERIAL NOT NULL, promotion_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, configuration TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_933D0915139DF194 ON sylius_promotion_action (promotion_id)');
        $this->addSql('COMMENT ON COLUMN sylius_promotion_action.configuration IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE sylius_promotion_rule (id SERIAL NOT NULL, promotion_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, configuration TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2C188EA8139DF194 ON sylius_promotion_rule (promotion_id)');
        $this->addSql('COMMENT ON COLUMN sylius_promotion_rule.configuration IS \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE sylius_promotion_coupon ADD CONSTRAINT FK_B04EBA85139DF194 FOREIGN KEY (promotion_id) REFERENCES sylius_promotion (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_promotion_action ADD CONSTRAINT FK_933D0915139DF194 FOREIGN KEY (promotion_id) REFERENCES sylius_promotion (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_promotion_rule ADD CONSTRAINT FK_2C188EA8139DF194 FOREIGN KEY (promotion_id) REFERENCES sylius_promotion (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE sylius_order ADD promotion_coupon_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F917B24436 FOREIGN KEY (promotion_coupon_id) REFERENCES sylius_promotion_coupon (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6196A1F917B24436 ON sylius_order (promotion_coupon_id)');

        $this->addSql('CREATE TABLE sylius_promotion_order (order_id INT NOT NULL, promotion_id INT NOT NULL, PRIMARY KEY(order_id, promotion_id))');
        $this->addSql('CREATE INDEX IDX_BF9CF6FB8D9F6D38 ON sylius_promotion_order (order_id)');
        $this->addSql('CREATE INDEX IDX_BF9CF6FB139DF194 ON sylius_promotion_order (promotion_id)');
        $this->addSql('ALTER TABLE sylius_promotion_order ADD CONSTRAINT FK_BF9CF6FB8D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_promotion_order ADD CONSTRAINT FK_BF9CF6FB139DF194 FOREIGN KEY (promotion_id) REFERENCES sylius_promotion (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE sylius_promotion_coupon ADD per_customer_usage_limit INT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE sylius_promotion_order');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F917B24436');
        $this->addSql('DROP INDEX IDX_6196A1F917B24436');
        $this->addSql('ALTER TABLE sylius_order DROP promotion_coupon_id');

        $this->addSql('ALTER TABLE sylius_promotion_coupon DROP CONSTRAINT FK_B04EBA85139DF194');
        $this->addSql('ALTER TABLE sylius_promotion_action DROP CONSTRAINT FK_933D0915139DF194');
        $this->addSql('ALTER TABLE sylius_promotion_rule DROP CONSTRAINT FK_2C188EA8139DF194');
        $this->addSql('DROP TABLE sylius_promotion');
        $this->addSql('DROP TABLE sylius_promotion_coupon');
        $this->addSql('DROP TABLE sylius_promotion_action');
        $this->addSql('DROP TABLE sylius_promotion_rule');
    }
}
