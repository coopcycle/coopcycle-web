<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240911103649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split payments made with method "EDENRED+CARD" into 2 payments';
    }

    public function up(Schema $schema): void
    {
        $stmt = $this->connection->prepare('SELECT id FROM sylius_payment_method WHERE code = \'CARD\'');
        $result = $stmt->execute();
        $cardPaymentMethodId = $result->fetchOne();

        $stmt = $this->connection->prepare('SELECT id FROM sylius_payment_method WHERE code = \'EDENRED\'');
        $result = $stmt->execute();
        $edenredPaymentMethodId = $result->fetchOne();

        $stmt = $this->connection->prepare('SELECT p.* FROM sylius_payment p JOIN sylius_payment_method pm ON p.method_id = pm.id WHERE pm.code = \'EDENRED+CARD\'');
        $result = $stmt->execute();

        while ($payment = $result->fetchAssociative()) {

            $details = json_decode($payment['details'], true);

            if (!array_key_exists('amount_breakdown', $details)) {
                 $this->addSql('UPDATE sylius_payment SET method_id = :method_id WHERE id = :id', [
                    'method_id' => $cardPaymentMethodId,
                    'id' => $payment['id']
                ]);
                continue;
            }

            $edenredDetails = [];
            foreach ($details as $key => $value) {
                if (str_starts_with($key, 'edenred_')) {
                    $edenredDetails[$key] = $value;
                }
            }

            if ($details['amount_breakdown']['edenred'] > 0) {
                $this->addSql('INSERT INTO sylius_payment (method_id, order_id, currency_code, amount, state, details, created_at, updated_at) VALUES (:method_id, :order_id, :currency_code, :amount, :state, :details, :created_at, :updated_at)', [
                    'method_id' => $edenredPaymentMethodId,
                    'order_id' => $payment['order_id'],
                    'currency_code' => $payment['currency_code'],
                    'amount' => $details['amount_breakdown']['edenred'],
                    'state' => $payment['state'],
                    'details' => json_encode($edenredDetails),
                    'created_at' => $payment['created_at'],
                    'updated_at' => $payment['updated_at'],
                ]);
            }

            foreach (array_keys($edenredDetails) as $key) {
                unset($details[$key]);
            }

            $this->addSql('UPDATE sylius_payment SET method_id = :method_id, amount = :amount, details = :details WHERE id = :id', [
                'method_id' => $cardPaymentMethodId,
                'amount' => $details['amount_breakdown']['card'],
                'details' => json_encode($details),
                'id' => $payment['id']
            ]);

        }

        $this->addSql('UPDATE sylius_payment_method SET is_enabled = \'f\' WHERE code = \'EDENRED+CARD\'');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
