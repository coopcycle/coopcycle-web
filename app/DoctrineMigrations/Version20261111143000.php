<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give delivery forms a name, so admins can tell them apart.
 *
 * The column is nullable because existing forms have no name yet: they are
 * still displayed with their hashid until an admin names them.
 */
final class Version20261111143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a name column to delivery_form';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_form ADD name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_form DROP name');
    }
}
