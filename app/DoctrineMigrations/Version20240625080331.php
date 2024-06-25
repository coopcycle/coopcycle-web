<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240625080331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_package DROP CONSTRAINT FK_C1894D7FF44CABFF');
        $this->addSql('ALTER TABLE task_package ADD CONSTRAINT FK_C1894D7FF44CABFF FOREIGN KEY (package_id) REFERENCES package (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_package DROP CONSTRAINT fk_c1894d7ff44cabff');
        $this->addSql('ALTER TABLE task_package ADD CONSTRAINT fk_c1894d7ff44cabff FOREIGN KEY (package_id) REFERENCES package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
