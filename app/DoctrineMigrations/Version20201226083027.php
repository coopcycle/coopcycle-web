<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201226083027 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $stmt = $this->connection->prepare('SELECT id, options FROM restaurant_fulfillment_method');
        $result = $stmt->execute();

        while ($fm = $result->fetchAssociative()) {

            if (empty($fm['options'])) {
                continue;
            }

            $options = json_decode($fm['options'], true);

            $options['range_duration'] = ($options['round'] ?? 5) * 2;
            unset($options['round']);

            $this->addSql('UPDATE restaurant_fulfillment_method SET options = :options WHERE id = :id', [
                'options' => json_encode($options),
                'id' => $fm['id'],
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
