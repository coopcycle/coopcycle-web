<?php

declare(strict_types=1);

namespace Application\Migrations;

use AppBundle\Service\StripeManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Stripe;

final class Version20200826135116 extends AbstractMigration
{

    public function getDescription() : string
    {
        return '';
    }

    private function getSetting($name)
    {
        $stmt = $this->connection->executeQuery(
            'SELECT value FROM craue_config_setting WHERE name = :name',
            [ 'name' => $name ]
        );

        return $stmt->fetchOne();
    }

    private function configureStripe(): bool
    {
        $livemode = filter_var($this->getSetting('stripe_livemode'), FILTER_VALIDATE_BOOLEAN);
        $secretKey = $this->getSetting(sprintf('stripe_%s_secret_key', $livemode ? 'live' : 'test'));

        if (!$secretKey) {
            return false;
        }

        Stripe\Stripe::setApiKey($secretKey);
        Stripe\Stripe::setApiVersion(StripeManager::STRIPE_API_VERSION);

        return true;
    }

    public function up(Schema $schema) : void
    {
        if (!$this->configureStripe()) {
            return;
        }

        $stmt = $this->connection->prepare('SELECT id, details FROM sylius_payment WHERE details::jsonb ?? \'refunds\'');

        $result = $stmt->execute();

        while ($payment = $result->fetchAssociative()) {
            $details = json_decode($payment['details'], true);

            $stripeOptions = [];

            if (isset($details['stripe_user_id'])) {
                $stripeOptions['stripe_account'] = $details['stripe_user_id'];
            }

            foreach ($details['refunds'] as $refund) {

                try {
                    $stripeRefund = Stripe\Refund::retrieve(
                        $refund['id'],
                        $stripeOptions
                    );
                    $createdAt = date('Y-m-d H:i:s', $stripeRefund->created);
                } catch (Stripe\Exception\ApiErrorException $e) {
                    $createdAt = (new \DateTime())->format('Y-m-d H:i:s');
                }


                $this->addSql('INSERT INTO refund (payment_id, liable_party, amount, data, created_at, updated_at) VALUES (:payment_id, :liable_party, :amount, :data, :created_at, :updated_at)', [
                    'payment_id' => $payment['id'],
                    'liable_party' => 'unknown',
                    'amount' => $refund['amount'],
                    'data' => json_encode(['stripe_refund_id' => $refund['id']]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DELETE FROM refund');
    }
}
