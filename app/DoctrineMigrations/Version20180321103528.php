<?php declare(strict_types = 1);

namespace Application\Migrations;

use Cocur\Slugify\Slugify;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180321103528 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $slugify = new Slugify([
            'separator' => '_',
            'lowercase' => false,
        ]);

        $stmt = $this->connection->prepare('SELECT * FROM api_user');
        $result = $stmt->execute();

        while ($user = $result->fetchAssociative()) {

            $username = $slugify->slugify($user['username']);

            if (strlen($username) > 15) {
                $username = substr($username, 0, 15);
            }

            $this->addSql('UPDATE api_user SET username = :username, username_canonical = :username_canonical WHERE id = :id', [
                'username' => $username,
                'username_canonical' => strtolower($username),
                'id' => $user['id']
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
