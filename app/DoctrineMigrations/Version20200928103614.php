<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200928103614 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $stmt = $this->connection->prepare('SELECT u.id AS user_id, c.id customer_id, u.telephone AS user_telephone, c.phone_number AS customer_phone_number FROM api_user u JOIN sylius_customer c ON u.customer_id = c.id');
        $result = $stmt->execute();
        while ($data = $result->fetchAssociative()) {
            if (!$data['customer_phone_number'] && $data['user_telephone']) {
                try {
                    $phoneNumber =
                        PhoneNumberUtil::getInstance()->parse($data['user_telephone']);
                    $this->addSql('UPDATE sylius_customer SET phone_number = :phone_number WHERE id = :id' , [
                        'phone_number' =>
                            PhoneNumberUtil::getInstance()->format($phoneNumber, PhoneNumberFormat::E164),
                        'id' => $data['customer_id'],
                    ]);
                } catch (NumberParseException $e) {}
            }
        }

        $this->addSql('ALTER TABLE api_user DROP given_name');
        $this->addSql('ALTER TABLE api_user DROP family_name');
        $this->addSql('ALTER TABLE api_user DROP telephone');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user ADD given_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD family_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD telephone VARCHAR(35) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN api_user.telephone IS \'(DC2Type:phone_number)\'');

        $stmt = $this->connection->prepare('SELECT c.id AS customer_id, u.id AS user_id, c.phone_number as customer_phone_number FROM sylius_customer c LEFT JOIN api_user u ON c.id = u.customer_id');
        $result = $stmt->execute();
        while ($data = $result->fetchAssociative()) {
            // user_id may be NULL
            if ($data['user_id'] && $data['customer_phone_number']) {
                $this->addSql('UPDATE api_user SET telephone = :telephone WHERE id = :id' , [
                    'telephone' => $data['customer_phone_number'],
                    'id' => $data['user_id'],
                ]);
            }
        }
    }
}
