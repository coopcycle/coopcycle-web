<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180927085246 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_stripe_account (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, stripe_account_id INT DEFAULT NULL, livemode BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A13E3764B1E7706E ON restaurant_stripe_account (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_A13E3764E065F932 ON restaurant_stripe_account (stripe_account_id)');
        $this->addSql('CREATE UNIQUE INDEX restaurant_stripe_account_unique ON restaurant_stripe_account (restaurant_id, stripe_account_id, livemode)');
        $this->addSql('ALTER TABLE restaurant_stripe_account ADD CONSTRAINT FK_A13E3764B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_stripe_account ADD CONSTRAINT FK_A13E3764E065F932 FOREIGN KEY (stripe_account_id) REFERENCES stripe_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmts = [];

        $stmts['stripe_account'] = $this->connection->prepare('SELECT * FROM stripe_account');
        $stmts['restaurant'] = $this->connection->prepare('SELECT * FROM restaurant WHERE stripe_account_id = :stripe_account_id');

        $result = $stmts['stripe_account']->execute();
        while ($stripeAccount = $result->fetchAssociative()) {

            $stmts['restaurant']->bindParam('stripe_account_id', $stripeAccount['id']);
            $result2 = $stmts['restaurant']->execute();

            if ($result2->rowCount() === 1) {
                $restaurant = $result2->fetchAssociative();

                $this->addSql('INSERT INTO restaurant_stripe_account (restaurant_id, stripe_account_id, livemode) VALUES (:restaurant_id, :stripe_account_id, :livemode)', [
                    'restaurant_id' => $restaurant['id'],
                    'stripe_account_id' => $stripeAccount['id'],
                    'livemode' => $stripeAccount['livemode'] ? 't' : 'f',
                ]);
            }
        }

        $this->addSql('ALTER TABLE restaurant DROP stripe_account_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD stripe_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT fk_eb95123fe065f932 FOREIGN KEY (stripe_account_id) REFERENCES stripe_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_eb95123fe065f932 ON restaurant (stripe_account_id)');

        $stmt = $this->connection->prepare('SELECT * FROM restaurant_stripe_account');
        $result = $stmt->execute();

        $stripeAccountByRestaurant = [];
        while ($restaurantStripeAccount = $result->fetchAssociative()) {
            $stripeAccountByRestaurant[$restaurantStripeAccount['restaurant_id']][] = $restaurantStripeAccount;
        }

        foreach ($stripeAccountByRestaurant as $restaurantId => $stripeAccounts) {

            if (count($stripeAccounts) === 1) {
                $stripeAccount = current($stripeAccounts);
            } else {
                // If there are several linked accounts, we use livemode = true
                foreach ($stripeAccounts as $stripeAccount) {
                    if ($stripeAccount['livemode']) {
                        break;
                    }
                }
            }

            $this->addSql('UPDATE restaurant SET stripe_account_id = :stripe_account_id WHERE id = :restaurant_id', [
                'restaurant_id' => $restaurantId,
                'stripe_account_id' => $stripeAccount['stripe_account_id'],
            ]);
        }

        $this->addSql('DROP TABLE restaurant_stripe_account');
    }
}
