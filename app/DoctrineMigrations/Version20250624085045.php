<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250624085045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $stmt = $this->connection->prepare('SELECT id, template FROM task_rrule');

        $result = $stmt->execute();
        while ($rule = $result->fetchAssociative()) {

            $template = json_decode($rule['template'], true);

            if ($template['@type'] === 'hydra:Collection') {

                foreach ($template['hydra:member'] as $i => $task) {

                    $tags = array_map(function ($tag) {
                        if (is_array($tag)) {
                            return $tag['slug'];
                        }
                        return $tag;
                    }, $task['tags']);

                    $template['hydra:member'][$i]['tags'] = $tags;

                }

                $this->addSql('UPDATE task_rrule SET template = :template WHERE id = :id', [
                            'template' => json_encode($template),
                            'id' => $rule['id']
                        ]);
            }

        }

    }

    public function down(Schema $schema): void
    {

    }
}
