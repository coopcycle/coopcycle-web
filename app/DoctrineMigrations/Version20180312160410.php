<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180312160410 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $defaults = [
            'brand_name' => 'CoopCycle',
        ];

        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM craue_config_setting WHERE name = :name');

        foreach ($defaults as $name => $value) {
            $stmt->bindParam('name', $name);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            if ((int) $count === 0) {
                $this->addSql('INSERT INTO craue_config_setting (name, section, value) VALUES (:name, :section, :value)', [
                    'name' => $name,
                    'section' => 'general',
                    'value' => $value
                ]);
            }
        }

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
