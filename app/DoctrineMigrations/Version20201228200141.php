<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201228200141 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX uniq_f11d61a2a76ed395');
        $this->addSql('ALTER TABLE invitation ADD email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invitation ADD grants JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN invitation.grants IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE INDEX IDX_F11D61A2A76ED395 ON invitation (user_id)');

        $restaurantsForUser = $this->connection->prepare('SELECT * FROM api_user_restaurant WHERE api_user_id = :user_id');
        $storesForUser = $this->connection->prepare('SELECT * FROM api_user_store WHERE api_user_id = :user_id');

        $allInvitations = $this->connection->prepare('SELECT i.code, i.user_id, u.email_canonical, u.customer_id, u.roles FROM invitation i JOIN api_user u ON i.user_id = u.id');
        $result = $allInvitations->execute();

        while ($invitation = $result->fetchAssociative()) {

            $roles = unserialize($invitation['roles']);

            $restaurants = [];
            $stores = [];

            $restaurantsForUser->bindParam('user_id', $invitation['user_id']);
            $result2 = $restaurantsForUser->execute();
            while ($restaurant = $result2->fetchAssociative()) {
                $restaurants[] = $restaurant['restaurant_id'];
            }

            $storesForUser->bindParam('user_id', $invitation['user_id']);
            $result3 = $storesForUser->execute();
            while ($store = $result3->fetchAssociative()) {
                $stores[] = $store['store_id'];
            }

            $grants = [
                'restaurants' => $restaurants,
                'stores' => $stores,
                'roles' => $roles,
            ];

            $this->addSql('UPDATE invitation SET email = :email, user_id = NULL, grants = :grants WHERE code = :code', [
                'email' => $invitation['email_canonical'],
                'code' => $invitation['code'],
                'grants' => json_encode($grants),
            ]);
        }

        $this->addSql('ALTER TABLE invitation ALTER COLUMN email SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX IDX_F11D61A2A76ED395');
        $this->addSql('ALTER TABLE invitation DROP email');
        $this->addSql('CREATE UNIQUE INDEX uniq_f11d61a2a76ed395 ON invitation (user_id)');
    }
}
