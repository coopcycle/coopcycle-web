<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191113125855 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_payment ADD order_id INT NOT NULL');
        $this->addSql('ALTER TABLE sylius_payment ADD CONSTRAINT FK_D9191BD48D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D9191BD48D9F6D38 ON sylius_payment (order_id)');

        $stmt = $this->connection->prepare("SELECT id FROM sylius_payment_method WHERE code = 'STRIPE'");
        $result = $stmt->execute();

        $stripeMethod = $result->fetchAssociative();

        $this->addSql('INSERT INTO sylius_payment (id, method_id, currency_code, amount, state, details, created_at, updated_at, order_id) SELECT id, :stripe_payment_method_id, currency_code, amount, state, details, created_at, updated_at, order_id FROM stripe_payment', [
            'stripe_payment_method_id' => $stripeMethod['id']
        ]);

        $stmt = $this->connection->prepare("SELECT last_value FROM stripe_payment_id_seq");
        $result = $stmt->execute();

        $latestId = $result->fetchOne();
        $latestId = intval($latestId);

        $this->addSql("SELECT SETVAL('sylius_payment_id_seq', {$latestId}, true)");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_payment DROP CONSTRAINT FK_D9191BD48D9F6D38');
        $this->addSql('DROP INDEX IDX_D9191BD48D9F6D38');
        $this->addSql('ALTER TABLE sylius_payment DROP order_id');
    }
}
