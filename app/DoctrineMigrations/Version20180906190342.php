<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Stripe;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180906190342 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $settingsManager;

    private function getLiveAccounts()
    {
        Stripe\Stripe::setApiKey($this->settingsManager->get('stripe_live_secret_key'));

        $accountIds = [];

        $accounts = Stripe\Account::all();
        foreach ($accounts->autoPagingIterator() as $account) {
            $accountIds[] = $account->id;
        }

        return $accountIds;
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->settingsManager = $this->container->get('coopcycle.settings_manager');

        $this->addSql('ALTER TABLE stripe_account ADD livemode BOOLEAN DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT * FROM stripe_account');

        $liveAccounts = $this->settingsManager->canEnableStripeLivemode() ? $this->getLiveAccounts() : [];

        $result = $stmt->execute();
        while ($stripeAccount = $result->fetchAssociative()) {
            $this->addSql('UPDATE stripe_account SET livemode = :livemode WHERE id = :id', [
                'livemode' => in_array($stripeAccount['stripe_user_id'], $liveAccounts) ? 't' : 'f',
                'id' => $stripeAccount['id']
            ]);
        }

        $this->addSql('ALTER TABLE stripe_account ALTER livemode SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE stripe_account DROP livemode');
    }
}
