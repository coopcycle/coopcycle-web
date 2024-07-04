<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190926124632 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $stmt = $this->connection->prepare("SELECT * FROM stripe_payment WHERE charge IS NOT NULL");
        $result = $stmt->execute();

        while ($payment = $result->fetchAssociative()) {

            $details = json_decode($payment['details'], true);

            $details['charge'] = $payment['charge'];

            $this->addSql('UPDATE stripe_payment SET details = :details WHERE id = :id', [
                'details' => json_encode($details),
                'id' => $payment['id'],
            ]);
        }

        $this->addSql('ALTER TABLE stripe_payment DROP charge');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE stripe_payment ADD charge VARCHAR(255) DEFAULT NULL');

        $stmt = $this->connection->prepare("SELECT * FROM stripe_payment");
        $result = $stmt->execute();

        while ($payment = $result->fetchAssociative()) {

            $details = json_decode($payment['details'], true);

            if (isset($details['charge']) && !empty($details['charge'])) {

                $charge = $details['charge'];

                unset($details['charge']);

                $this->addSql('UPDATE stripe_payment SET charge = :charge, details = :details WHERE id = :id', [
                    'charge' => $charge,
                    'details' => json_encode($details),
                    'id' => $payment['id'],
                ]);
            }
        }
    }
}
