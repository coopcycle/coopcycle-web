<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260120101439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Updates task.image_count based on task_image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE task AS t SET image_count = images.count FROM (SELECT task_id, COUNT(id) FROM task_image GROUP BY task_id) images WHERE t.id = images.task_id');

    }

    public function down(Schema $schema): void
    {
    }
}
