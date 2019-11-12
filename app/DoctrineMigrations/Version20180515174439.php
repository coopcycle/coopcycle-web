<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180515174439 extends AbstractMigration
{
    private $stmt = [];

    private function getSetting($name)
    {
        $this->stmt['setting']->bindParam('name', $name);
        $this->stmt['setting']->execute();

        if ($this->stmt['setting']->rowCount() === 1) {
            $setting = $this->stmt['setting']->fetch();

            return $setting['value'];
        }
    }

    private function addSetting($name, $value)
    {
        $this->addSql('INSERT INTO craue_config_setting (name, section, value) VALUES (:name, :section, :value)', [
            'name' => $name,
            'section' => 'general',
            'value' => $value,
        ]);
    }

    private function deleteSetting($name)
    {
        $this->addSql('DELETE FROM craue_config_setting WHERE name = :name', [
            'name' => $name,
        ]);
    }

    public function up(Schema $schema) : void
    {
        $this->stmt = [];
        $this->stmt['setting'] = $this->connection->prepare('SELECT * FROM craue_config_setting WHERE name = :name');

        $isLivePublishableKey = false;
        if ($stripePublishableKey = $this->getSetting('stripe_publishable_key')) {
            $isLivePublishableKey = (1 === preg_match('/^pk_live_.*/', $stripePublishableKey));
            if ($isLivePublishableKey) {
                $this->addSetting('stripe_live_publishable_key', $stripePublishableKey);
            } else {
                $this->addSetting('stripe_test_publishable_key', $stripePublishableKey);
            }
            $this->deleteSetting('stripe_publishable_key');
        }

        $isLiveSecretKey = false;
        if ($stripeSecretKey = $this->getSetting('stripe_secret_key')) {
            $isLiveSecretKey = (1 === preg_match('/^sk_live_.*/', $stripeSecretKey));
            if ($isLiveSecretKey) {
                $this->addSetting('stripe_live_secret_key', $stripeSecretKey);
            } else {
                $this->addSetting('stripe_test_secret_key', $stripeSecretKey);
            }
            $this->deleteSetting('stripe_secret_key');
        }

        if ($stripeConnectClientId = $this->getSetting('stripe_connect_client_id')) {
            // If both keys are live, we suppose the client id is live too
            if ($isLivePublishableKey && $isLiveSecretKey) {
                $this->addSetting('stripe_live_connect_client_id', $stripeConnectClientId);
            } else {
                $this->addSetting('stripe_test_connect_client_id', $stripeConnectClientId);
            }
            $this->deleteSetting('stripe_connect_client_id');
        }

        $this->addSetting('stripe_livemode', ($isLivePublishableKey && $isLiveSecretKey) ? 'yes' : 'no');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
