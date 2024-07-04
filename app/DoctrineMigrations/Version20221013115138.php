<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221013115138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $stmt = $this->connection->prepare('SELECT * FROM task_rrule');
        $result = $stmt->execute();

        while ($rrule = $result->fetchAssociative()) {

            $template = json_decode($rrule['template'], true);

            if ($template['@type'] === 'Task') {
                unset($template['address']['@id']);
            } else {
                $template['hydra:member'] = array_map(function ($member) {
                    unset($member['address']['@id']);
                    return $member;
                }, $template['hydra:member']);
            }

            $this->addSql('UPDATE task_rrule SET template = :template WHERE id = :id', [
                'template' => json_encode($template),
                'id' => $rrule['id'],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
