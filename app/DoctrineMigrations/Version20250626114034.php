<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250626114034 extends AbstractMigration
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

                $shouldUpdate = false;

                foreach ($template['hydra:member'] as $i => $task) {

                    if (isset($task['tags']) && is_array($task['tags'])) {

                        // Remove NULL values
                        $tags = array_filter($task['tags']);

                        $template['hydra:member'][$i]['tags'] = $tags;

                        $shouldUpdate = true;
                    }

                }

                if ($shouldUpdate) {
                    $this->addSql('UPDATE task_rrule SET template = :template WHERE id = :id', [
                                'template' => json_encode($template),
                                'id' => $rule['id']
                            ]);
                }
            }

        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
