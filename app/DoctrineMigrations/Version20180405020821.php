<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180405020821 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE stripe_payment DROP CONSTRAINT FK_42EFB5F78D9F6D38');
        $this->addSql('ALTER TABLE stripe_payment ALTER order_id SET NOT NULL');
        $this->addSql('ALTER TABLE stripe_payment ADD CONSTRAINT FK_42EFB5F78D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE stripe_payment DROP CONSTRAINT fk_42efb5f78d9f6d38');
        $this->addSql('ALTER TABLE stripe_payment ALTER order_id DROP NOT NULL');
        $this->addSql('ALTER TABLE stripe_payment ADD CONSTRAINT fk_42efb5f78d9f6d38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
