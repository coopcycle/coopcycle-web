<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use AppBundle\Enum\Optin;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220307181623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE optin_consent ADD accepted BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE optin_consent ADD asked BOOLEAN DEFAULT \'false\' NOT NULL');

        // select users who are customers of the platform and who does not have any consent added yet
        $customersUsers = $this->connection->prepare
        (
            'SELECT u.id FROM api_user u
            LEFT JOIN optin_consent oc ON oc.user_id = u.id
            WHERE oc.user_id IS NULL
            AND u.roles NOT LIKE \'%ROLE_ADMIN%\'
            AND u.roles NOT LIKE \'%ROLE_COURIER%\'
            AND u.roles NOT LIKE \'%ROLE_RESTAURANT%\'
            AND u.roles NOT LIKE \'%ROLE_STORE%\''
        );
        $customersUsers->execute();

        while ($user = $customersUsers->fetch())
        {
            foreach(Optin::values() as $optin) {
                $this->addSql('INSERT INTO optin_consent (user_id, type, created_at) VALUES (:user_id, :type, CURRENT_TIMESTAMP)', [
                    'user_id' => $user['id'],
                    'type' => $optin->getKey()
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE optin_consent DROP accepted');
        $this->addSql('ALTER TABLE optin_consent DROP asked');
    }
}
