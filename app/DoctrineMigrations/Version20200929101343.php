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
final class Version20200929101343 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Delete invalid customer phone numbers';
    }

    public function up(Schema $schema) : void
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        $stmt = $this->connection->prepare('SELECT id, phone_number FROM sylius_customer WHERE phone_number IS NOT NULL');
        $stmt->execute();
        while ($customer = $stmt->fetch()) {

            $delete = false;

            try {
                $phoneNumber = $phoneNumberUtil->parse($customer['phone_number']);
                if (!$phoneNumberUtil->isValidNumber($phoneNumber)) {
                    $delete = true;
                }
            } catch (NumberParseException $e) {
                $delete = true;
            }

            if ($delete) {
                $this->addSql('UPDATE sylius_customer SET phone_number = NULL WHERE id = :id' , [
                    'id' => $customer['id'],
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
    }
}
